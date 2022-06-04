<?php

namespace Amp\Websocket\Client;

use Amp\Http\Client\Response;
use Amp\Socket\Socket;
use Amp\Websocket\CompressionContext;

interface WebsocketConnectionFactory
{
    /**
     * @param Response $handshakeResponse Response that initiated the websocket connection.
     * @param Socket $socket Underlying socket to be used for network communication.
     * @param CompressionContext|null $compressionContext CompressionContext generated from the response headers.
     */
    public function createConnection(
        Response $handshakeResponse,
        Socket $socket,
        ?CompressionContext $compressionContext = null,
    ): WebsocketConnection;
}
