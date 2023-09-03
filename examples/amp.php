<?php

require \dirname(__DIR__) . '/vendor/autoload.php';

use Amp\Websocket\Client\WebsocketHandshake;
use function Amp\delay;
use function Amp\Websocket\Client\connect;

// Connects to the websocket endpoint in examples/broadcast-server/server.php provided in
// amphp/websocket-server (https://github.com/amphp/websocket-server).
$handshake = (new WebsocketHandshake('ws://localhost:1337/broadcast'))
    ->withHeader('Origin', 'http://localhost:1337');

$connection = connect($handshake);

$connection->sendText('Hello!');

$i = 0;

while ($message = $connection->receive()) {
    $payload = $message->buffer();

    \printf("Received: %s\n", $payload);

    if (\strpos($payload, 'Goodbye!') !== false) {
        $connection->close();
        break;
    }

    delay(1);

    if ($i < 3) {
        $connection->sendText('Ping: ' . ++$i);
    } else {
        $connection->sendText('Goodbye!');
    }
}
