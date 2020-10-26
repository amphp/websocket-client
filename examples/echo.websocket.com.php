<?php

require \dirname(__DIR__) . '/vendor/autoload.php';

use function Amp\delay;
use function Amp\Websocket\Client\connect;

$connection = connect('wss://echo.websocket.org');

$connection->send('Hello!');

$i = 0;

while ($message = $connection->receive()) {
    $payload = $message->buffer();

    \printf("Received: %s\n", $payload);

    if ($payload === 'Goodbye!') {
        $connection->close();
        break;
    }

    delay(1000);

    if ($i < 3) {
        $connection->send('Ping: ' . ++$i);
    } else {
        $connection->send('Goodbye!');
    }
}
