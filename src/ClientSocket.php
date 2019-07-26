<?php

namespace Amp\Websocket\Client;

use Amp\CancellationToken;
use Amp\Promise;
use Amp\Socket\EncryptableSocket;
use Amp\Socket\Socket;
use Amp\Socket\SocketAddress;
use Amp\Socket\TlsInfo;
use Amp\Success;

final class ClientSocket implements EncryptableSocket
{
    /** @var Socket */
    private $socket;

    /** @var string|null */
    private $buffer;

    public function __construct(EncryptableSocket $socket, string $buffer)
    {
        $this->socket = $socket;
        $this->buffer = $buffer !== '' ? $buffer : null;
    }

    public function read(): Promise
    {
        if ($this->buffer !== null) {
            $buffer = $this->buffer;
            $this->buffer = null;
            return new Success($buffer);
        }

        return $this->socket->read();
    }

    public function write(string $data): Promise
    {
        return $this->socket->write($data);
    }

    public function end(string $finalData = ""): Promise
    {
        return $this->socket->end($finalData);
    }

    public function reference(): void
    {
        $this->socket->reference();
    }

    public function unreference(): void
    {
        $this->socket->unreference();
    }

    public function close(): void
    {
        $this->socket->close();
    }

    public function isClosed(): bool
    {
        return $this->socket->isClosed();
    }

    public function getLocalAddress(): SocketAddress
    {
        return $this->socket->getLocalAddress();
    }

    public function getRemoteAddress(): SocketAddress
    {
        return $this->socket->getRemoteAddress();
    }

    public function setupTls(?CancellationToken $cancellationToken = null): Promise
    {
        return $this->socket->setupTls($cancellationToken);
    }

    public function shutdownTls(?CancellationToken $cancellationToken = null): Promise
    {
        return $this->socket->shutdownTls($cancellationToken);
    }

    public function getTlsState(): int
    {
        return $this->socket->getTlsState();
    }

    public function getTlsInfo(): ?TlsInfo
    {
        return $this->socket->getTlsInfo();
    }
}
