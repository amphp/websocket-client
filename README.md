# websocket-client

AMPHP is a collection of event-driven libraries for PHP designed with fibers and concurrency in mind.
`amphp/websocket-client` provides an asynchronous WebSocket client for PHP based on Amp.
Websockets are full-duplex communication channels, which are mostly used for realtime communication where the HTTP request / response cycle has too much overhead.
They're also used if the server should be able to push data to the client without an explicit request.

There are various use cases for a WebSocket client in PHP, such as consuming realtime APIs, writing tests for a WebSocket server, or controlling web browsers via their remote debugging APIs, which are based on WebSockets.

## Installation

This package can be installed as a [Composer](https://getcomposer.org/) dependency.

```
composer require amphp/websocket-client
```

## Requirements

* PHP 8.1+


## Connecting

You can create new WebSocket connections using `Amp\Websocket\connect()`.
It accepts a string as first argument, which must use the `ws` or `wss` (WebSocket over TLS) scheme.
Options can be specified by passing a `WebsocketHandshake` object instead of a string as first argument, which can also be used to pass additional headers with the initial handshake.
The second argument is an optional `Cancellation`.

```php
<?php

require 'vendor/autoload.php';

use Amp\Websocket\Client;

$connection = Client\connect('ws://localhost:1337/ws');

// do something
```

## Sending Data

WebSocket messages can be sent using the `sendText()` and `sendBinary()` methods.
Text messages sent with `sendText()` must be valid UTF-8.
Binary messages send with `sendBinary()` can be arbitrary data.

Both methods return as soon as the message has been fully written to the send buffer. This doesn't mean that the message has been received by the other party or that the message even left the local system's send buffer, yet.

## Receiving Data

WebSocket messages can be received using the `receive()` method. `receive()` returns once the client has started to receive a message. This allows streaming WebSocket messages, which might be pretty large. In practice, most messages are rather small, and it's fine buffering them completely. `receive()` returns to a `WebsocketMessage`, which allows easy buffered and streamed consumption.

## Example

```php
use Amp\Websocket\Client\WebsocketHandshake;
use function Amp\Websocket\Client\connect;

// Connects to the websocket endpoint at libwebsockets.org which sends a message every 50ms.
$handshake = (new WebsocketHandshake('wss://libwebsockets.org'))
    ->withHeader('Sec-WebSocket-Protocol', 'dumb-increment-protocol');

$connection = connect($handshake);

while ($message = $connection->receive()) {
    $payload = $message->buffer();

    printf("Received: %s\n", $payload);

    if ($payload === '100') {
        $connection->close();
        break;
    }
}
```

## Versioning

`amphp/websocket-client` follows the [semver](http://semver.org/) semantic versioning specification like all other `amphp` packages.

## Security

If you discover any security related issues, please use the private security issue reporter instead of using the public issue tracker.

## License

The MIT License (MIT). Please see [`LICENSE`](./LICENSE) for more information.
