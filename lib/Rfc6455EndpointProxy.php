<?php

namespace Amp\Websocket;

class Rfc6455EndpointProxy implements Endpoint {
    private $endpoint;

    public function __construct(Rfc6455Endpoint $endpoint) {
        $this->endpoint = $endpoint;
    }

    public function send($data) {
        return $this->endpoint->send($data);
    }

    public function sendBinary($data) {
        return $this->endpoint->send($data, $binary = true);
    }

    public function close($code = Code::NORMAL_CLOSE, $reason = "") {
        $this->endpoint->close($code, $reason);
    }

    public function getInfo() {
        return $this->endpoint->getInfo();
    }
}
