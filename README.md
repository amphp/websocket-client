# websocket-client

[![Build Status](https://img.shields.io/travis/amphp/websocket-client/master.svg?style=flat-square)](https://travis-ci.org/amphp/websocket-client)
[![CoverageStatus](https://img.shields.io/coveralls/amphp/websocket-client/master.svg?style=flat-square)](https://coveralls.io/github/amphp/websocket-client?branch=master)
![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)

`amphp/websocket-client` is an async WebSocket client for PHP based on Amp.

## Installation

This package can be installed as a [Composer](https://getcomposer.org/) dependency.

```
composer require amphp/websocket-client
```

## Requirements

* PHP 7.1+

## Documentation & Examples

More extensive code examples reside in the [`examples`](examples) directory.

```php
use Amp\Delayed;
use Amp\Websocket\Client\Connection;
use Amp\Websocket\Message;
use function Amp\Websocket\Client\connect;

// Connects to the Kaazing echoing websocket demo.
Amp\Loop::run(function () {
    /** @var Connection $connection */
    $connection = yield connect('ws://demos.kaazing.com/echo');
    yield $connection->send("Hello!");

    $i = 0;

    while ($message = yield $connection->receive()) {
        /** @var Message $message */
        $payload = yield $message->buffer();
        printf("Received: %s\n", $payload);

        if ($payload === "Goodbye!") {
            $connection->close();
            break;
        }

        yield new Delayed(1000);

        if ($i < 3) {
            yield $connection->send("Ping: " . ++$i);
        } else {
            yield $connection->send("Goodbye!");
        }
    }
});
```

## Versioning

`amphp/websocket-client` follows the [semver](http://semver.org/) semantic versioning specification like all other `amphp` packages.

## Security

If you discover any security related issues, please email [`contact@amphp.org`](mailto:contact@amphp.org) instead of using the issue tracker.

## License

The MIT License (MIT). Please see [`LICENSE`](./LICENSE) for more information.
