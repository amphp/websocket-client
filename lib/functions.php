<?php

namespace Amp\Websocket;

use Amp\Socket;

function connect($handshake, array $options = []) {
    if (is_string($handshake)) {
        $handshake = new Handshake($handshake);
    } elseif (!$handshake instanceof Handshake) {
        throw new \TypeError(__FUNCTION__ . " expected parameter 1 to be string or " . Handshake::class . ", got " . (is_object($handshake) ? get_class($handshake) : gettype($handshake)));
    }

    if ($handshake->hasCrypto()) {
        $promise = Socket\cryptoConnect($handshake->getTarget(), $options);
    } else {
        $promise = Socket\connect($handshake->getTarget(), $options);
    }

    return \Amp\pipe($promise, [$handshake, 'send']);
}