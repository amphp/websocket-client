<?php

namespace Amp\Websocket;

use AsyncInterop\Promise;

interface Endpoint {
    public function send(string $data): Promise;
    public function sendBinary(string $data): Promise;
    public function close(int $code = Code::NORMAL_CLOSE, string $reason = ""): Promise;
}
