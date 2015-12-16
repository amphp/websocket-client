<?php

namespace Amp;

function websocket(Websocket $ws, Websocket\Handshake $handshake) {
	if ($handshake->hasCrypto()) {
		$promise = Socket\cryptoConnect($handshake->getTarget(), $handshake->getOptions());
	} else {
		$promise = Socket\connect($handshake->getTarget(), $handshake->getOptions());
	}

	return pipe($promise, function($socket) use ($handshake, $ws) {
		return pipe($handshake->send($socket), function($headers) use ($socket, $ws) {
			if (!$headers) {
				throw new Websocket\ClientException;
			}
			return new Websocket\Rfc6455Endpoint($socket, $ws, $headers);
		});
	});
}