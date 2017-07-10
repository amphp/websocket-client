<?php

namespace Amp\Websocket;

use Amp\Promise;
use Amp\Socket\Socket;
use function Amp\call;

class Handshake {
    const ACCEPT_CONCAT = "258EAFA5-E914-47DA-95CA-C5AB0DC85B11";
    const ACCEPT_NONCE_LENGTH = 12;

    private $crypto;
    private $target;
    private $path;
    private $headers = [];

    /**
     * @param string $url target address of websocket (e.g. ws://foo.bar/baz or wss://crypto.example/?secureConnection)
     */
    public function __construct(string $url) {
        $url = parse_url($url);
        $this->crypto = $url["scheme"] == "wss";
        $host = $this->target = $url["host"];
        if (isset($url["port"])) {
            $this->target .= ":{$url['port']}";
            if ($url["port"] != ($this->crypto ? 443 : 80)) {
                $host = $this->target;
            }
        } elseif ($this->crypto) {
            $this->target .= ":443";
        } else {
            $this->target .= ":80";
        }
        $this->headers["Host"] = $host;
        if (isset($url["path"])) {
            $this->path = $url["path"];
        } else {
            $this->path = "/";
        }
        if (isset($url["query"])) {
            $this->path .= "?{$url['query']}";
        }
    }

    public function setHeader(string $field, $value): self {
        $this->headers[$field] = $value;
        return $this;
    }

    public function getTarget(): string {
        return $this->target;
    }

    public function hasCrypto(): bool {
        return $this->crypto;
    }

    private function getRequest(): string {
        $headers = "";
        foreach ($this->headers as $field => $value) {
            $headers .= "$field: $value\r\n";
        }
        $accept = base64_encode(random_bytes(self::ACCEPT_NONCE_LENGTH));
        return "GET $this->path HTTP/1.1\r\nConnection: Upgrade\r\nUpgrade: websocket\r\nSec-Websocket-Version: 13\r\nSec-Websocket-Key: $accept\r\n$headers\r\n";
    }

    public function send(Socket $socket): Promise {
        return call(function () use ($socket) {
            $data = $this->getRequest();

            yield $socket->write($data);

            $buffer = "";

            while (($chunk = yield $socket->read()) !== null) {
                $buffer .= $chunk;

                if ($position = \strpos($buffer, "\r\n\r\n")) {
                    $header = \substr($buffer, 0, $position + 4);

                    return $this->createConnection($socket, $header, \substr($buffer, $position + 4));
                }
            }

            throw new ServerException("Failed to read response from server");
        });
    }

    private function createConnection($socket, string $data, string $buffer): Connection {
        if (!preg_match("(^HTTP/1.1[\x20\x09]101[\x20\x09]*[^\x01-\x08\x10-\x19]*$)", substr($data, 0, strpos($data, "\r\n")))) {
            throw new ServerException("Did not receive switching protocols response");
        }
        preg_match_all("(
            (?P<field>[^()<>@,;:\\\"/[\\]?={}\x01-\x20\x7F]+):[\x20\x09]*
            (?P<value>[^\x01-\x08\x0A-\x1F\x7F]*)[\x0D]?[\x20\x09]*[\r]?[\n]
        )x", $data, $m);
        $headers = [];
        foreach ($m["field"] as $idx => $field) {
            $headers[strtolower($field)][] = $m["value"][$idx];
        }
        // TODO: validate headers...
        return new Rfc6455Connection($socket, $headers, $buffer);
    }
}
