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
use Amp\Websocket\CompressionContextFactory;
use Amp\Websocket\Rfc6455Client;
use Amp\Websocket\Rfc7692CompressionFactory;
use function Amp\call;

final class Rfc6455Connector implements Connector
{
    /** @var CompressionContextFactory */
    private $compressionFactory;

    /**
     * @param CompressionContextFactory|null $compressionFactory Automatically uses Rfc7692CompressionFactory if null.
     */
    public function __construct(?CompressionContextFactory $compressionFactory = null)
    {
        $this->compressionFactory = $compressionFactory ?? new Rfc7692CompressionFactory;
    }

    public function connect(
        Handshake $handshake,
        ?ClientConnectContext $connectContext = null,
        ?ClientTlsContext $tlsContext = null
    ): Promise {
        return call(function () use ($handshake, $connectContext, $tlsContext) {
            try {
                $uri = $handshake->getUri();
                $isEncrypted = $uri->getScheme() === 'wss';
                $defaultPort = $isEncrypted ? 443 : 80;
                $authority = $uri->getHost() . ':' . ($uri->getPort() ?? $defaultPort);

                if ($isEncrypted) {
                    $socket = yield Socket\cryptoConnect($authority, $connectContext, $tlsContext);
                } else {
                    $socket = yield Socket\connect($authority, $connectContext);
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

        $headers = $handshake->getHeaders();

        $headers['host'] = [$uri->getAuthority()];
        $headers['connection'] = ['Upgrade'];
        $headers['upgrade'] = ['websocket'];
        $headers['sec-websocket-version'] = ['13'];
        $headers['sec-websocket-key'] = [$key];

        if ($handshake->getOptions()->isCompressionEnabled()) {
            $headers['sec-websocket-extensions'] = [$this->compressionFactory->createRequestHeader()];
        }

        if (($path = $uri->getPath()) === '') {
            $path = '/';
        }

        if (($query = $uri->getQuery()) !== '') {
            $path .= '?' . $query;
        }

        return \sprintf("GET %s HTTP/1.1\r\n%s\r\n", $path, Rfc7230::formatHeaders($headers));
    }

    private function handleResponse(string $headerBuffer, string $key): array
    {
        if (\substr($headerBuffer, -4) !== "\r\n\r\n") {
            throw new ConnectionException('Invalid header provided');
        }

        $position = \strpos($headerBuffer, "\r\n");
        $startLine = \substr($headerBuffer, 0, $position);

        if (!\preg_match("/^HTTP\/(1\.[01]) (\d{3}) ([^\x01-\x08\x10-\x19]*)$/i", $startLine, $matches)) {
            throw new ConnectionException('Invalid response start line: ' . $startLine);
        }

        $version = $matches[1];
        $status = (int) $matches[2];
        $reason = $matches[3];

        if ($version !== '1.1' || $status !== Status::SWITCHING_PROTOCOLS) {
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
                if ($compressionContext = $this->compressionFactory->fromServerHeader($extension)) {
                    break;
                }
            }
        }

        $client = new Rfc6455Client($socket, $options, true, $compressionContext, $buffer);
        return new Rfc6455Connection($client, $headers);
    }
}
