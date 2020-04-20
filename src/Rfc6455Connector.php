<?php

namespace Amp\Websocket\Client;

use Amp\CancellationToken;
use Amp\Deferred;
use Amp\Http;
use Amp\Http\Client\HttpClient;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Promise;
use Amp\Socket\EncryptableSocket;
use Amp\Websocket;
use Amp\Websocket\CompressionContextFactory;
use Amp\Websocket\Rfc6455Client;
use Amp\Websocket\Rfc7692CompressionFactory;
use function Amp\call;

class Rfc6455Connector implements Connector
{
    /** @var HttpClient */
    private $client;

    /** @var CompressionContextFactory */
    private $compressionFactory;

    /**
     * @param HttpClient                     $client
     * @param CompressionContextFactory|null $compressionFactory Automatically uses Rfc7692CompressionFactory if null.
     */
    public function __construct(HttpClient $client, ?CompressionContextFactory $compressionFactory = null)
    {
        $this->client = $client;
        $this->compressionFactory = $compressionFactory ?? new Rfc7692CompressionFactory;
    }

    public function connect(Handshake $handshake, ?CancellationToken $cancellationToken = null): Promise
    {
        return call(function () use ($handshake, $cancellationToken) {
            $key = Websocket\generateKey();
            $request = $this->generateRequest($handshake, $key);
            $options = $handshake->getOptions();

            $deferred = new Deferred;
            $request->setUpgradeHandler(function (EncryptableSocket $socket, Request $request, Response $response) use (
                $deferred, $key, $options
            ): void {
                if (\strtolower($response->getHeader('upgrade')) !== 'websocket') {
                    $deferred->fail(new ConnectionException('Upgrade header does not equal "websocket"', $response));
                    return;
                }

                if (!Websocket\validateAcceptForKey($response->getHeader('sec-websocket-accept'), $key)) {
                    $deferred->fail(new ConnectionException('Invalid Sec-WebSocket-Accept header', $response));
                    return;
                }

                $deferred->resolve($this->createConnection($socket, $options, $response));
            });

            $response = yield $this->client->request($request, $cancellationToken);
            \assert($response instanceof Response);

            if ($response->getStatus() !== Http\Status::SWITCHING_PROTOCOLS) {
                throw new ConnectionException(\sprintf(
                    'A %s (%d) response was not received; instead received response status: %s (%d)',
                    Http\Status::getReason(Http\Status::SWITCHING_PROTOCOLS),
                    Http\Status::SWITCHING_PROTOCOLS,
                    $response->getReason(),
                    $response->getStatus()
                ), $response);
            }

            return yield $deferred->promise();
        });
    }

    /**
     * @param Handshake $handshake
     * @param string    $key
     *
     * @return Request
     */
    private function generateRequest(Handshake $handshake, string $key): Request
    {
        $uri = $handshake->getUri();
        $uri = $uri->withScheme($uri->getScheme() === 'wss' ? 'https' : 'http');

        $request = new Request($uri, 'GET');
        $request->setHeaders($handshake->getHeaders());

        $request->setTcpConnectTimeout($handshake->getTcpConnectTimeout());
        $request->setTlsHandshakeTimeout($handshake->getTlsHandshakeTimeout());
        $request->setHeaderSizeLimit($handshake->getHeaderSizeLimit());

        if (!$request->hasHeader('origin')) {
            $origin = $uri
                ->withUserInfo('')
                ->withPath('')
                ->withQuery('');
            $request->setHeader('origin', (string) $origin);
        }

        $extensions = Http\parseFieldValueComponents($request, 'sec-websocket-extensions');

        if ($handshake->getOptions()->isCompressionEnabled()) {
            $extensions[] = [$this->compressionFactory->createRequestHeader(), ''];
        }

        if (!empty($extensions)) {
            $pairs = [];
            foreach ($extensions as [$name, $value]) {
                if ($value === '') {
                    $pairs[] = $name;
                    continue;
                }

                $pairs[] = $name . '=' . $value;
            }

            $request->setHeader('sec-websocket-extensions', \implode(', ', $pairs));
        }

        $request->setProtocolVersions(['1.1']);
        $request->setHeader('connection', 'Upgrade');
        $request->setHeader('upgrade', 'websocket');
        $request->setHeader('sec-websocket-version', '13');
        $request->setHeader('sec-websocket-key', $key);

        return $request;
    }

    /**
     * @param Response $response
     *
     * @return Websocket\CompressionContext|null
     */
    final protected function createCompressionContext(Response $response): ?Websocket\CompressionContext
    {
        $extensions = \implode(', ', $response->getHeaderArray('sec-websocket-extensions'));

        $extensions = \array_map('trim', \array_map('strtolower', \explode(',', $extensions)));

        foreach ($extensions as $extension) {
            if ($compressionContext = $this->compressionFactory->fromServerHeader($extension)) {
                return $compressionContext;
            }
        }

        return null;
    }

    protected function createConnection(EncryptableSocket $socket, Websocket\Options $options, Response $response): Connection
    {
        if ($options->isCompressionEnabled()) {
            $compressionContext = $this->createCompressionContext($response);
        }

        $client = new Rfc6455Client($socket, $options, true, $compressionContext ?? null);
        return new Rfc6455Connection($client, $response->getHeaders());
    }
}
