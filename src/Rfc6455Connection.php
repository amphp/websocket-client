<?php

namespace Amp\Websocket\Client;

use Amp\ByteStream\ReadableStream;
use Amp\Cancellation;
use Amp\Http\Client\Response;
use Amp\Socket\SocketAddress;
use Amp\Socket\TlsInfo;
use Amp\Websocket\CloseCode;
use Amp\Websocket\Rfc6455Client;
use Amp\Websocket\WebsocketClientMetadata;
use Amp\Websocket\WebsocketMessage;

final class Rfc6455Connection implements WebsocketConnection
{
    public const DEFAULT_MESSAGE_SIZE_LIMIT = (2 ** 20) * 10; // 10MB
    public const DEFAULT_FRAME_SIZE_LIMIT = (2 ** 20) * 10; // 10MB

    public function __construct(
        private readonly Rfc6455Client $client,
        private readonly Response $handshakeResponse,
    ) {
    }

    public function getHandshakeResponse(): Response
    {
        return $this->handshakeResponse;
    }

    public function receive(?Cancellation $cancellation = null): ?WebsocketMessage
    {
        return $this->client->receive($cancellation);
    }

    public function getId(): int
    {
        return $this->client->getId();
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

    public function stream(ReadableStream $stream): void
    {
        $this->client->stream($stream);
    }

    public function streamBinary(ReadableStream $stream): void
    {
        $this->client->streamBinary($stream);
    }

    public function ping(): void
    {
        $this->client->ping();
    }

    public function getInfo(): WebsocketClientMetadata
    {
        return $this->client->getInfo();
    }

    public function isClosed(): bool
    {
        return $this->client->isClosed();
    }

    public function close(int $code = CloseCode::NORMAL_CLOSE, string $reason = ''): void
    {
        $this->client->close($code, $reason);
    }

    public function onClose(\Closure $onClose): void
    {
        $this->client->onClose($onClose);
    }
}
