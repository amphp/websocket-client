<?php

namespace Amp\Websocket\Client;

use Amp\Http\Client\Response;
use Amp\Socket\Socket;
use Amp\Websocket\CompressionContext;

interface ConnectionFactory
{
    /**
     * @param Response $response Response that initiated the websocket connection.
     * @param Socket $socket Underlying socket to be used for network communication.
     * @param CompressionContext|null $compressionContext CompressionContext generated from the response headers.
     *
     * @return Connection
     */
    public function createConnection(
        Response $response,
        Socket $socket,
        ?CompressionContext $compressionContext = null,
    ): Connection;
}
