# websocket-client

![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)

`amphp/websocket-client` is an async WebSocket client for PHP based on Amp.

## Installation

This package can be installed as a [Composer](https://getcomposer.org/) dependency.

```
composer require amphp/websocket-client
```

## Requirements

* PHP 8.1+

## Documentation & Examples

More extensive code examples reside in the [`examples`](examples) directory.

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

If you discover any security related issues, please email [`contact@amphp.org`](mailto:contact@amphp.org) instead of using the issue tracker.

## License

The MIT License (MIT). Please see [`LICENSE`](./LICENSE) for more information.
