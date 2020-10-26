<?php

namespace Amp\Websocket\Client;

use Amp\CancellationToken;
use Amp\Http\Client\Connection\DefaultConnectionFactory;
use Amp\Http\Client\Connection\UnlimitedConnectionPool;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\HttpException;
use Amp\Socket\ConnectContext;
use Psr\Http\Message\UriInterface as PsrUri;

/**
 * @param string|PsrUri|Handshake $handshake
 * @param ConnectContext|null     $connectContext
 * @param CancellationToken|null  $cancellationToken
 *
 * @return Connection
 *
 * @throws ConnectionException If the response received is invalid or is not a switching protocols (101) response.
 * @throws HttpException Thrown if the request fails.
 */
function connect(
    Handshake|PsrUri|string $handshake,
    ?ConnectContext $connectContext = null,
    ?CancellationToken $cancellationToken = null
): Connection {
    if (!$handshake instanceof Handshake) {
        $handshake = new Handshake($handshake);
    }

    if ($connectContext === null) {
        $connectContext = (new ConnectContext)->withTcpNoDelay();
    }

    $httpClient = (new HttpClientBuilder)
        ->usingPool(new UnlimitedConnectionPool(new DefaultConnectionFactory(null, $connectContext)))
        ->build();

    return (new Rfc6455Connector($httpClient))->connect($handshake, $cancellationToken);
}
