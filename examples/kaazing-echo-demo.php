<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Amp\Delayed;
use Amp\Websocket;

Amp\Loop::run(function () {
    /** @var Websocket\Connection $connection */
    $connection = yield Websocket\connect('ws://demos.kaazing.com/echo');
    yield $connection->send('Hello!');

    $i = 0;

    /** @var Websocket\Message $message */
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
