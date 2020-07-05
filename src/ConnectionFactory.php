<?php

namespace Amp\Websocket\Client;

use Amp\Http\Client\Response;
use Amp\Socket\Socket;
use Amp\Websocket\CompressionContext;
use Amp\Websocket\Options;

interface ConnectionFactory
{
    /**
     * @param Response                $response
     * @param Socket                  $socket
     * @param Options                 $options
     * @param CompressionContext|null $compressionContext
     *
     * @return Connection
     */
    public function createConnection(
        Response $response,
        Socket $socket,
        Options $options,
        ?CompressionContext $compressionContext = null
    ): Connection;
}
