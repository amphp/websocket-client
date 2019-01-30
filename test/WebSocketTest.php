<?php

namespace Amp\Websocket\Test;

use Amp\Http\Server\Request;
use Amp\Http\Server\Server;
use Amp\Http\Server\Websocket\Websocket;
use Amp\PHPUnit\TestCase;
use Amp\Promise;
use Amp\Socket;
use Amp\Websocket\Connection;
use Amp\Http\Server\Websocket\Message as ServerMessage;
use Amp\Websocket\Message;
use Amp\Websocket\WebSocketException;
use Psr\Log\NullLogger;
use function Amp\call;
use function Amp\Websocket\connect;

class WebSocketTest extends TestCase {
    /** @var Server[] */
    private $servers = [];

    protected function tearDown() {
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
    public function createServer(Websocket $websocket): Promise {
        $socket = Socket\listen('tcp://127.0.0.1:0');

        $port = (int) \explode(':', $socket->getAddress())[1];

        $server = new Server([$socket], $websocket, new NullLogger);
        $this->servers[] = $server;

        return call(function () use ($server, $port) {
            yield $server->start();
            return $port;
        });
    }

    public function testSimpleBinaryEcho() {
        Promise\wait(call(function () {
            $port = yield $this->createServer(new class extends Helper\WebsocketAdapter {
                public function onData(int $clientId, ServerMessage $message) {
                    if ($message->isBinary()) {
                        return yield $this->sendBinary(yield $message->buffer(), $clientId);
                    }

                    return yield $this->send(yield $message->buffer(), $clientId);
                }
            });

            /** @var Connection $client */
            $client = yield connect('ws://localhost:' . $port . '/');
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

    public function testSimpleTextEcho() {
        Promise\wait(call(function () {
            $port = yield $this->createServer(new class extends Helper\WebsocketAdapter {
                public function onData(int $clientId, ServerMessage $message) {
                    if ($message->isBinary()) {
                        return yield $this->sendBinary(yield $message->buffer(), $clientId);
                    }

                    return yield $this->send(yield $message->buffer(), $clientId);
                }
            });

            /** @var Connection $client */
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

    public function testUnconsumedMessage() {
        Promise\wait(call(function () {
            $port = yield $this->createServer(new class extends Helper\WebsocketAdapter {
                public function onOpen(int $clientId, Request $request) {
                    yield $this->send(\str_repeat('.', 1024 * 1024 * 1), $clientId);
                    yield $this->send('Message', $clientId);
                }
            });

            /** @var Connection $client */
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

    public function testVeryLongMessage() {
        Promise\wait(call(function () {
            $port = yield $this->createServer(new class extends Helper\WebsocketAdapter {
                public function onOpen(int $clientId, Request $request) {
                    $payload = \str_repeat('.', 1024 * 1024 * 10); // 10 MiB
                    yield $this->sendBinary($payload, $clientId);
                }
            });

            /** @var Connection $client */
            $client = yield connect('ws://localhost:' . $port . '/');

            /** @var Message $message */
            $message = yield $client->receive();
            $this->assertSame(\str_repeat('.', 1024 * 1024 * 10), yield $message->buffer());
        }));
    }

    public function testTooLongMessage() {
        Promise\wait(call(function () {
            $port = yield $this->createServer(new class extends Helper\WebsocketAdapter {
                public function onOpen(int $clientId, Request $request) {
                    $payload = \str_repeat('.', 1024 * 1024 * 10 + 1); // 10 MiB
                    yield $this->sendBinary($payload, $clientId);
                }
            });

            /** @var Connection $client */
            $client = yield connect('ws://localhost:' . $port . '/');

            /** @var Message $message */
            $message = yield $client->receive();

            $this->expectException(WebSocketException::class);
            $this->expectExceptionMessage('The connection was closed: Received payload exceeds maximum allowable size');
            yield $message->buffer();
        }));
    }
}
