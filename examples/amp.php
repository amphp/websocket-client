<?php

require \dirname(__DIR__) . '/vendor/autoload.php';

use Amp\Delayed;
use Amp\Websocket\Client\Connection;
use Amp\Websocket\Client\Handshake;
use Amp\Websocket\Message;
use function Amp\Websocket\Client\connect;

// Connects to the websocket endpoint in examples/broadcast-server/server.php provided in
// amphp/websocket-server (https://github.com/amphp/websocket-server).
Amp\Loop::run(function () {
    $handshake = new Handshake('ws://localhost:1337/broadcast');
    $handshake->setHeader('Origin', 'http://localhost:1337');

    /** @var Connection $connection */
    $connection = yield connect($handshake);
    yield $connection->send('Hello!');

    $i = 0;

    /** @var Message $message */
    while ($message = yield $connection->receive()) {
        $payload = yield $message->buffer();

        \printf("Received: %s\n", $payload);

        if (\strpos($payload, 'Goodbye!') !== false) {
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
