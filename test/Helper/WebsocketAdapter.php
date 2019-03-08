<?php

namespace Amp\Websocket\Client\Test\Helper;

use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Amp\Promise;
use Amp\Success;
use Amp\Websocket\Client;
use Amp\Websocket\Server\Websocket;

class WebsocketAdapter extends Websocket
{
    public function onHandshake(Request $request, Response $response): Promise
    {
        return new Success($response);
    }

    public function onConnect(Client $client, Request $request, Response $response): ?Promise
    {
        return null;
    }
}
