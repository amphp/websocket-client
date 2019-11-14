<?php

namespace Amp\Websocket\Client;

use Amp\CancellationToken;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\HttpException;
use Amp\Loop;
use Amp\Promise;
use Psr\Http\Message\UriInterface as PsrUri;

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

        $connector = new Rfc6455Connector(HttpClientBuilder::buildDefault());
    }

    Loop::setState(LOOP_CONNECTOR_IDENTIFIER, $connector);
    return $connector;
}

/**
 * @param string|PsrUri|Handshake $handshake
 * @param CancellationToken|null  $cancellationToken
 *
 * @return Promise<Connection>
 *
 * @throws \TypeError If $handshake is not a string, instance of WebsocketUri, or instance of Handshake.
 * @throws HttpException Thrown if the request fails.
 * @throws ConnectionException If the response received is invalid or is not a switching protocols (101) response.
 */
function connect($handshake, ?CancellationToken $cancellationToken = null): Promise
{
    if (!$handshake instanceof Handshake) {
        $handshake = new Handshake($handshake);
    }

    return connector()->connect($handshake, $cancellationToken);
}
