<?php

namespace Amp\Websocket\Client\Test\Helper;

use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Amp\Websocket\Server\ClientHandler;
use Amp\Websocket\Server\Gateway;

abstract class EmptyClientHandler implements ClientHandler
{
    public function handleHandshake(Gateway $gateway, Request $request, Response $response): Response
    {
        return $response;
    }
}
