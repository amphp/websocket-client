<?php

namespace Amp\Websocket\Client;

use Amp\Http\Rfc7230;
use Amp\Http\Status;
use Amp\Promise;
use Amp\Socket;
use Amp\Socket\ClientConnectContext;
use Amp\Socket\ClientSocket;
use Amp\Socket\ClientTlsContext;
use Amp\Websocket;
use Amp\Websocket\Rfc6455Client;
use Amp\Websocket\Rfc7692Compression;
use function Amp\call;

final class Rfc6455Connector implements Connector
{
    public function connect(
        Handshake $handshake,
        ?ClientConnectContext $connectContext = null,
        ?ClientTlsContext $tlsContext = null
    ): Promise {
        return call(function () use ($handshake, $connectContext, $tlsContext) {
            try {
                $uri = $handshake->getUri();

                if ($uri->getScheme() === 'wss') {
                    $socket = yield Socket\cryptoConnect($uri->getAuthority(), $connectContext, $tlsContext);
                } else {
                    $socket = yield Socket\connect($uri->getAuthority(), $connectContext);
                }

                \assert($socket instanceof ClientSocket);

                $key = Websocket\generateKey();
                yield $socket->write($this->generateRequest($handshake, $key));

                $buffer = '';

                while (($chunk = yield $socket->read()) !== null) {
                    $buffer .= $chunk;

                    if ($position = \strpos($buffer, "\r\n\r\n")) {
                        $headerBuffer = \substr($buffer, 0, $position + 4);
                        $buffer = \substr($buffer, $position + 4);

                        $headers = $this->handleResponse($headerBuffer, $key);

                        return $this->createConnection($handshake, $socket, $headers, $buffer);
                    }
                }
            } catch (ConnectionException $exception) {
                throw $exception;
            } catch (\Throwable $exception) {
                throw new ConnectionException('Websocket connection attempt failed', 0, $exception);
            }

            throw new ConnectionException('Connection closed unexpectedly');
        });
    }

    private function generateRequest(Handshake $handshake, string $key): string
    {
        $uri = $handshake->getUri();

        $headers = [];

        $headers['connection'] = ['Upgrade'];
        $headers['upgrade'] = ['websocket'];
        $headers['sec-websocket-version'] = ['13'];
        $headers['sec-websocket-key'] = [$key];
        $headers['host'] = [$uri->getAuthority()];

        if ($handshake->getOptions()->isCompressionEnabled()) {
            $headers['sec-websocket-extensions'] = [Rfc7692Compression::createRequestHeader()];
        }

        $headers = \array_merge($headers, $handshake->getHeaders());

        $headers = Rfc7230::formatHeaders($headers);

        if (($path = $uri->getPath()) === '') {
            $path = '/';
        }

        if (($query = $uri->getQuery()) !== '') {
            $path .= '?' . $query;
        }

        return \sprintf("GET %s HTTP/1.1\r\n%s\r\n", $path, $headers);
    }

    private function handleResponse(string $headerBuffer, string $key): array
    {
        if (\substr($headerBuffer, -4) !== "\r\n\r\n") {
            throw new ConnectionException('Invalid header provided');
        }

        $position = \strpos($headerBuffer, "\r\n");
        $startLine = \substr($headerBuffer, 0, $position);

        if (!\preg_match("(^HTTP/1.1 (\d{3}) ([^\x01-\x08\x10-\x19]*)$)", $startLine, $matches)) {
            throw new ConnectionException('Invalid response start line: ' . $startLine);
        }

        $status = (int) $matches[1];
        $reason = $matches[2];

        if ($status !== Status::SWITCHING_PROTOCOLS) {
            throw new ConnectionException(
                \sprintf('Did not receive switching protocols response: %d %s', $status, $reason),
                $status
            );
        }

        $headerBuffer = \substr($headerBuffer, $position + 2, -2);

        $headers = Rfc7230::parseHeaders($headerBuffer);

        $upgrade = $headers['upgrade'][0] ?? '';
        if (\strtolower($upgrade) !== 'websocket') {
            throw new ConnectionException('Missing "Upgrade: websocket" header');
        }

        $connection = $headers['connection'][0] ?? '';
        if (!\in_array('upgrade', \array_map('trim', \array_map('strtolower', \explode(',', $connection))), true)) {
            throw new ConnectionException('Missing "Connection: upgrade" header');
        }

        $secWebsocketAccept = $headers['sec-websocket-accept'][0] ?? '';
        if (!Websocket\validateAcceptForKey($secWebsocketAccept, $key)) {
            throw new ConnectionException('Invalid "Sec-WebSocket-Accept" header');
        }

        return $headers;
    }

    private function createConnection(Handshake $handshake, ClientSocket $socket, array $headers, string $buffer): Connection
    {
        $options = $handshake->getOptions();

        $compressionContext = null;
        if ($options->isCompressionEnabled()) {
            $extensions = $headers['sec-websocket-extensions'][0] ?? '';

            $extensions = \array_map('trim', \explode(',', $extensions));

            foreach ($extensions as $extension) {
                if ($compressionContext = Rfc7692Compression::fromServerHeader($extension)) {
                    break;
                }
            }
        }

        $client = new Rfc6455Client($socket, $options, true, $compressionContext, $buffer);
        return new Rfc6455Connection($client, $headers);
    }
}
