<?php

namespace Amp\Websocket;

use Amp\ByteStream\IteratorStream;
use Amp\Iterator;
use Amp\Promise;

class Message extends \Amp\ByteStream\Message {
    /** @var \Amp\Promise */
    private $binary;

    public function __construct(Iterator $iterator, Promise $binary) {
        parent::__construct(new IteratorStream($iterator));
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
