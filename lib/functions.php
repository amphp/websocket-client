<?php

namespace Amp;

function websocket($handshake) {
    if (is_string($handshake)) {
        $handshake = new Websocket\Handshake($handshake);
    } elseif (!$handshake instanceof Websocket\Handshake) {
        throw new \TypeError(__FUNCTION__ . " expected parameter 1 to be string or " . Websocket\Handshake::class . ", got " . (is_object($handshake) ? get_class($handshake) : gettype($handshake)));
    }

    if ($handshake->hasCrypto()) {
        $promise = Socket\cryptoConnect($handshake->getTarget(), $handshake->getOptions());
    } else {
        $promise = Socket\connect($handshake->getTarget(), $handshake->getOptions());
    }

    return pipe($promise, function($socket) use ($handshake) {
        return pipe($handshake->send($socket), function($headers) use ($socket) {
            if (!$headers) {
                throw new Websocket\ServerException;
            }
            return new Websocket\Connection($socket, $headers);
        });
    });
}