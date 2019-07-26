<?php

namespace Amp\Websocket\Client;

use Amp\CancellationToken;
use Amp\Promise;
use Amp\Socket\ConnectContext;

interface Connector
{
    /**
     * @param Handshake $handshake
     * @param ConnectContext|null $connectContext
     * @param CancellationToken|null $cancellationToken
     *
     * @return Promise<Connection>
     *
     * @throws ConnectionException If connecting to the Websocket fails.
     */
    public function connect(
        Handshake $handshake,
        ?ConnectContext $connectContext = null,
        ?CancellationToken $cancellationToken = null
    ): Promise;
}
