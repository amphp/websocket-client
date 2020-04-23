<?php

namespace Amp\Websocket\Client;

use Amp\Http\Client\Response;
use Amp\Socket\Socket;
use Amp\Websocket\CompressionContext;
use Amp\Websocket\Options;
use Amp\Websocket\Rfc6455Client;

final class Rfc6455ConnectionFactory implements ConnectionFactory
{
    public function createConnection(
        Response $response,
        Socket $socket,
        Options $options,
        ?CompressionContext $compressionContext = null
    ): Connection {
        $client = new Rfc6455Client($socket, $options, true, $compressionContext);
        return new Rfc6455Connection($client, $response);
    }
}
