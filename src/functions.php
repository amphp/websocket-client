<?php

namespace Amp\Websocket\Client;

use Amp\Loop;
use Amp\Promise;
use Amp\Socket\ClientConnectContext;
use Amp\Socket\ClientTlsContext;

const LOOP_CONNECTOR_IDENTIFIER = Connector::class;

/**
 * Set or access the global websocket Connector instance.
 *
 * @param Connector|null $connector
 *
 * @return Connector
 */
function connector(?Connector $connector = null): Connector
{
    if ($connector === null) {
        $connector = Loop::getState(LOOP_CONNECTOR_IDENTIFIER);
        if ($connector) {
            return $connector;
        }

        $connector = new Rfc6455Connector;
    }

    Loop::setState(LOOP_CONNECTOR_IDENTIFIER, $connector);
    return $connector;
}

/**
 * @param string|Handshake          $handshake
 * @param ClientConnectContext|null $connectContext
 * @param ClientTlsContext|null     $tlsContext
 *
 * @return Promise<Connection>
 *
 * @throws \TypeError If $handshake is not a string or instance of \Amp\WebSocket\Handshake.
 * @throws ConnectionException If the connection could not be established.
 */
function connect($handshake, ClientConnectContext $connectContext = null, ClientTlsContext $tlsContext = null): Promise
{
    if (\is_string($handshake)) {
        $handshake = new Handshake($handshake);
    } elseif (!$handshake instanceof Handshake) {
        throw new \TypeError(\sprintf('Must provide an instance of %s or a URL as a string', Handshake::class));
    }

    return connector()->connect($handshake, $connectContext, $tlsContext);
}
