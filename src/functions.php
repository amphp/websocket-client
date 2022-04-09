<?php

namespace Amp\Websocket\Client;

use Amp\Cancellation;
use Amp\Http\Client\HttpException;
use Psr\Http\Message\UriInterface as PsrUri;
use Revolt\EventLoop;

/**
 * Set or access the global websocket Connector instance.
 */
function connector(?Connector $connector = null): Connector
{
    static $map;
    $map ??= new \WeakMap();
    $driver = EventLoop::getDriver();

    if ($connector) {
        return $map[$driver] = $connector;
    }

    return $map[$driver] ??= new Rfc6455Connector();
}

/**
 * @throws ConnectionException If the response received is invalid or is not a switching protocols (101) response.
 * @throws HttpException Thrown if the request fails.
 */
function connect(
    Handshake|PsrUri|string $handshake,
    ?Cancellation $cancellation = null,
): Connection {
    if (!$handshake instanceof Handshake) {
        $handshake = new Handshake($handshake);
    }

    return connector()->connect($handshake, $cancellation);
}
