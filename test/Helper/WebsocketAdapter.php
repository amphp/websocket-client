<?php

namespace Amp\Websocket\Client\Test\Helper;

use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Amp\Promise;
use Amp\Success;
use Amp\Websocket\Client;
use Amp\Websocket\Server\ClientHandler;
use Amp\Websocket\Server\Websocket;

class WebsocketAdapter implements ClientHandler
{
    public function onStart(Websocket $endpoint): Promise
    {
        return new Success; // nothing to do
    }

    public function onStop(Websocket $endpoint): Promise
    {
        return new Success; // nothing to do
    }

    public function handleHandshake(Request $request, Response $response): Promise
    {
        return new Success($response);
    }

    public function handleClient(Client $client, Request $request, Response $response): Promise
    {
        return new Success;
    }
}
