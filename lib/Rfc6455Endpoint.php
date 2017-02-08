<?php

namespace Amp\Websocket;

use Amp\{ Deferred, Emitter, Failure, Success };
use AsyncInterop\{ Loop, Promise };

class Rfc6455Endpoint {
    public $autoFrameSize = 32768;
    public $maxFrameSize = 2097152;
    public $maxMsgSize = 10485760;
    public $heartbeatPeriod = 10;
    public $closePeriod = 3;
    public $validateUtf8 = false;
    public $textOnly = false;
    public $queuedPingLimit = 3;
    // @TODO add minimum average frame size rate threshold to prevent tiny-frame DoS

    private $socket;
    private $parser;
    private $readWatcher;
    private $writeWatcher;

    public $pingCount = 0;
    public $pongCount = 0;

    private $writeBuffer = '';
    private $writeDeferred;
    private $writeDataQueue = [];
    private $writeDeferredDataQueue = [];
    private $writeControlQueue = [];
    private $writeDeferredControlQueue = [];

    public $readMessage;
    public $readQueue = [];
    public $readEmitters = [];
    public $msgEmitter;

    private $closeTimeout;
    private $timeoutWatcher;

    // getInfo() properties
    public $connectedAt;
    public $closedAt = 0;
    public $lastReadAt = 0;
    public $lastSentAt = 0;
    public $lastDataReadAt = 0;
    public $lastDataSentAt = 0;
    public $bytesRead = 0;
    public $bytesSent = 0;
    public $framesRead = 0;
    public $framesSent = 0;
    public $messagesRead = 0;
    public $messagesSent = 0;
    public $closeCode;
    public $closeReason;
    
    /* Frame control bits */
    const FIN      = 0b1;
    const RSV_NONE = 0b000;
    const OP_CONT  = 0x00;
    const OP_TEXT  = 0x01;
    const OP_BIN   = 0x02;
    const OP_CLOSE = 0x08;
    const OP_PING  = 0x09;
    const OP_PONG  = 0x0A;

    const CONTROL = -1;
    const ERROR = -2;

    public function __construct($socket, array $headers) {
        if (!$headers) {
            throw new ServerException;
        }
        $this->timeoutWatcher = Loop::repeat(1000, [$this, "timeout"]);
        Loop::unreference($this->timeoutWatcher);
        $this->connectedAt = \time();
        $this->socket = $socket;
        $this->parser = $this->parser($this);
        $this->writeWatcher = Loop::onWritable($socket, [$this, "onWritable"]);
        Loop::disable($this->writeWatcher);
        $this->readWatcher = Loop::onReadable($this->socket, [$this, "onReadable"]);
    }

    public function close(int $code = Code::NORMAL_CLOSE, string $reason = '') {
        // Only proceed if we haven't already begun the close handshake elsewhere
        if ($this->closedAt) {
            return;
        }

        $this->closeTimeout = \time() + $this->closePeriod;
        $this->closeCode = $code;
        $this->closeReason = $reason;
        $this->sendCloseFrame($code, $reason);
        if ($this->msgEmitter) {
            $this->msgEmitter->fail(new ServerException);
        }
        // Don't unload the client here, it will be unloaded upon timeout
    }

    private function sendCloseFrame($code, $msg) {
        $promise = $this->compile(pack('n', $code) . $msg, self::OP_CLOSE);
        $this->closedAt = \time();
        return $promise;
    }

    private function unloadServer() {
        $this->parser = null;
        if ($this->readWatcher) {
            Loop::cancel($this->readWatcher);
        }
        if ($this->writeWatcher) {
            Loop::cancel($this->writeWatcher);
        }

        // fail not yet terminated message streams; they *must not* be failed before client is removed
        if ($this->msgEmitter) {
            $this->msgEmitter->fail(new ServerException);
            foreach ($this->readEmitters as list($emitter)) {
                $emitter->fail(new ServerException);
            }
        }

        if ($this->writeBuffer != "") {
            $this->writeDeferred->fail(new ServerException);
        }
        foreach ([$this->writeDeferredDataQueue, $this->writeDeferredControlQueue] as $deferreds) {
            foreach ($deferreds as $deferred) {
                $deferred->fail(new ServerException);
            }
        }
    }

