<?php

namespace Amp\Websocket;

class Message extends \Amp\Message {
    /** @var bool|null */
    private $binary;

    public function __construct(\Amp\Stream $stream, bool $binary = null) {
        parent::__construct($stream);
        $this->binary = $binary;
    }

    /**
     * @internal
     *
     * @param bool $binary
     *
     * @throws \Error Thrown if the binary flag is set multiple times.
     */
    public function setBinary(bool $binary) {
        if ($this->binary !== null) {
            throw new \Error("Binary flag has already been set");
        }

        $this->binary = $binary;
    }

    public function isBinary() {
        if ($this->binary === null) {
            throw new \Error(
                \sprintf("No data has arrived; wait for promise returned from %s::advance() to resolve", __CLASS__)
            );
        }
        return $this->binary;
    }
}
