<?php

namespace Amp\Websocket\Test\Helper;

use Aerys\Request;
use Aerys\Response;
use Aerys\Websocket;

class WebsocketAdapter implements Websocket {
    /** @var Websocket\Endpoint */
    protected $endpoint;

    public function onStart(Websocket\Endpoint $endpoint) {
        $this->endpoint = $endpoint;
    }

    public function onHandshake(Request $request, Response $response) {
        // nothing to do
    }

    public function onOpen(int $clientId, $handshakeData) {
        // nothing to do
    }

    public function onData(int $clientId, Websocket\Message $msg) {
        // nothing to do
    }

    public function onClose(int $clientId, int $code, string $reason) {
        // nothing to do
    }

    public function onStop() {
        // nothing to do
    }
}
