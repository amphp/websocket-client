<?php

namespace Amp\Websocket\Client;

use Amp\CancellationToken;
use Amp\Promise;
use Amp\Socket\ClientConnectContext;
use Amp\Socket\ClientTlsContext;

interface Connector
{
    /**
     * @param Handshake $handshake
     * @param ClientConnectContext|null $connectContext
     * @param ClientTlsContext|null $tlsContext
     * @param CancellationToken|null $cancellationToken
     *
     * @return Promise<Connection>
     *
     * @throws ConnectionException If connecting to the Websocket fails.
     */
    public function connect(
        Handshake $handshake,
        ?ClientConnectContext $connectContext = null,
        ?ClientTlsContext $tlsContext = null,
        ?CancellationToken $cancellationToken = null
    ): Promise;
}
