<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\Websocket\Client\Test;

use Amp\Http\Server\HttpServer;
use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use Amp\Socket\Server as SocketServer;
use Amp\Socket\SocketException;
use Amp\Websocket\Client;
use Amp\Websocket\ClosedException;
use Amp\Websocket\Message;
use Amp\Websocket\Options;
use Amp\Websocket\Server\ClientHandler;
use Amp\Websocket\Server\Gateway;
use Amp\Websocket\Server\Websocket;
use Psr\Log\NullLogger;
use function Amp\call;
use function Amp\Websocket\Client\connect;

class ConnectionTest extends AsyncTestCase
{
    /**
     * This method creates a new server that listens on a randomly assigned port and returns the used port.
     *
     * @param ClientHandler $clientHandler
     *
     * @return Promise<int> Resolves to the used port number.
     * @throws SocketException
     */
    protected function createServer(ClientHandler $clientHandler): Promise
    {
        $socket = SocketServer::listen('tcp://127.0.0.1:0');

        $port = $socket->getAddress()->getPort();

        $server = new HttpServer([$socket], new Websocket($clientHandler), new NullLogger);

        return call(static function () use ($server, $port) {
            yield $server->start();
            return [$server, $port];
        });
    }

    public function testSimpleBinaryEcho(): \Generator
    {
        [$server, $port] = yield $this->createServer(new class extends Helper\EmptyClientHandler {
            public function handleClient(Gateway $gateway, Client $client, Request $request, Response $response): Promise
            {
                return call(static function () use ($client) {
                    while ($message = yield $client->receive()) {
                        \assert($message instanceof Message);
                        if ($message->isBinary()) {
                            yield $client->sendBinary(yield $message->buffer());
                        }

                        yield $client->send(yield $message->buffer());
                    }
                });
            }
        });

        try {
            /** @var Client $client */
            $client = yield connect('ws://127.0.0.1:' . $port . '/');
            $client->sendBinary('Hey!');

            /** @var Message $message */
            $message = yield $client->receive();

            $this->assertInstanceOf(Message::class, $message);
            $this->assertTrue($message->isBinary());
            $this->assertSame('Hey!', yield $message->buffer());

            $promise = $client->receive();
            $client->close();

            $this->assertNull(yield $promise);
        } finally {
            $server->stop();
        }
    }

    public function testSimpleTextEcho(): \Generator
    {
        [$server, $port] = yield $this->createServer(new class extends Helper\EmptyClientHandler {
            public function handleClient(Gateway $gateway, Client $client, Request $request, Response $response): Promise
            {
                return call(static function () use ($client) {
                    while ($message = yield $client->receive()) {
                        \assert($message instanceof Message);
                        if ($message->isBinary()) {
                            yield $client->sendBinary(yield $message->buffer());
                        }

                        yield $client->send(yield $message->buffer());
                    }
                });
            }
        });

        try {
            /** @var Client $client */
            $client = yield connect('ws://localhost:' . $port . '/');
            $client->send('Hey!');

            /** @var Message $message */
            $message = yield $client->receive();

            $this->assertInstanceOf(Message::class, $message);
            $this->assertFalse($message->isBinary());
            $this->assertSame('Hey!', yield $message->buffer());

            $promise = $client->receive();
            $client->close();

            $this->assertNull(yield $promise);
        } finally {
            $server->stop();
        }
    }

    public function testUnconsumedMessage(): \Generator
    {
        [$server, $port] = yield $this->createServer(new class extends Helper\EmptyClientHandler {
            public function handleClient(Gateway $gateway, Client $client, Request $request, Response $response): Promise
            {
                return call(static function () use ($client) {
                    yield $client->send(\str_repeat('.', 1024 * 1024 * 1));
                    yield $client->send('Message');
                });
            }
        });

        try {
            /** @var Client $client */
            $client = yield connect('ws://localhost:' . $port . '/');

            /** @var Message $message */
            $message = yield $client->receive();

            $this->assertInstanceOf(Message::class, $message);
            // Do not consume the bytes from the first message.

            $message = yield $client->receive();
            $this->assertFalse($message->isBinary());
            $this->assertSame('Message', yield $message->buffer());

            $this->assertInstanceOf(Message::class, $message);

            $promise = $client->receive();
            $client->close();

            $this->assertNull(yield $promise);
        } finally {
            $server->stop();
        }
    }

    public function testVeryLongMessage(): \Generator
    {
        $options = Options::createClientDefault()
            ->withBytesPerSecondLimit(\PHP_INT_MAX)
            ->withFramesPerSecondLimit(\PHP_INT_MAX)
            ->withMessageSizeLimit(1024 * 1024 * 10)
            ->withoutCompression();

        [$server, $port] = yield $this->createServer(new class extends Helper\EmptyClientHandler {
            public function handleClient(Gateway $gateway, Client $client, Request $request, Response $response): Promise
            {
                $payload = \str_repeat('.', 1024 * 1024 * 10); // 10 MiB
                return $client->sendBinary($payload);
            }
        });

        try {
            /** @var Client $client */
            $client = yield connect(new Client\Handshake('ws://localhost:' . $port . '/', $options));

            /** @var Message $message */
            $message = yield $client->receive();
            $this->assertSame(\str_repeat('.', 1024 * 1024 * 10), yield $message->buffer());
        } finally {
            $server->stop();
        }
    }

    public function testTooLongMessage(): \Generator
    {
        $options = Options::createClientDefault()
            ->withBytesPerSecondLimit(\PHP_INT_MAX)
            ->withFramesPerSecondLimit(\PHP_INT_MAX)
            ->withMessageSizeLimit(1024 * 1024 * 10)
            ->withoutCompression();

        [$server, $port] = yield $this->createServer(new class() extends Helper\EmptyClientHandler {
            public function handleClient(Gateway $gateway, Client $client, Request $request, Response $response): Promise
            {
                $payload = \str_repeat('.', 1024 * 1024 * 10 + 1); // 10 MiB + 1 byte
                return $client->sendBinary($payload);
            }
        });

        try {
            /** @var Client $client */
            $client = yield connect(new Client\Handshake('ws://localhost:' . $port . '/', $options));

            /** @var Message $message */
            $message = yield $client->receive();
            yield $message->buffer();
        } catch (ClosedException $exception) {
            $this->assertSame('Received payload exceeds maximum allowable size', $exception->getReason());
        } finally {
            $server->stop();
        }
    }
}
