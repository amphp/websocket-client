<?php

namespace Amp\Websocket\Client;

use Amp\CancellationToken;
use Amp\Http\Client\HttpException;

interface Connector
{
    /**
     * @param Handshake $handshake
     * @param CancellationToken|null $cancellationToken
     *
     * @return Connection
     *
     * @throws HttpException Thrown if the request fails.
     * @throws ConnectionException If the response received is invalid or is not a switching protocols (101) response.
     */
    public function connect(Handshake $handshake, ?CancellationToken $cancellationToken = null): Connection;
}
