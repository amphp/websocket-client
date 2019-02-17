<?php

namespace Amp\Websocket\Client\Internal;

use Amp\Promise;
use Amp\Success;

class ClientSocket extends \Amp\Socket\ClientSocket
{
    /** @var string|null */
    private $buffer;

    public function __construct($resource, string $buffer, int $chunkSize = self::DEFAULT_CHUNK_SIZE)
    {
        parent::__construct($resource, $chunkSize);
        $this->buffer = $buffer !== '' ? $buffer : null;
    }

    public function read(): Promise
    {
        if ($this->buffer !== null) {
            $buffer = $this->buffer;
            $this->buffer = null;
            return new Success($buffer);
        }

        return parent::read();
    }
}
