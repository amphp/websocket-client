<?php

namespace Amp\Websocket;

use Amp\Socket;

function connect($handshake) {
    if (is_string($handshake)) {
        $handshake = new Handshake($handshake);
    } elseif (!$handshake instanceof Handshake) {
        throw new \TypeError(__FUNCTION__ . " expected parameter 1 to be string or " . Handshake::class . ", got " . (is_object($handshake) ? get_class($handshake) : gettype($handshake)));
    }

    if ($handshake->hasCrypto()) {
        $promise = Socket\cryptoConnect($handshake->getTarget(), $handshake->getOptions());
    } else {
        $promise = Socket\connect($handshake->getTarget(), $handshake->getOptions());
    }

    return \Amp\pipe($promise, function($socket) use ($handshake) {
        return \Amp\pipe($handshake->send($socket), function($headers) use ($socket) {
            if (!$headers) {
                throw new ServerException;
            }
            return new Connection($socket, $headers);
        });
    });
}