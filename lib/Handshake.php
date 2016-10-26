<?php

namespace Amp\Websocket;

use Amp\Deferred;

class Handshake {
    const ACCEPT_CONCAT = "258EAFA5-E914-47DA-95CA-C5AB0DC85B11";
    const ACCEPT_NONCE = 'Amp\Websocket+13'; // should be randomly selected per RFC 6455 4.1§7, but...

    private $crypto;
    private $target;
    private $path;
    private $options;
    private $headers = [];

    /**
     * @param string $url target address of websocket (e.g. ws://foo.bar/baz or wss://crypto.example/?secureConnection)
     * @param array $options to be passed to Socket\(crypto)Connect
     */
    public function __construct($url, $options = []) {
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
        $this->options = $options;
        if (isset($url["path"])) {
            $this->path = $url["path"];
        } else {
            $this->path = "/";
        }
        if (isset($url["query"])) {
            $this->path .= "?{$url['query']}";
        }
    }

    public function setHeader($field, $value) {
        $this->headers[$field] = $value;
        return $this;
    }

    public function getTarget() {
        return $this->target;
    }

    public function hasCrypto() {
        return $this->crypto;
    }

    public function getOptions() {
        return $this->options;
    }

    private function getRequest() {
        $headers = "";
        foreach ($this->headers as $field => $value) {
            $headers .= "$field: $value\r\n";
        }
        $accept = base64_encode(self::ACCEPT_NONCE);
        return "GET $this->path HTTP/1.1\r\nConnection: Upgrade\r\nUpgrade: websocket\r\nSec-Websocket-Version: 13\r\nSec-Websocket-Key: $accept\r\n$headers\r\n";
    }

    public function send($socket) {
        $deferred = new Deferred;
        stream_set_blocking($socket, false);
        $data = $this->getRequest();
        \Amp\onWritable($socket, function($writer, $socket) use ($deferred, &$data) {
            if ($bytes = fwrite($socket, $data)) {
                if ($bytes < \strlen($data)) {
                    $data = substr($data, $bytes);
                    return;
                }
                $data = '';
                \Amp\onReadable($socket, function($reader, $socket) use ($deferred, &$data) {
                    $data .= $bytes = fgets($socket);
                    if ($bytes == '' || \strlen($bytes) > 32768) {
                        \Amp\cancel($reader);
                        $deferred->resolve(null);
                    } elseif (substr($data, -4) == "\r\n\r\n") {
                        \Amp\cancel($reader);
                        $deferred->resolve($this->parseResponse($data));
                    }
                });
            } else {
                $deferred->resolve(null);
            }
            \Amp\cancel($writer);
        });
        return $deferred->getAwaitable();
    }

    private function parseResponse($data) {
        if (!preg_match("(^HTTP/1.1[\x20\x09]101[\x20\x09]*[^\x01-\x08\x10-\x19]*$)", substr($data, 0, strpos($data, "\r\n")))) {
            return null;
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
        return $headers;
    }
}
