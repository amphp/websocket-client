<?php

namespace Amp\Websocket\Client;

use Amp\ByteStream\InputStream;
use Amp\Http\Message;
use Amp\Promise;
use Amp\Websocket\ClientMetadata;
use Amp\Websocket\Code;
use Amp\Websocket\Options;
use Amp\Websocket\Rfc6455Client;

final class Rfc6455Connection implements Connection
{
    /** @var Rfc6455Client */
    private $client;

    /** @var Message */
    private $headers;

    public function __construct(Rfc6455Client $client, array $headers)
    {
        $this->client = $client;
        $this->headers = new class($headers) extends Message {
            public function __construct(array $headers)
            {
                $this->setHeaders($headers);
            }
        };
    }

    public function getHeaders(): array
    {
        return $this->headers->getHeaders();
    }

    public function getHeaderArray(string $name): array
    {
        return $this->headers->getHeaderArray($name);
    }

    public function getHeader(string $name): ?string
    {
        return $this->headers->getHeader($name);
    }

    public function hasHeader(string $name): bool
    {
        return $this->headers->hasHeader($name);
    }

    public function receive(): Promise
    {
        return $this->client->receive();
    }

    public function getId(): string
    {
        return $this->client->getId();
    }

    public function getOptions(): Options
    {
        return $this->client->getOptions();
    }

    public function isConnected(): bool
    {
        return $this->client->isConnected();
    }

    public function getLocalAddress(): string
    {
        return $this->client->getLocalAddress();
    }

    public function getLocalPort(): ?int
    {
        return $this->client->getLocalPort();
    }

    public function getRemoteAddress(): string
    {
        return $this->client->getRemoteAddress();
    }

    public function getRemotePort(): ?int
    {
        return $this->client->getRemotePort();
    }

    public function isEncrypted(): bool
    {
        return $this->client->isEncrypted();
    }

    public function getCryptoContext(): array
    {
        return $this->client->getCryptoContext();
    }

    public function didPeerInitiateClose(): bool
    {
        return $this->client->didPeerInitiateClose();
    }

    public function getUnansweredPingCount(): int
    {
        return $this->client->getUnansweredPingCount();
    }

    public function getCloseCode(): int
    {
        return $this->client->getCloseCode();
    }

    public function getCloseReason(): string
    {
        return $this->client->getCloseReason();
    }

    public function send(string $data): Promise
    {
        return $this->client->send($data);
    }

    public function sendBinary(string $data): Promise
    {
        return $this->client->sendBinary($data);
    }

    public function stream(InputStream $stream): Promise
    {
        return $this->client->stream($stream);
    }

    public function streamBinary(InputStream $stream): Promise
    {
        return $this->client->streamBinary($stream);
    }

    public function ping(): Promise
    {
        return $this->client->ping();
    }

    public function getInfo(): ClientMetadata
    {
        return $this->client->getInfo();
    }

    public function close(int $code = Code::NORMAL_CLOSE, string $reason = ''): Promise
    {
        return $this->client->close($code, $reason);
    }

    public function onClose(callable $onClose): void
    {
        $this->client->onClose($onClose);
    }
}
