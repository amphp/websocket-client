<?php

namespace Amp\Websocket;

use Amp\Promise;
use Amp\Socket;
use Amp\Socket\ClientConnectContext;
use Amp\Socket\ClientSocket;
use Amp\Socket\ClientTlsContext;
use function Amp\call;

/**
 * @param string|\Amp\Websocket\Handshake       $handshake
 * @param \Amp\Socket\ClientConnectContext|null $connectContext
 * @param \Amp\Socket\ClientTlsContext|null     $tlsContext
 * @param array                                 $options
 *
 * @return \Amp\Promise<\Amp\WebSocket\Connection>
 *
 * @throws \TypeError If $handshake is not a string or instance of \Amp\WebSocket\Handshake.
 */
function connect($handshake, ClientConnectContext $connectContext = null, ClientTlsContext $tlsContext = null, array $options = []): Promise {
    if (\is_string($handshake)) {
        $handshake = new Handshake($handshake);
    } elseif (!$handshake instanceof Handshake) {
        throw new \TypeError(\sprintf('Must provide an instance of %s or a URL as a string', Handshake::class));
    }

    return call(function () use ($handshake, $connectContext, $tlsContext, $options) {
        if ($handshake->isEncrypted()) {
            /** @var ClientSocket $socket */
            $socket = yield Socket\cryptoConnect($handshake->getRemoteAddress(), $connectContext, $tlsContext);
        } else {
            /** @var ClientSocket $socket */
            $socket = yield Socket\connect($handshake->getRemoteAddress(), $connectContext);
        }

        yield $socket->write($handshake->getRawRequest());

        $buffer = '';

        while (($chunk = yield $socket->read()) !== null) {
            $buffer .= $chunk;

            if ($position = \strpos($buffer, "\r\n\r\n")) {
                $headerBuffer = \substr($buffer, 0, $position + 4);
                $buffer = \substr($buffer, $position + 4);

                $startLine = \substr($headerBuffer, 0, \strpos($headerBuffer, "\r\n"));
                if (!\preg_match("(^HTTP/1.1[\x20\x09]101[\x20\x09]*[^\x01-\x08\x10-\x19]*$)", $startLine)) {
                    throw new WebSocketException('Did not receive switching protocols response: ' . $startLine);
                }

                \preg_match_all("(
                    (?P<field>[^()<>@,;:\\\"/[\\]?={}\x01-\x20\x7F]+):[\x20\x09]*
                    (?P<value>[^\x01-\x08\x0A-\x1F\x7F]*)\x0D?[\x20\x09]*\r?\n
                )x", $headerBuffer, $responseHeaders);

                $headers = [];

                /** @var array[] $responseHeaders */
                foreach ($responseHeaders['field'] as $idx => $field) {
                    $headers[\strtolower($field)][] = $responseHeaders['value'][$idx];
                }

                // TODO: validate headers...

                return new Rfc6455Endpoint($socket, $buffer, $options);
            }
        }

        throw new WebSocketException('Failed to read response from server');
    });
}
