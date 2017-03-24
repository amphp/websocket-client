<?php

namespace Amp\Websocket;

use Amp\{ Promise, Socket, UnionTypeError };

/**
 * @param string|\Amp\WebSocket\Handshake $handshake
 * @param array $options Connect options. See \Amp\Socket\connect() and \Amp\Socket\cryptoConnect().
 *
 * @return \Amp\Promise<\Amp\WebSocket\Connection>
 *
 * @throws \Amp\UnionTypeError If $handshake is not a string or instance of \Amp\WebSocket\Handshake.
 */
function connect($handshake, array $options = []): Promise {
    if (\is_string($handshake)) {
        $handshake = new Handshake($handshake);
    } elseif (!$handshake instanceof Handshake) {
        throw new UnionTypeError(["string", Handshake::class], $handshake);
    }

    if ($handshake->hasCrypto()) {
        $promise = Socket\rawCryptoConnect($handshake->getTarget(), $options);
    } else {
        $promise = Socket\rawConnect($handshake->getTarget(), $options);
    }

    return Promise\pipe($promise, [$handshake, 'send']);
}