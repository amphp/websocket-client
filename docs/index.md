---
title: Introduction
permalink: /
---
`amphp/websocket-client` provides an asynchronous WebSocket client for PHP based on Amp.
WebSockets are full-duplex communication channels, which are mostly used for realtime communication where the HTTP request / response cycle has too much overhead.
They're also used if the server should be able to push data to the client without an explicit request.

There are various use cases for a WebSocket client in PHP, such as consuming realtime APIs, writing tests for a WebSocket server, or controlling web browsers via their remote debugging APIs, which are based on WebSockets.

## Installation

The server can be installed as a [Composer](https://getcomposer.org/) dependency.

```bash
composer require amphp/websocket-client
```

## Connecting

You can create new WebSocket connections using `Amp\Websocket\connect()`.
It accepts a string as first argument, which must use the `ws` or `wss` (WebSocket over TLS) scheme.
`Options` can be specified by passing a `Handshake` object instead of a string as first argument, which can also be used to pass additional headers with the initial handshake.
Further optional arguments are `ConnectContext`, and a `CancellationToken`.

```php
<?php

require 'vendor/autoload.php';

use Amp\Websocket\Client;

Amp\Loop::run(function () {
    /** @var Client\Connection $connection */
    $connection = yield Client\connect('ws://localhost:1337/ws');

    // do something
});
```

## Sending Data

WebSocket messages can be sent using the `send()` and `sendBinary()` methods.
Text messages sent with `send()` must be valid UTF-8.
Binary messages send with `sendBinary()` can be arbitrary data.

Both methods return a `Promise` that is resolved as soon as the message has been fully written to the send buffer. This doesn't mean that the message has been received by the other party or that the message even left the local system's send buffer, yet.

## Receiving Data

WebSocket messages can be received using the `receive()` method. The `Promise` returned from `receive()` resolves once the client has started to receive a message. This allows streaming WebSocket messages, which might be pretty large. In practice, most messages are rather small, and it's fine buffering them completely. The `Promise` returned from `receive()` resolves to a `Message`, which allows easy buffered and streamed consumption.

{:.note}
> `Amp\Websocket\Message` differs from the now deprecated `Amp\ByteStream\Message`.
> `Amp\ByteStream\Message` directly implemented `Promise`, which is not possible for promise resolution values and has been confusing for most users.
> A consumer has to call `Amp\Websocket\Message::buffer()` which returns a `Promise` resolving to the entire message contents like in `Amp\ByteStream\Payload`.

## Demo

The following example connects to a WebSocket demo server that just echos all messages it receives.

```php
<?php

require 'vendor/autoload.php';

use Amp\Delayed;
use Amp\Websocket;
use Amp\Websocket\Client;

Amp\Loop::run(function () {
    /** @var Client\Connection $connection */
    $connection = yield Client\connect('ws://demos.kaazing.com/echo');
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
```
