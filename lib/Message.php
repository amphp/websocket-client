<?php

namespace Amp\Websocket;

use Amp\ByteStream\InputStream;
use Amp\Promise;

class Message extends \Amp\ByteStream\Message {
    /** @var \Amp\Promise */
    private $binary;

    public function __construct(InputStream $stream, Promise $binary) {
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
