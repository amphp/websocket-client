<?php

namespace Amp\Websocket;

use Amp\Promise;
use Amp\Socket;
use Amp\Socket\ClientConnectContext;
use Amp\Socket\ClientTlsContext;
use function Amp\call;

/**
 * @param string|\Amp\WebSocket\Handshake $handshake
 * @param \Amp\Socket\ClientConnectContext|null $connectContext
 * @param \Amp\Socket\ClientTlsContext|null $tlsContext
 *
 * @return \Amp\Promise<\Amp\WebSocket\Connection>
 *
 * @throws \TypeError If $handshake is not a string or instance of \Amp\WebSocket\Handshake.
 */
function connect($handshake, ClientConnectContext $connectContext = null, ClientTlsContext $tlsContext = null): Promise {
    if (\is_string($handshake)) {
        $handshake = new Handshake($handshake);
    } elseif (!$handshake instanceof Handshake) {
        throw new \TypeError(\sprintf("Must provide an instance of %s or a URL as a string", Handshake::class));
    }

    return call(function () use ($handshake, $connectContext, $tlsContext) {
        if ($handshake->hasCrypto()) {
            $socket = yield Socket\cryptoConnect($handshake->getTarget(), $connectContext, $tlsContext);
        } else {
            $socket = yield Socket\connect($handshake->getTarget(), $connectContext);
        }

        return yield $handshake->send($socket);
    });
}
