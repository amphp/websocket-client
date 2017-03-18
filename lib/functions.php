<?php

namespace Amp\Websocket;

use Amp\{ Promise, Socket, UnionTypeError };

function connect($handshake, array $options = []) {
    if (is_string($handshake)) {
        $handshake = new Handshake($handshake);
    } elseif (!$handshake instanceof Handshake) {
        throw new UnionTypeError(["string", Handshake::class], $handshake);
    }

    if ($handshake->hasCrypto()) {
        $promise = Socket\cryptoConnect($handshake->getTarget(), $options);
    } else {
        $promise = Socket\connect($handshake->getTarget(), $options);
    }

    return Promise\pipe($promise, [$handshake, 'send']);
}