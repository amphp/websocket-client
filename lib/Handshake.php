<?php

namespace Amp\Websocket;

final class Handshake {
    const ACCEPT_CONCAT = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';
    const ACCEPT_NONCE_LENGTH = 12;

    private $encrypted;
    private $remoteAddress;
    private $path;
    private $headers = [];

    /**
     * @param string $url target address of websocket (e.g. ws://foo.bar/baz or wss://crypto.example/?secureConnection)
     */
    public function __construct(string $url) {
        $url = \parse_url($url);

        $this->encrypted = $url['scheme'] === 'wss';
        $defaultPort = $this->encrypted ? 443 : 80;

        $host = $url['host'];
        $port = $url['port'] ?? $defaultPort;

        $this->remoteAddress = $host . ':' . $port;

        if ($url['port'] !== $defaultPort) {
            $host .= ':' . $port;
        }

        $this->headers['host'][] = $host;
        $this->path = $url['path'] ?? '/';

        if (isset($url['query'])) {
            $this->path .= "?{$url['query']}";
        }
    }

    public function addHeader(string $field, string $value): self {
        $this->headers[$field][] = $value;

        return $this;
    }

    public function getRemoteAddress(): string {
        return $this->remoteAddress;
    }

    public function isEncrypted(): bool {
        return $this->encrypted;
    }

    public function getRawRequest(): string {
        $headers = '';

        foreach ($this->headers as $field => $values) {
            /** @var array $values */
            foreach ($values as $value) {
                $headers .= "$field: $value\r\n";
            }
        }

        $accept = \base64_encode(\random_bytes(self::ACCEPT_NONCE_LENGTH));

        return "GET $this->path HTTP/1.1\r\nConnection: Upgrade\r\nUpgrade: websocket\r\nSec-Websocket-Version: 13\r\nSec-Websocket-Key: $accept\r\n$headers\r\n";
    }
}