    private function onParsedControlFrame(int $opcode, string $data) {
        // something went that wrong that we had to shutdown our readWatcher... if parser has anything left, we don't care!
        if (!$this->readWatcher) {
            return;
        }

        switch ($opcode) {
            case self::OP_CLOSE:
                if ($this->closedAt) {
                    $this->closeTimeout = null;
                    $this->unloadServer();
                } else {
                    if (\strlen($data) < 2) {
                        return; // invalid close reason
                    }
                    $code = current(unpack('S', substr($data, 0, 2)));
                    $reason = substr($data, 2);

                    @stream_socket_shutdown($this->socket, STREAM_SHUT_RD);
                    Loop::cancel($this->readWatcher);
                    $this->readWatcher = null;
                    $this->close($code, $reason);
                }
                break;

            case self::OP_PING:
                $this->compile($data, self::OP_PONG);
                break;

            case self::OP_PONG:
                // We need a min() here, else someone might just send a pong frame with a very high pong count and leave TCP connection in open state...
                $this->pongCount = min($this->pingCount, $data);
                break;
        }
    }

    private function onParsedData(string $data, int $opcode, bool $terminated) {
        if ($this->closedAt) {
            return;
        }

        $this->lastDataReadAt = \time();

        if (!$this->msgEmitter) {
            $binary = $opcode === self::OP_BIN;
            if ($this->readEmitters) {
                list($this->msgEmitter, $msg) = \reset($this->readEmitters);
                $msg->setBinary($binary);
                unset($this->readEmitters[\key($this->readEmitters)]);
            } else {
                $this->msgEmitter = new Emitter;
                $msg = new Message($this->msgEmitter->stream(), $binary);
                $this->readQueue[] = $msg;
            }
        }

        $this->msgEmitter->emit($data);
        if ($terminated) {
            $msgEmitter = $this->msgEmitter;
            $this->msgEmitter = null;
            $msgEmitter->resolve();
            ++$this->messagesRead;
        }
    }

    private function onParsedError(int $code, string $msg) {
        // something went that wrong that we had to shutdown our readWatcher... if parser has anything left, we don't care!
        if (!$this->readWatcher) {
            return;
        }

        if ($code) {
            if ($this->closedAt || $code == Code::PROTOCOL_ERROR) {
                @stream_socket_shutdown($this->socket, STREAM_SHUT_RD);
                Loop::cancel($this->readWatcher);
                $this->readWatcher = null;
            }

            if (!$this->closedAt) {
                $this->close($code, $msg);
            }
        }
    }

    public function onReadable($watcherId, $socket) {
        $data = @\fread($socket, 8192);

        if ($data !== "") {
            $this->lastReadAt = \time();
            $this->bytesRead += \strlen($data);
            $this->framesRead += $this->parser->send($data);
        } elseif (!is_resource($socket) || @feof($socket)) {
            if (!$this->closedAt) {
                $this->closedAt = \time();
                $this->closeCode = Code::ABNORMAL_CLOSE;
                $this->closeReason = "Client closed underlying TCP connection";
            } else {
                $this->closeTimeout = null;
            }

            $this->unloadServer();
        }
    }

    public function onWritable($watcherId, $socket) {
        $bytes = @fwrite($socket, $this->writeBuffer);
        $this->bytesSent += $bytes;

        if ($bytes != \strlen($this->writeBuffer)) {
            $this->writeBuffer = substr($this->writeBuffer, $bytes);
        } elseif ($bytes == 0 && $this->closedAt && (!is_resource($socket) || @feof($socket))) {
            // usually read watcher cares about aborted TCP connections, but when
            // $this->closedAt is true, it might be the case that read watcher
            // is already cancelled and we need to ensure that our writing promise
            // is fulfilled in unloadServer() with a failure
            $this->closeTimeout = null;
            $this->unloadServer();
        } else {
            $this->framesSent++;
            $this->writeDeferred->resolve();
            if ($this->writeControlQueue) {
                $this->writeBuffer = array_shift($this->writeControlQueue);
                $this->lastSentAt = \time();
                $this->writeDeferred = array_shift($this->writeDeferredControlQueue);
            } elseif ($this->closedAt) {
                @stream_socket_shutdown($socket, STREAM_SHUT_WR);
                Loop::cancel($watcherId);
                $this->writeWatcher = null;
                $this->writeBuffer = "";
            } elseif ($this->writeDataQueue) {
                $this->writeBuffer = array_shift($this->writeDataQueue);
                $this->lastDataSentAt = \time();
                $this->lastSentAt = \time();
                $this->writeDeferred = array_shift($this->writeDeferredDataQueue);
            } else {
                $this->writeBuffer = "";
                Loop::disable($watcherId);
            }
        }
    }

