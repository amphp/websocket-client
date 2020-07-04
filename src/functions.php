<?php

namespace Amp\Websocket\Client;

use Amp\CancellationToken;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\HttpException;
use Amp\Promise;
use Psr\Http\Message\UriInterface as PsrUri;

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

    return (new Rfc6455Connector(HttpClientBuilder::buildDefault()))->connect($handshake, $cancellationToken);
}
