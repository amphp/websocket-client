<?php

namespace Amp\Websocket;

final class Handshake {
    // defined in https://tools.ietf.org/html/rfc6455#section-4
    const ACCEPT_CONCAT = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';
    const ACCEPT_NONCE_LENGTH = 16;

    private $encrypted;
    private $remoteAddress;
    private $path;
    private $headers = [];
    private $accept;

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

        if ($port !== $defaultPort) {
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

    public function getHeaders(): array {
        return $this->headers;
    }

    public function generateRequest(): string {
        $headers = '';

        foreach ($this->headers as $field => $values) {
            /** @var array $values */
            foreach ($values as $value) {
                $headers .= "$field: $value\r\n";
            }
        }

        // This has to be generated for each connect attempt, once a new request has been generated,
        // we use the new key for validation.
        $this->accept = \base64_encode(\random_bytes(self::ACCEPT_NONCE_LENGTH));

        return 'GET ' . $this->path . " HTTP/1.1\r\nConnection: Upgrade\r\nUpgrade: websocket\r\nSec-Websocket-Version: 13\r\nSec-Websocket-Key: {$this->accept}\r\n$headers\r\n";
    }

    public function decodeResponse(string $headerBuffer): array {
        $startLine = \substr($headerBuffer, 0, \strpos($headerBuffer, "\r\n"));

        if (!\preg_match("(^HTTP/1.1[\x20\x09]101[\x20\x09]*[^\x01-\x08\x10-\x19]*$)", $startLine)) {
            throw new WebSocketException('Did not receive switching protocols response: ' . $startLine);
        }

        \preg_match_all("(
            (?P<field>[^()<>@,;:\\\"/[\\]?={}\x01-\x20\x7F]+):[\x20\x09]*
            (?P<value>[^\x01-\x08\x0A-\x1F\x7F]*)\x0D?[\x20\x09]*\r?\n
        )x", $headerBuffer, $responseHeaders);

        $headers = [];

        /** @var array[] $responseHeaders */
        foreach ($responseHeaders['field'] as $idx => $field) {
            $headers[\strtolower($field)][] = $responseHeaders['value'][$idx];
        }

        $upgrade = $headers['upgrade'][0] ?? '';
        if (\strtolower($upgrade) !== 'websocket') {
            throw new WebSocketException('Missing "Upgrade: websocket" header.');
        }

        $connection = $headers['connection'][0] ?? '';
        if (!\in_array('upgrade', \array_map('trim', \array_map('strtolower', \explode(',', $connection))), true)) {
            throw new WebSocketException('Missing "Connection: upgrade" header.');
        }

        $secWebsocketAccept = $headers['sec-websocket-accept'][0] ?? '';
        if ($secWebsocketAccept !== \base64_encode(\sha1($this->accept . self::ACCEPT_CONCAT, true))) {
            throw new WebSocketException('Invalid "Sec-WebSocket-Accept" header.');
        }

        // TODO: Check extension header and fail handshake if extensions are not supported

        return $headers;
    }
}
