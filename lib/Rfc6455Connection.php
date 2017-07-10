<?php

namespace Amp\Websocket;

use Amp\Promise;

class Rfc6455Connection implements Connection {
    /** @var \Amp\Websocket\Rfc6455Endpoint */
    private $processor;

    /** @var \Amp\Websocket\Message */
    private $message;

    public function __construct($socket, array $headers, string $buffer) {
        $this->processor = new Rfc6455Endpoint($socket, $headers, $buffer);
        $this->next();
    }

    public function send(string $data): Promise {
        return $this->processor->send($data);
    }

    public function sendBinary(string $data): Promise {
        return $this->processor->send($data, true);
    }

    public function close(int $code = Code::NORMAL_CLOSE, string $reason = "") {
        $this->processor->close($code, $reason);
    }

    public function getInfo(): array {
        return [
            'bytes_read'    => $this->processor->bytesRead,
            'bytes_sent'    => $this->processor->bytesSent,
            'frames_read'   => $this->processor->framesRead,
            'frames_sent'   => $this->processor->framesSent,
            'messages_read' => $this->processor->messagesRead,
            'messages_sent' => $this->processor->messagesSent,
            'connected_at'  => $this->processor->connectedAt,
            'closed_at'     => $this->processor->closedAt,
            'last_read_at'  => $this->processor->lastReadAt,
            'last_sent_at'  => $this->processor->lastSentAt,
            'last_data_read_at'  => $this->processor->lastDataReadAt,
            'last_data_sent_at'  => $this->processor->lastDataSentAt,
        ];
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
        $this->processor->{$option} = $value;
    }

    public function current(): Message {
        return $this->message;
    }

    public function next() {
        $this->message = $this->processor->pull();
    }

    public function key(): int {
        return $this->processor->messageCount();
    }

    public function valid(): bool {
        return !$this->processor->isClosed();
    }

    public function rewind() { }

    public function __destruct() {
        $this->processor->close(Code::NORMAL_CLOSE, "");
    }
}
