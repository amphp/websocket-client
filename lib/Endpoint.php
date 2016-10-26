<?php

namespace Amp\Websocket;

interface Endpoint {
    public function send($data);
    public function sendBinary($data);
    public function close($code = Code::NORMAL_CLOSE, $reason = "");
}
