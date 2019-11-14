<?php

namespace Amp\Websocket\Client;

use Amp\CancellationToken;
use Amp\Http\Client\HttpException;
use Amp\Promise;

interface Connector
{
    /**
     * @param Handshake $handshake
     * @param CancellationToken|null $cancellationToken
     *
     * @return Promise<Connection>
     *
     * @throws HttpException Thrown if the request fails.
     * @throws ConnectionException If the response received is invalid or is not a switching protocols (101) response.
     */
    public function connect(Handshake $handshake, ?CancellationToken $cancellationToken = null): Promise;
}
