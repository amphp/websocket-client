<?php

namespace Amp\Websocket\Client;

use Amp\CancellationToken;
use Amp\Loop;
use Amp\Promise;
use Amp\Socket\ConnectContext;

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
 * @param string|WebsocketUri|Handshake $handshake
 * @param ConnectContext|null           $connectContext
 * @param CancellationToken|null        $cancellationToken
 *
 * @return Promise<Connection>
 *
 * @throws \TypeError If $handshake is not a string, instance of WebsocketUri, or instance of Handshake.
 * @throws ConnectionException If the connection could not be established.
 */
function connect(
    $handshake,
    ?ConnectContext $connectContext = null,
    ?CancellationToken $cancellationToken = null
): Promise {
    if (\is_string($handshake) || $handshake instanceof WebsocketUri) {
        $handshake = new Handshake($handshake);
    } elseif (!$handshake instanceof Handshake) {
        throw new \TypeError(\sprintf(
            'Must provide an instance of %s or a websocket URL as a string or instance of %s',
            Handshake::class,
            WebsocketUri::class
        ));
    }

    return connector()->connect($handshake, $connectContext, $cancellationToken);
}
