<?php

require \dirname(__DIR__) . '/vendor/autoload.php';

use Amp\Websocket\Client\WebsocketHandshake;
use function Amp\Websocket\Client\connect;

// Connects to the websocket endpoint at libwebsockets.org which sends a message every 50ms.
$handshake = (new WebsocketHandshake('wss://libwebsockets.org'))
    ->withHeader('Sec-WebSocket-Protocol', 'dumb-increment-protocol');

$connection = connect($handshake);

while ($message = $connection->receive()) {
    $payload = $message->buffer();

    \printf("Received: %s\n", $payload);

    if ($payload === '100') {
        $connection->close();
        break;
    }
}
