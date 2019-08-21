<?php

namespace Amp\Websocket\Client;

use Amp\CancellationToken;
use Amp\CancelledException;
use Amp\Deferred;
use Amp\Http\Rfc7230;
use Amp\Http\Status;
use Amp\NullCancellationToken;
use Amp\Promise;
use Amp\Socket;
use Amp\Socket\ConnectContext;
use Amp\Websocket;
use Amp\Websocket\CompressionContextFactory;
use Amp\Websocket\Rfc6455Client;
use Amp\Websocket\Rfc7692CompressionFactory;
use League\Uri;
use function Amp\asyncCall;
use function Amp\call;

class Rfc6455Connector implements Connector
{
    /** @var CompressionContextFactory */
    private $compressionFactory;

    /** @var Socket\Connector */
    private $connector;

    /**
     * @param CompressionContextFactory|null $compressionFactory Automatically uses Rfc7692CompressionFactory if null.
     * @param Socket\Connector|null $connector Socket connector. Global connector used if null.
     */
    public function __construct(?CompressionContextFactory $compressionFactory = null, ?Socket\Connector $connector = null)
    {
        $this->compressionFactory = $compressionFactory ?? new Rfc7692CompressionFactory;
        $this->connector = $connector ?? Socket\connector();
    }

    public function connect(
        Handshake $handshake,
        ?ConnectContext $connectContext = null,
        ?CancellationToken $cancellationToken = null
    ): Promise {
        $cancellationToken = $cancellationToken ?? new NullCancellationToken;

        return call(function () use ($handshake, $connectContext, $cancellationToken) {
            try {
                $uri = $handshake->getUri();
                $isEncrypted = $uri->getScheme() === 'wss';
                $defaultPort = $isEncrypted ? 443 : 80;
                $authority = $uri->getHost() . ':' . ($uri->getPort() ?? $defaultPort);

                $socket = yield $this->connector->connect($authority, $connectContext, $cancellationToken);

                \assert($socket instanceof Socket\EncryptableSocket);

                if ($isEncrypted) {
                    yield $socket->setupTls();
                }
            } catch (CancelledException $exception) {
                throw $exception;
            } catch (\Throwable $exception) {
                throw new ConnectionException('Connecting to the websocket failed', 0, $exception);
            }

            $deferred = new Deferred;
            $id = $cancellationToken->subscribe([$deferred, 'fail']);

            asyncCall(function () use ($socket, $handshake, $deferred) {
                try {
                    $key = Websocket\generateKey();
                    yield $socket->write($this->generateRequest($handshake, $key));

                    $buffer = '';

                    while (($chunk = yield $socket->read()) !== null) {
                        $buffer .= $chunk;

                        if ($position = \strpos($buffer, "\r\n\r\n")) {
                            $headerBuffer = \substr($buffer, 0, $position + 4);
                            $buffer = \substr($buffer, $position + 4);

                            $headers = $this->handleResponse($headerBuffer, $key);

                            if ($buffer !== '') {
                                $socket = new ClientSocket($socket, $buffer);
                            }

                            $deferred->resolve($this->createConnection($socket, $handshake->getOptions(), $headers));
                            return;
                        }
                    }
                } catch (ConnectionException $exception) {
                    $deferred->fail($exception);
                } catch (\Throwable $exception) {
                    $deferred->fail(new ConnectionException('Performing the websocket handshake failed', 0, $exception));
                }
            });

            try {
                return yield $deferred->promise();
            } catch (\Throwable $exception) {
                $socket->close(); // Close socket in case operation did not fail but was cancelled.
                throw $exception;
            } finally {
                $cancellationToken->unsubscribe($id);
            }
        });
    }

    private function generateRequest(Handshake $handshake, string $key): string
    {
        $uri = $handshake->getUri();

        if (!$handshake->hasHeader('origin')) {
            $origin = Uri\Http::createFromComponents([
                'scheme' => $uri->getScheme() === 'wss' ? 'https' : 'http',
                'host' => $uri->getHost(),
                'port' => $uri->getPort(),
            ]);
            $handshake = $handshake->withHeader('origin', (string) $origin);
        }

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

    final protected function createCompressionContext(array $headers): ?Websocket\CompressionContext
    {
        $extensions = $headers['sec-websocket-extensions'][0] ?? '';

        $extensions = \array_map('trim', \explode(',', $extensions));

        foreach ($extensions as $extension) {
            if ($compressionContext = $this->compressionFactory->fromServerHeader($extension)) {
                return $compressionContext;
            }
        }

        return null;
    }

    protected function createConnection(Socket\EncryptableSocket $socket, Websocket\Options $options, array $headers): Connection
    {
        if ($options->isCompressionEnabled()) {
            $compressionContext = $this->createCompressionContext($headers);
        }

        $client = new Rfc6455Client($socket, $options, true, $compressionContext ?? null);
        return new Rfc6455Connection($client, $headers);
    }
}
