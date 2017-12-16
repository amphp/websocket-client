<?php

namespace Amp\Websocket;

use Amp\ByteStream\InputStream;
use Amp\Promise;

/**
 * This class allows streamed and buffered access to an `InputStream` similar to `Amp\ByteStream\Message`.
 *
 * `Amp\ByteStream\Message` is not extended due to it implementing `Amp\Promise`, which makes resolving promises with it
 * impossible. `Amp\ByteStream\Message` will probably be adjusted to follow this implementation in the future.
 */
final class Message implements InputStream {
    /** @var bool */
    private $binary;

    /** @var InputStream */
    private $stream;

    /** @var \Amp\ByteStream\Message|null */
    private $message;

    public function __construct(InputStream $stream, bool $binary) {
        $this->stream = $stream;
        $this->binary = $binary;
    }

    /**
     * @return bool Returns a promise that resolves to true if the message is binary, false if it is UTF-8 text.
     */
    public function isBinary(): bool {
        return $this->binary;
    }

    /** @inheritdoc */
    public function read(): Promise {
        return $this->stream->read();
    }

    /**
     * Buffers the entire message and resolves the returned promise then.
     *
     * @return Promise Resolves with the entire message contents.
     */
    public function buffer(): Promise {
        if (!$this->message) {
            $this->message = new \Amp\ByteStream\Message($this->stream);
        }

        return $this->message;
    }
}
