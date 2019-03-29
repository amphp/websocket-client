<?php

namespace Amp\Websocket\Client\Test;

use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Amp\Http\Server\Server;
use Amp\PHPUnit\TestCase;
use Amp\Promise;
use Amp\Socket;
use Amp\Websocket\Client;
use Amp\Websocket\ClosedException;
use Amp\Websocket\Message;
use Amp\Websocket\Options;
use Amp\Websocket\Server\Websocket;
use Psr\Log\NullLogger;
use function Amp\call;
use function Amp\Websocket\Client\connect;

class WebSocketTest extends TestCase
{
    /** @var Server[] */
    private $servers = [];

    protected function tearDown()
    {
        $promises = [];
        foreach ($this->servers as $server) {
            $promises[] = $server->stop();
        }

        Promise\wait(Promise\all($promises));

        parent::tearDown();
    }

    /**
     * This method creates a new server that listens on a randomly assigned port and returns the used port.
     *
     * The server will automatically shut down after a test case ends.
     *
     * @param Websocket $websocket
     *
     * @return Promise<int> Resolves to the used port number.
     */
    public function createServer(Websocket $websocket): Promise
    {
        $socket = Socket\listen('tcp://127.0.0.1:0');

        $port = (int) \explode(':', $socket->getAddress())[1];

        $server = new Server([$socket], $websocket, new NullLogger);
        $this->servers[] = $server;

        return call(function () use ($server, $port) {
            yield $server->start();
            return $port;
        });
    }

    public function testSimpleBinaryEcho()
    {
        Promise\wait(call(function () {
            $port = yield $this->createServer(new class extends Helper\WebsocketAdapter {
                public function onConnect(Client $client, Request $request, Response $response): Promise
                {
                    return call(function () use ($client) {
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
        }));
    }

    public function testSimpleTextEcho()
    {
        Promise\wait(call(function () {
            $port = yield $this->createServer(new class extends Helper\WebsocketAdapter {
                public function onConnect(Client $client, Request $request, Response $response): Promise
                {
                    return call(function () use ($client) {
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
        }));
    }

    public function testUnconsumedMessage()
    {
        Promise\wait(call(function () {
            $port = yield $this->createServer(new class extends Helper\WebsocketAdapter {
                public function onConnect(Client $client, Request $request, Response $response): Promise
                {
                    return call(function () use ($client) {
                        yield $client->send(\str_repeat('.', 1024 * 1024 * 1));
                        yield $client->send('Message');
                    });
                }
            });

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
        }));
    }

    public function testVeryLongMessage()
    {
        Promise\wait(call(function () {
            $options = Options::createClientDefault()
                ->withBytesPerSecondLimit(\PHP_INT_MAX)
                ->withFramesPerSecondLimit(\PHP_INT_MAX)
                ->withMessageSizeLimit(1024 * 1024 * 10)
                ->withoutCompression();

            $port = yield $this->createServer(new class($options) extends Helper\WebsocketAdapter {
                public function onConnect(Client $client, Request $request, Response $response): Promise
                {
                    $payload = \str_repeat('.', 1024 * 1024 * 10); // 10 MiB
                    return $client->sendBinary($payload);
                }
            });

            /** @var Client $client */
            $client = yield connect(new Client\Handshake('ws://localhost:' . $port . '/', $options));

            /** @var Message $message */
            $message = yield $client->receive();
            $this->assertSame(\str_repeat('.', 1024 * 1024 * 10), yield $message->buffer());
        }));
    }

    public function testTooLongMessage()
    {
        Promise\wait(call(function () {
            $options = Options::createClientDefault()
                ->withBytesPerSecondLimit(\PHP_INT_MAX)
                ->withFramesPerSecondLimit(\PHP_INT_MAX)
                ->withMessageSizeLimit(1024 * 1024 * 10)
                ->withoutCompression();

            $port = yield $this->createServer(new class($options) extends Helper\WebsocketAdapter {
                public function onConnect(Client $client, Request $request, Response $response): Promise
                {
                    $payload = \str_repeat('.', 1024 * 1024 * 10 + 1); // 10 MiB + 1 byte
                    return $client->sendBinary($payload);
                }
            });

            /** @var Client $client */
            $client = yield connect(new Client\Handshake('ws://localhost:' . $port . '/', $options));

            try {
                /** @var Message $message */
                $message = yield $client->receive();
                yield $message->buffer();
            } catch (ClosedException $exception) {
                $this->assertSame('Received payload exceeds maximum allowable size', $exception->getReason());
            }
        }));
    }
}
