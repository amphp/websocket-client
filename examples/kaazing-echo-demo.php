<?php

require \dirname(__DIR__) . '/vendor/autoload.php';

use Amp\Delayed;
use Amp\Websocket\Client\Connection;
use Amp\Websocket\Message;
use function Amp\Websocket\Client\connect;

Amp\Loop::run(function () {
    /** @var Connection $connection */
    $connection = yield connect('wss://demos.kaazing.com/echo');
    yield $connection->send('Hello!');

    $i = 0;

    /** @var Message $message */
    while ($message = yield $connection->receive()) {
        $payload = yield $message->buffer();

        \printf("Received: %s\n", $payload);

        if ($payload === 'Goodbye!') {
            yield $connection->close();
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
