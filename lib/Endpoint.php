<?php

namespace Amp\Websocket;

use Amp\Iterator;
use Amp\Promise;

interface Endpoint extends Iterator {
    public function getHeaders(): array;

    public function getHeader(string $field);

    public function getHeaderArray(string $field): array;

    public function send(string $data): Promise;

    public function sendBinary(string $data): Promise;

    public function isClosed(): bool;

    public function close(int $code = Code::NORMAL_CLOSE, string $reason = '');

    public function getInfo(): array;
}
