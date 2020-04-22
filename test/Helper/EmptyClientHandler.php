<?php

namespace Amp\Websocket\Client\Test\Helper;

use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Amp\Promise;
use Amp\Success;
use Amp\Websocket\Client;
use Amp\Websocket\Server\ClientHandler;
use Amp\Websocket\Server\Endpoint;

abstract class EmptyClientHandler implements ClientHandler
{
    public function handleHandshake(Endpoint $endpoint, Request $request, Response $response): Promise
    {
        return new Success($response);
    }

    public function handleClient(Endpoint $endpoint, Client $client, Request $request, Response $response): Promise
    {
        return new Success;
    }
}
