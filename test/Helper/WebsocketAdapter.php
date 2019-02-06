<?php

namespace Amp\Websocket\Client\Test\Helper;

use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Amp\Websocket\Client;
use Amp\Websocket\Server\Websocket;

class WebsocketAdapter extends Websocket
{
    public function onHandshake(Request $request, Response $response)
    {
        return $response;
    }

    public function onConnection(Client $client, Request $request)
    {
        while ($message = yield $client->receive());
    }
}
