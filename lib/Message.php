<?php

namespace Amp\Websocket;

use Amp\{ Promise, Stream };

class Message extends \Amp\Message {
    /** @var \Amp\Promise */
    private $binary;

    public function __construct(Stream $stream, Promise $binary) {
        parent::__construct($stream);
        $this->binary = $binary;
    }

    /**
     * @return \Amp\Promise<bool> Returns a promise that resolves to true if the message is binary, false if
     *     it is UTF-8 text.
     */
    public function isBinary(): Promise {
        return $this->binary;
    }
}
