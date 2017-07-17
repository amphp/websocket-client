<?php

namespace Amp\Websocket;

use Amp\ByteStream\IteratorStream;
use Amp\Coroutine;
use Amp\Deferred;
use Amp\Emitter;
use Amp\Loop;
use Amp\Promise;
use Amp\Socket\Socket;
use Amp\Success;

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

    /** @var \Amp\Socket\Socket */
    private $socket;

    /** @var \Generator */
    private $parser;

    public $pingCount = 0;
    public $pongCount = 0;

    /** @var \Amp\Websocket\Message[] */
    private $readQueue = [];

    /** @var \Amp\Emitter[] */
    private $readEmitters = [];

    /** @var \Amp\Emitter */
    private $msgEmitter;

    /** @var \Amp\Promise|null */
    private $lastWrite;

    /** @var int */
    private $closeTimeout;

    /** @var string */
    private $timeoutWatcher;

    // getInfo() properties
    private $connectedAt;
    private $closedAt = 0;
    private $lastReadAt = 0;
    private $lastSentAt = 0;
    private $lastDataReadAt = 0;
    private $lastDataSentAt = 0;
    private $bytesRead = 0;
    private $bytesSent = 0;
    private $framesRead = 0;
    private $framesSent = 0;
    private $messagesRead = 0;
    private $messagesSent = 0;
    private $closeCode;
    private $closeReason;
    
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

    public function __construct(Socket $socket, array $headers, string $buffer) {
        if (!$headers) {
            throw new ServerException;
        }
        $this->timeoutWatcher = Loop::repeat(1000, [$this, "timeout"]);
        Loop::unreference($this->timeoutWatcher);
        $this->connectedAt = \time();
        $this->socket = $socket;
        $this->parser = $this->parser($this);

        if ($buffer !== "") {
            $this->framesRead += $this->parser->send($buffer);
        }

        Promise\rethrow(new Coroutine($this->read()));
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
            $this->msgEmitter->fail(new ServerException("The connection was closed"));
        }
        // Don't unload the client here, it will be unloaded upon timeout
    }

    public function pull(): Message {
        if ($this->readQueue) {
            $message = \reset($this->readQueue);
            unset($this->readQueue[\key($this->readQueue)]);
        } else {
            $emitter = new Emitter;
            $deferred = new Deferred;
            $message = new Message(new IteratorStream($emitter->iterate()), $deferred->promise());
            $this->readEmitters[] = [$emitter, $message, $deferred];
        }

        return $message;
    }

    public function messageCount(): int {
        return $this->messagesRead + \count($this->readEmitters) - \count($this->readQueue);
    }

    public function isClosed(): bool {
        return (bool) $this->closedAt;
    }

    private function sendCloseFrame(int $code, string $msg): Promise {
        $promise = $this->write(pack('n', $code) . $msg, self::OP_CLOSE);
        $this->closedAt = \time();
        $promise->onResolve(function () {
            $this->socket->close();
        });
        return $promise;
    }

    private function unloadServer() {
        $this->parser = null;
        $this->socket->close();

        $exception = new ServerException("The connection was closed");

        // fail not yet terminated message streams; they *must not* be failed before client is removed
        if ($this->msgEmitter) {
            $this->msgEmitter->fail($exception);
            foreach ($this->readEmitters as list($emitter)) {
                $emitter->fail($exception);
            }
        }
    }

    private function onParsedControlFrame(int $opcode, string $data) {
        if ($this->closedAt) {
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

                    $this->close($code, $reason);
                }
                break;

            case self::OP_PING:
                $this->write($data, self::OP_PONG);
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
                list($this->msgEmitter, $msg, $binaryDeferred) = \reset($this->readEmitters);
                $binaryDeferred->resolve($binary);
                unset($this->readEmitters[\key($this->readEmitters)]);
            } else {
                $this->msgEmitter = new Emitter;
                $msg = new Message(new IteratorStream($this->msgEmitter->iterate()), new Success($binary));
                $this->readQueue[] = $msg;
            }
        }

        $this->msgEmitter->emit($data);
        if ($terminated) {
            $msgEmitter = $this->msgEmitter;
            $this->msgEmitter = null;
            $msgEmitter->complete();
            ++$this->messagesRead;
        }
    }

    private function onParsedError(int $code, string $msg) {
        if ($this->closedAt) {
            return;
        }

        $this->close($code, $msg);
    }

    public function read(): \Generator {
        while (($chunk = yield $this->socket->read()) !== null) {
            $this->lastReadAt = \time();
            $this->bytesRead += \strlen($chunk);
            $this->framesRead += $this->parser->send($chunk);
        }

        if (!$this->closedAt) {
            $this->closedAt = \time();
            $this->closeCode = Code::ABNORMAL_CLOSE;
            $this->closeReason = "Client closed underlying TCP connection";
        } else {
            $this->closeTimeout = null;
        }

        $this->unloadServer();
    }

    private function compile(string $msg, int $opcode, bool $fin): string {
        $rsv = 0b000; // @TODO Add filter mechanism (e.g. for gzip encoding)

        $len = \strlen($msg);
        $w = chr(($fin << 7) | ($rsv << 4) | $opcode);

        if ($len > 0xFFFF) {
            $w .= "\xFF" . pack('J', $len);
        } elseif ($len > 0x7D) {
            $w .= "\xFE" . pack('n', $len);
        } else {
            $w .= chr($len | 0x80);
        }

        $mask = pack('N', random_int(\PHP_INT_MIN, \PHP_INT_MAX));

        $w .= $mask;
        $w .= $msg ^ str_repeat($mask, ($len + 3) >> 2);

        return $w;
    }

    private function write(string $msg, int $opcode, bool $fin = true): Promise {
        $frame = $this->compile($msg, $opcode, $fin);

        ++$this->framesSent;
        $this->bytesSent += \strlen($frame);
        $this->lastSentAt = \time();

        return $this->socket->write($frame);
    }

    public function send(string $data, bool $binary = false): Promise {
        $this->messagesSent++;

        $opcode = $binary ? self::OP_BIN : self::OP_TEXT;
        assert($binary || preg_match("//u", $data), "non-binary data needs to be UTF-8 compatible");

        return $this->lastWrite = new Coroutine($this->doSend($data, $opcode));
    }

    private function doSend(string $data, int $opcode): \Generator {
        if ($this->lastWrite) {
            yield $this->lastWrite;
        }

        try {
            $bytes = 0;

            if (\strlen($data) > 1.5 * $this->autoFrameSize) {
                $len = \strlen($data);
                $slices = \ceil($len / $this->autoFrameSize);
                $chunks = \str_split($data, \ceil($len / $slices));
                $final = \array_pop($chunks);
                foreach ($chunks as $chunk) {
                    $bytes += yield $this->write($chunk, $opcode, false);
                    $opcode = self::OP_CONT;
                }
                $bytes += yield $this->write($final, $opcode, true);
            } else {
                $bytes = yield $this->write($data, $opcode);
            }
        } catch (\Throwable $exception) {
            $this->close();
            throw $exception;
        } finally {
            $this->lastWrite = null;
        }

        return $bytes;
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

    public function setOption(string $option, $value) {
        switch ($option) {
            case "maxBytesPerMinute":
                if (8192 > $value) {
                    throw new \Error("$option must be at least 8192 bytes");
                }
            case "autoFrameSize":
            case "maxFrameSize":
            case "maxFramesPerSecond":
            case "maxMsgSize":
            case "heartbeatPeriod":
            case "closePeriod":
            case "queuedPingLimit":
                if (0 <= $value = filter_var($value, FILTER_VALIDATE_INT)) {
                    throw new \Error("$option must be a positive integer greater than 0");
                }
                break;
            case "validateUtf8":
            case "textOnly":
                if (null === $value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)) {
                    throw new \Error("$option must be a boolean value");
                }
                break;
            default:
                throw new \Error("Unknown option $option");
        }
        $this->{$option} = $value;
    }

    public function getInfo(): array {
        return [
            'bytes_read'    => $this->bytesRead,
            'bytes_sent'    => $this->bytesSent,
            'frames_read'   => $this->framesRead,
            'frames_sent'   => $this->framesSent,
            'messages_read' => $this->messagesRead,
            'messages_sent' => $this->messagesSent,
            'connected_at'  => $this->connectedAt,
            'closed_at'     => $this->closedAt,
            'last_read_at'  => $this->lastReadAt,
            'last_sent_at'  => $this->lastSentAt,
            'last_data_read_at'  => $this->lastDataReadAt,
            'last_data_sent_at'  => $this->lastDataSentAt,
        ];
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
                        $endpoint->onParsedError(
                            Code::MESSAGE_TOO_LARGE,
                            'Payload exceeds maximum allowable size'
                        );
                        return;
                    }
                    $frameLength = $lengthLong32Pair[2];
                } else {
                    $frameLength = ($lengthLong32Pair[1] << 32) | $lengthLong32Pair[2];
                    if ($frameLength < 0) {
                        $endpoint->onParsedError(
                            Code::PROTOCOL_ERROR,
                            'Most significant bit of 64-bit length field set'
                        );
                        return;
                    }
                }
            }

            if ($isMasked) {
                $endpoint->onParsedError(
                    Code::PROTOCOL_ERROR,
                    'Payload must not be masked to client'
                );
                return;
            }

            if ($isControlFrame) {
                if (!$fin) {
                    $endpoint->onParsedError(
                        Code::PROTOCOL_ERROR,
                        'Illegal control frame fragmentation'
                    );
                    return;
                }

                if ($frameLength > 125) {
                    $endpoint->onParsedError(
                        Code::PROTOCOL_ERROR,
                        'Control frame payload must be of maximum 125 bytes or less'
                    );
                    return;
                }
            } elseif (($opcode === 0x00) === ($dataMsgBytesRecd === 0)) {
                // We deliberately do not accept a non-fin empty initial text frame
                $code = Code::PROTOCOL_ERROR;
                if ($opcode === 0x00) {
                    $errorMsg = 'Illegal CONTINUATION opcode; initial message payload frame must be TEXT or BINARY';
                } else {
                    $errorMsg = 'Illegal data type opcode after unfinished previous data type frame; opcode MUST be CONTINUATION';
                }
                $endpoint->onParsedError($code, $errorMsg);
                return;
            }

            if ($maxFrameSize && $frameLength > $maxFrameSize) {
                $endpoint->onParsedError(
                    Code::MESSAGE_TOO_LARGE,
                    'Payload exceeds maximum allowable size'
                );
                return;
            }

            if ($maxMsgSize && ($frameLength + $dataMsgBytesRecd) > $maxMsgSize) {
                $endpoint->onParsedError(
                    Code::MESSAGE_TOO_LARGE,
                    'Payload exceeds maximum allowable size'
                );
                return;
            }

            if ($textOnly && $opcode === 0x02) {
                $endpoint->onParsedError(
                    Code::UNACCEPTABLE_TYPE,
                    'BINARY opcodes (0x02) not accepted'
                );
                return;
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
                                $endpoint->onParsedError(
                                    Code::INCONSISTENT_FRAME_DATA_TYPE,
                                    'Invalid TEXT data; UTF-8 required'
                                );
                                return;
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
                            $endpoint->onParsedError(
                                Code::INCONSISTENT_FRAME_DATA_TYPE,
                                'Invalid TEXT data; UTF-8 required'
                            );
                            return;
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
    }
}
