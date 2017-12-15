<?php

namespace Amp\Websocket;

use Amp\Promise;

class Rfc6455Connection implements Connection {
    /** @var \Amp\Websocket\Rfc6455Endpoint */
    private $processor;

    /** @var \Amp\Websocket\Message */
    private $message;

    public function __construct($socket, array $headers, string $buffer, array $options = []) {
        $this->processor = new Rfc6455Endpoint($socket, $headers, $buffer, $options);
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
        return $this->processor->getInfo();
    }

    public function setOption(string $option, $value) {
        $this->processor->setOption($option, $value);
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

    public function rewind() {
    }

    public function __destruct() {
        $this->processor->close(Code::NORMAL_CLOSE, "");
    }
}
