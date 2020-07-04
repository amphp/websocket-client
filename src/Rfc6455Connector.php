<?php

namespace Amp\Websocket\Client;

use Amp\CancellationToken;
use Amp\Deferred;
use Amp\Http;
use Amp\Http\Client\HttpClient;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Http\Message;
use Amp\Promise;
use Amp\Socket\EncryptableSocket;
use Amp\Websocket;
use Amp\Websocket\CompressionContextFactory;
use Amp\Websocket\Rfc7692CompressionFactory;
use function Amp\call;

final class Rfc6455Connector implements Connector
{
    /** @var HttpClient */
    private $client;

    /** @var ConnectionFactory */
    private $connectionFactory;

    /** @var CompressionContextFactory */
    private $compressionFactory;

    /**
     * @param HttpClient                     $client
     * @param ConnectionFactory|null         $connectionFactory  Uses {@see Rfc6455ConnectionFactory} if null.
     * @param CompressionContextFactory|null $compressionFactory Uses {@see Rfc7692CompressionFactory} if null.
     */
    public function __construct(
        HttpClient $client,
        ?ConnectionFactory $connectionFactory = null,
        ?CompressionContextFactory $compressionFactory = null
    ) {
        $this->client = $client;
        $this->connectionFactory = $connectionFactory ?? new Rfc6455ConnectionFactory;
        $this->compressionFactory = $compressionFactory ?? new Rfc7692CompressionFactory;
    }

    public function connect(Handshake $handshake, ?CancellationToken $cancellationToken = null): Promise
    {
        return call(function () use ($handshake, $cancellationToken) {
            $key = Websocket\generateKey();
            $request = $this->generateRequest($handshake, $key);
            $options = $handshake->getOptions();

            $deferred = new Deferred;
            $connectionFactory = $this->connectionFactory;
            $compressionFactory = $this->compressionFactory;
            $request->setUpgradeHandler(static function (EncryptableSocket $socket, Request $request, Response $response) use (
                $connectionFactory, $compressionFactory, $deferred, $key, $options
            ): void {
                if (\strtolower((string) $response->getHeader('upgrade')) !== 'websocket') {
                    $deferred->fail(new ConnectionException('Upgrade header does not equal "websocket"', $response));
                    return;
                }

                if (!Websocket\validateAcceptForKey((string) $response->getHeader('sec-websocket-accept'), $key)) {
                    $deferred->fail(new ConnectionException('Invalid Sec-WebSocket-Accept header', $response));
                    return;
                }

                $extensions = self::splitField($response, 'sec-websocket-extensions');

                foreach ($extensions as $extension) {
                    if ($compressionContext = $compressionFactory->fromServerHeader($extension)) {
                        break;
                    }
                }

                $deferred->resolve($connectionFactory->createConnection($response, $socket, $options, $compressionContext ?? null));
            });

            /** @var Response $response */
            $response = yield $this->client->request($request, $cancellationToken);

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

        $extensions = self::splitField($request, 'sec-websocket-extensions');

        if ($handshake->getOptions()->isCompressionEnabled() && \extension_loaded('zlib')) {
            $extensions[] = $this->compressionFactory->createRequestHeader();
        }

        if (!empty($extensions)) {
            $request->setHeader('sec-websocket-extensions', \implode(', ', $extensions));
        }

        $request->setProtocolVersions(['1.1']);
        $request->setHeader('connection', 'Upgrade');
        $request->setHeader('upgrade', 'websocket');
        $request->setHeader('sec-websocket-version', '13');
        $request->setHeader('sec-websocket-key', $key);

        return $request;
    }

    private static function splitField(Message $message, string $headerName): array
    {
        $header = \implode(', ', $message->getHeaderArray($headerName));

        if ($header === '') {
            return [];
        }

        \preg_match_all('(([^",]+(?:"((?:[^\\\\"]|\\\\.)*)"|([^,]*))?),?\s*)', $header, $matches, \PREG_SET_ORDER);

        $values = [];

        foreach ($matches as $match) {
            // decode escaped characters
            $values[] = \preg_replace('(\\\\(.))', '\1', \trim($match[1]));
        }

        return $values;
    }
}