    private function compile(string $msg, int $opcode, bool $fin = true): Promise {
        $rsv = 0b000;

        // @TODO Add filter mechanism (e.g. for gzip encoding)

        return $this->write($msg, $opcode, $rsv, $fin);
    }

    private function write(string $msg, int $opcode, int $rsv, bool $fin): Promise {
        if ($this->closedAt) {
            return new Failure(new ServerException);
        }

        $len = \strlen($msg);
        $w = chr(($fin << 7) | ($rsv << 4) | $opcode);

        if ($len > 0xFFFF) {
            $w .= "\xFF" . pack('J', $len);
        } elseif ($len > 0x7D) {
            $w .= "\xFE" . pack('n', $len);
        } else {
            $w .= chr($len | 0x80);
        }

        $mask = pack('N', mt_rand(-0x7fffffff - 1, 0x7fffffff)); // this is not a CSPRNG, but good enough for our use cases

        $w .= $mask;
        $w .= $msg ^ str_repeat($mask, ($len + 3) >> 2);

        Loop::enable($this->writeWatcher);
        if ($this->writeBuffer != "") {
            if ($opcode >= 0x8) {
                $this->writeControlQueue[] = $w;
                $deferred = $this->writeDeferredControlQueue[] = new Deferred;
            } else {
                $this->writeDataQueue[] = $w;
                $deferred = $this->writeDeferredDataQueue[] = new Deferred;
            }
        } else {
            $this->writeBuffer = $w;
            $deferred = $this->writeDeferred = new Deferred;
        }

        return $deferred->promise();
    }

    public function send(string $data, bool $binary = false): Promise {
        $this->messagesSent++;

        $opcode = $binary ? self::OP_BIN : self::OP_TEXT;
        assert($binary || preg_match("//u", $data), "non-binary data needs to be UTF-8 compatible");

        if (\strlen($data) > 1.5 * $this->autoFrameSize) {
            $len = \strlen($data);
            $slices = ceil($len / $this->autoFrameSize);
            $frames = str_split($data, ceil($len / $slices));
            $data = array_pop($frames);
            foreach ($frames as $frame) {
                $this->compile($frame, $opcode, false);
                $opcode = self::OP_CONT;
            }
        }
        return $this->compile($data, $opcode);
    }

    public function sendBinary(string $data): Promise {
        return $this->send($data, true);
    }

    public function timeout() {
        $now = \time();

        if ($this->closeTimeout < $now && $this->closedAt) {
            $this->unloadServer();
            $this->closeTimeout = null;
        }
    }

