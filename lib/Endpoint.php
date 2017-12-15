<?php

namespace Amp\Websocket;

use Amp\Iterator;
use Amp\Promise;

interface Endpoint extends Iterator {
    public function send(string $data): Promise;

    public function sendBinary(string $data): Promise;

    public function close(int $code = Code::NORMAL_CLOSE, string $reason = '');
}
