<?php

namespace Amp\Websocket\Client;

use Amp\ByteStream\ReadableStream;
use Amp\Future;
use Amp\Http\Client\Response;
use Amp\Socket\SocketAddress;
use Amp\Socket\TlsInfo;
use Amp\Websocket\ClientMetadata;
use Amp\Websocket\Code;
use Amp\Websocket\Message;
use Amp\Websocket\Options;
use Amp\Websocket\Rfc6455Client;

final class Rfc6455Connection implements Connection
{
    private Rfc6455Client $client;

    private Response $response;

    public function __construct(Rfc6455Client $client, Response $response)
    {
        $this->client = $client;
        $this->response = $response;
    }

    public function getResponse(): Response
    {
        return $this->response;
    }

    public function receive(): ?Message
    {
        return $this->client->receive();
    }

    public function getId(): int
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

    public function getLocalAddress(): SocketAddress
    {
        return $this->client->getLocalAddress();
    }

    public function getRemoteAddress(): SocketAddress
    {
        return $this->client->getRemoteAddress();
    }

    public function getTlsInfo(): ?TlsInfo
    {
        return $this->client->getTlsInfo();
    }

    public function isClosedByPeer(): bool
    {
        return $this->client->isClosedByPeer();
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

    public function send(string $data): void
    {
        $this->client->send($data);
    }

    public function sendBinary(string $data): void
    {
        $this->client->sendBinary($data);
    }

    public function stream(ReadableStream $stream): Future
    {
        return $this->client->stream($stream);
    }

    public function streamBinary(ReadableStream $stream): Future
    {
        return $this->client->streamBinary($stream);
    }

    public function ping(): void
    {
        $this->client->ping();
    }

    public function getInfo(): ClientMetadata
    {
        return $this->client->getInfo();
    }

    public function close(int $code = Code::NORMAL_CLOSE, string $reason = ''): array
    {
        return $this->client->close($code, $reason);
    }

    public function onClose(callable $onClose): void
    {
        $this->client->onClose($onClose);
    }
}