    /**
     * A stateful generator websocket frame parser
     *
     * @param \Amp\Websocket\Rfc6455Endpoint $endpoint Endpoint to receive parser event emissions
     * @param array $options Optional parser settings
     * @return \Generator
     */
    public static function parser(self $endpoint, array $options = []): \Generator {
        $emitThreshold = $options["threshold"] ?? 32768;
        $maxFrameSize = $options["max_frame_size"] ?? PHP_INT_MAX;
        $maxMsgSize = $options["max_msg_size"] ?? PHP_INT_MAX;
        $textOnly = $options["text_only"] ?? false;
        $doUtf8Validation = $validateUtf8 = $options["validate_utf8"] ?? false;

        $dataMsgBytesRecd = 0;
        $nextEmit = $emitThreshold;
        $dataArr = [];

        $buffer = yield;
        $offset = 0;
        $bufferSize = \strlen($buffer);
        $frames = 0;

        while (1) {
            if ($bufferSize < 2) {
                $buffer = substr($buffer, $offset);
                $offset = 0;
                do {
                    $buffer .= yield $frames;
                    $bufferSize = \strlen($buffer);
                    $frames = 0;
                } while ($bufferSize < 2);
            }

            $firstByte = ord($buffer[$offset]);
            $secondByte = ord($buffer[$offset + 1]);

            $offset += 2;
            $bufferSize -= 2;

            $fin = (bool)($firstByte & 0b10000000);
            // $rsv = ($firstByte & 0b01110000) >> 4; // unused (let's assume the bits are all zero)
            $opcode = $firstByte & 0b00001111;
            $isMasked = (bool)($secondByte & 0b10000000);
            $maskingKey = null;
            $frameLength = $secondByte & 0b01111111;

            $isControlFrame = $opcode >= 0x08;
            if ($validateUtf8 && $opcode !== self::OP_CONT && !$isControlFrame) {
                $doUtf8Validation = $opcode === self::OP_TEXT;
            }

            if ($frameLength === 0x7E) {
                if ($bufferSize < 2) {
                    $buffer = substr($buffer, $offset);
                    $offset = 0;
                    do {
                        $buffer .= yield $frames;
                        $bufferSize = \strlen($buffer);
                        $frames = 0;
                    } while ($bufferSize < 2);
                }

                $frameLength = unpack('n', $buffer[$offset] . $buffer[$offset + 1])[1];
                $offset += 2;
                $bufferSize -= 2;
            } elseif ($frameLength === 0x7F) {
                if ($bufferSize < 8) {
                    $buffer = substr($buffer, $offset);
                    $offset = 0;
                    do {
                        $buffer .= yield $frames;
                        $bufferSize = \strlen($buffer);
                        $frames = 0;
                    } while ($bufferSize < 8);
                }

                $lengthLong32Pair = unpack('N2', substr($buffer, $offset, 8));
                $offset += 8;
                $bufferSize -= 8;

                if (PHP_INT_MAX === 0x7fffffff) {
                    if ($lengthLong32Pair[1] !== 0 || $lengthLong32Pair[2] < 0) {
                        $code = Code::MESSAGE_TOO_LARGE;
                        $errorMsg = 'Payload exceeds maximum allowable size';
                        break;
                    }
                    $frameLength = $lengthLong32Pair[2];
                } else {
                    $frameLength = ($lengthLong32Pair[1] << 32) | $lengthLong32Pair[2];
                    if ($frameLength < 0) {
                        $code = Code::PROTOCOL_ERROR;
                        $errorMsg = 'Most significant bit of 64-bit length field set';
                        break;
                    }
                }
            }

            if ($isMasked) {
                $code = Code::PROTOCOL_ERROR;
                $errorMsg = 'Payload must not be masked to client';
                break;
            } elseif ($isControlFrame) {
                if (!$fin) {
                    $code = Code::PROTOCOL_ERROR;
                    $errorMsg = 'Illegal control frame fragmentation';
                    break;
                } elseif ($frameLength > 125) {
                    $code = Code::PROTOCOL_ERROR;
                    $errorMsg = 'Control frame payload must be of maximum 125 bytes or less';
                    break;
                }
            } elseif (($opcode === 0x00) === ($dataMsgBytesRecd === 0)) {
                // We deliberately do not accept a non-fin empty initial text frame
                $code = Code::PROTOCOL_ERROR;
                if ($opcode === 0x00) {
                    $errorMsg = 'Illegal CONTINUATION opcode; initial message payload frame must be TEXT or BINARY';
                } else {
                    $errorMsg = 'Illegal data type opcode after unfinished previous data type frame; opcode MUST be CONTINUATION';
                }
                break;
            } elseif ($maxFrameSize && $frameLength > $maxFrameSize) {
                $code = Code::MESSAGE_TOO_LARGE;
                $errorMsg = 'Payload exceeds maximum allowable frame size';
                break;
            } elseif ($maxMsgSize && ($frameLength + $dataMsgBytesRecd) > $maxMsgSize) {
                $code = Code::MESSAGE_TOO_LARGE;
                $errorMsg = 'Payload exceeds maximum allowable message size';
                break;
            } elseif ($textOnly && $opcode === 0x02) {
                $code = Code::UNACCEPTABLE_TYPE;
                $errorMsg = 'BINARY opcodes (0x02) not accepted';
                break;
            }

            if ($bufferSize >= $frameLength) {
                if (!$isControlFrame) {
                    $dataMsgBytesRecd += $frameLength;
                }

                $payload = substr($buffer, $offset, $frameLength);
                $offset += $frameLength;
                $bufferSize -= $frameLength;
            } else {
                if (!$isControlFrame) {
                    $dataMsgBytesRecd += $bufferSize;
                }
                $frameBytesRecd = $bufferSize;

                $payload = substr($buffer, $offset);

                do {
                    // if we want to validate UTF8, we must *not* send incremental mid-frame updates because the message might be broken in the middle of an utf-8 sequence
                    // also, control frames always are <= 125 bytes, so we never will need this as per https://tools.ietf.org/html/rfc6455#section-5.5
                    if (!$isControlFrame && $dataMsgBytesRecd >= $nextEmit) {
                        if ($isMasked) {
                            $payload ^= str_repeat($maskingKey, ($frameBytesRecd + 3) >> 2);
                            // Shift the mask so that the next data where the mask is used on has correct offset.
                            $maskingKey = substr($maskingKey . $maskingKey, $frameBytesRecd % 4, 4);
                        }

                        if ($dataArr) {
                            $dataArr[] = $payload;
                            $payload = implode($dataArr);
                            $dataArr = [];
                        }

                        if ($doUtf8Validation) {
                            $string = $payload;
                            /* @TODO: check how many bits are set to 1 instead of multiple (slow) preg_match()es and substr()s */
                            for ($i = 0; !preg_match('//u', $payload) && $i < 8; $i++) {
                                $payload = substr($payload, 0, -1);
                            }
                            if ($i === 8) {
                                $code = Code::INCONSISTENT_FRAME_DATA_TYPE;
                                $errorMsg = 'Invalid TEXT data; UTF-8 required';
                                break 2;
                            }

                            $endpoint->onParsedData($payload, $opcode === self::OP_BIN, false);
                            $payload = $i > 0 ? substr($string, -$i) : '';
                        } else {
                            $endpoint->onParsedData($payload, $opcode === self::OP_BIN, false);
                            $payload = '';
                        }

                        $frameLength -= $frameBytesRecd;
                        $nextEmit = $dataMsgBytesRecd + $emitThreshold;
                        $frameBytesRecd = 0;
                    }

                    $buffer = yield $frames;
                    $bufferSize = \strlen($buffer);
                    $frames = 0;

                    if ($bufferSize + $frameBytesRecd >= $frameLength) {
                        $dataLen = $frameLength - $frameBytesRecd;
                    } else {
                        $dataLen = $bufferSize;
                    }

                    if (!$isControlFrame) {
                        $dataMsgBytesRecd += $dataLen;
                    }

                    $payload .= substr($buffer, 0, $dataLen);
                    $frameBytesRecd += $dataLen;
                } while ($frameBytesRecd !== $frameLength);

                $offset = $dataLen;
                $bufferSize -= $dataLen;
            }

            if ($fin || $dataMsgBytesRecd >= $emitThreshold) {
                if ($isControlFrame) {
                    $endpoint->onParsedControlFrame($opcode, $payload);
                } else {
                    if ($dataArr) {
                        $dataArr[] = $payload;
                        $payload = implode($dataArr);
                        $dataArr = [];
                    }

                    if ($doUtf8Validation) {
                        if ($fin) {
                            $i = preg_match('//u', $payload) ? 0 : 8;
                        } else {
                            $string = $payload;
                            for ($i = 0; !preg_match('//u', $payload) && $i < 8; $i++) {
                                $payload = substr($payload, 0, -1);
                            }
                            if ($i > 0) {
                                $dataArr[] = substr($string, -$i);
                            }
                        }
                        if ($i === 8) {
                            $code = Code::INCONSISTENT_FRAME_DATA_TYPE;
                            $errorMsg = 'Invalid TEXT data; UTF-8 required';
                            break;
                        }
                    }

                    if ($fin) {
                        $dataMsgBytesRecd = 0;
                    }
                    $nextEmit = $dataMsgBytesRecd + $emitThreshold;

                    $endpoint->onParsedData($payload, $opcode === self::OP_BIN, $fin);
                }
            } else {
                $dataArr[] = $payload;
            }

            $frames++;
        }

        // An error occurred...
        // stop parsing here ...
        $endpoint->onParsedError($code, $errorMsg);
        yield $frames;
        
        while (1) {
            yield 0;
        }
    }
}