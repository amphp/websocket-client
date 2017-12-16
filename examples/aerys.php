<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Amp\Delayed;
use Amp\Websocket;

// Connects to the websocket endpoint in demo.php provided with Aerys (https://github.com/amphp/aerys).
Amp\Loop::run(function () {
    /** @var \Amp\Websocket\Connection $connection */
    $connection = yield Websocket\connect('ws://localhost:1337/ws');
    yield $connection->send('Hello!');

    $i = 0;

    while ($message = yield $connection->receive()) {
        $payload = yield $message->buffer();

        printf("Received: %s\n", $payload);

        if ($payload === 'Goodbye!') {
            $connection->close();
            break;
        }

        yield new Delayed(1000);

        if ($i < 3) {
            yield $connection->send('Ping: ' . ++$i);
        } else {
            yield $connection->send('Goodbye!');
        }
    }
});
