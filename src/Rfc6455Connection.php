<?php

namespace Amp\Websocket\Client;

use Amp\ByteStream\InputStream;
use Amp\Promise;
use Amp\Websocket\Code;
use Amp\Websocket\Rfc6455Client;

final class Rfc6455Connection implements Connection
{
    /** @var Rfc6455Client */
    private $client;

    /** @var string[][] */
    private $headers;

    public function __construct(Rfc6455Client $client, array $headers)
    {
        $this->client = $client;
        $this->headers = $headers;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeaderArray(string $name): array
    {
        return $this->headers[\strtolower($name)] ?? [];
    }

    public function getHeader(string $name): ?string
    {
        return $this->headers[\strtolower($name)][0] ?? null;
    }

    public function receive(): Promise
    {
        return $this->client->receive();
    }

    public function getId(): int
    {
        return $this->client->getId();
    }

    public function isConnected(): bool
    {
        return $this->client->isConnected();
    }

    public function getLocalAddress(): string
    {
        return $this->client->getLocalAddress();
    }

    public function getRemoteAddress(): string
    {
        return $this->client->getRemoteAddress();
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

    public function getInfo(): array
    {
        return $this->client->getInfo();
    }

    public function close(int $code = Code::NORMAL_CLOSE, string $reason = ''): Promise
    {
        return $this->client->close($code, $reason);
    }
}
