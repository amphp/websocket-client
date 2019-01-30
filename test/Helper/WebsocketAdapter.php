<?php

namespace Amp\Websocket\Test\Helper;

use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Amp\Http\Server\Websocket;
use Amp\Http\Server\Websocket\Message;

class WebsocketAdapter extends Websocket\Websocket {
    public function onHandshake(Request $request, Response $response) {
        return $response;
    }

    public function onOpen(int $clientId, Request $request){
    }

    public function onData(int $clientId, Message $message) {
    }

    public function onClose(int $clientId, int $code, string $reason){
    }
}
