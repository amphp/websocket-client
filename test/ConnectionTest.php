<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\Websocket\Client\Test;

use Amp\Http\Server\HttpServer;
use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Socket\Server as SocketServer;
use Amp\Socket\SocketException;
use Amp\Websocket\Client;
use Amp\Websocket\ClosedException;
use Amp\Websocket\Options;
use Amp\Websocket\Server\ClientHandler;
use Amp\Websocket\Server\Gateway;
use Amp\Websocket\Server\Websocket;
use Psr\Log\NullLogger;
use function Amp\async;
use function Amp\await;
use function Amp\Websocket\Client\connect;

class ConnectionTest extends AsyncTestCase
{
    /**
     * This method creates a new server that listens on a randomly assigned port and returns the used port.
     *
     * @param ClientHandler $clientHandler
     *
     * @return array Returns the HttpServer instance and the used port number.
     * @throws SocketException
     */
    protected function createServer(ClientHandler $clientHandler): array
    {
        $socket = SocketServer::listen('tcp://localhost:0');

        $port = $socket->getAddress()->getPort();

        $server = new HttpServer([$socket], new Websocket($clientHandler), new NullLogger);

        $server->start();
        return [$server, $port];
    }

    public function testSimpleBinaryEcho(): void
    {
        [$server, $port] = $this->createServer(new class extends Helper\EmptyClientHandler {
            public function handleClient(Gateway $gateway, Client $client, Request $request, Response $response): void
            {
                while ($message = $client->receive()) {
                    await($client->sendBinary($message->buffer()));
                }
            }
        });

        try {
            $client = connect('ws://localhost:' . $port . '/');
            $client->sendBinary('Hey!');

            $message = $client->receive();

            $this->assertTrue($message->isBinary());
            $this->assertSame('Hey!', $message->buffer());

            $promise = async(fn() => $client->receive());
            $client->close();

            $this->assertNull(await($promise));
        } finally {
            $server->stop();
        }
    }

    public function testSimpleTextEcho(): void
    {
        [$server, $port] = $this->createServer(new class extends Helper\EmptyClientHandler {
            public function handleClient(Gateway $gateway, Client $client, Request $request, Response $response): void
            {
                while ($message = $client->receive()) {
                    await($client->send($message->buffer()));
                }
            }
        });

        try {
            $client = connect('ws://localhost:' . $port . '/');
            $client->send('Hey!');

            $message = $client->receive();

            $this->assertFalse($message->isBinary());
            $this->assertSame('Hey!', $message->buffer());

            $promise = async(fn() => $client->receive());
            $client->close();

            $this->assertNull(await($promise));
        } finally {
            $server->stop();
        }
    }

    public function testUnconsumedMessage(): void
    {
        [$server, $port] = $this->createServer(new class extends Helper\EmptyClientHandler {
            public function handleClient(Gateway $gateway, Client $client, Request $request, Response $response): void
            {
                await($client->send(\str_repeat('.', 1024 * 1024 * 1)));
                await($client->send('Message'));
            }
        });

        try {
            $client = connect('ws://localhost:' . $port . '/');

            $message = $client->receive();

            // Do not consume the bytes from the first message.
            unset($message);

            $message = $client->receive();
            $this->assertFalse($message->isBinary());
            $this->assertSame('Message', $message->buffer());

            $promise = async(fn() => $client->receive());
            $client->close();

            $this->assertNull(await($promise));
        } finally {
            $server->stop();
        }
    }

    public function testVeryLongMessage(): void
    {
        $options = Options::createClientDefault()
            ->withBytesPerSecondLimit(\PHP_INT_MAX)
            ->withFramesPerSecondLimit(\PHP_INT_MAX)
            ->withMessageSizeLimit(1024 * 1024 * 10)
            ->withoutCompression();

        [$server, $port] = $this->createServer(new class extends Helper\EmptyClientHandler {
            public function handleClient(Gateway $gateway, Client $client, Request $request, Response $response): void
            {
                $payload = \str_repeat('.', 1024 * 1024 * 10); // 10 MiB
                await($client->sendBinary($payload));
            }
        });

        try {
            $client = connect(new Client\Handshake('ws://localhost:' . $port . '/', $options));

            $message = $client->receive();
            $this->assertSame(\str_repeat('.', 1024 * 1024 * 10), $message->buffer());
        } finally {
            $server->stop();
        }
    }

    public function testTooLongMessage(): void
    {
        $options = Options::createClientDefault()
            ->withBytesPerSecondLimit(\PHP_INT_MAX)
            ->withFramesPerSecondLimit(\PHP_INT_MAX)
            ->withMessageSizeLimit(1024 * 1024 * 10)
            ->withoutCompression();

        [$server, $port] = $this->createServer(new class() extends Helper\EmptyClientHandler {
            public function handleClient(Gateway $gateway, Client $client, Request $request, Response $response): void
            {
                $payload = \str_repeat('.', 1024 * 1024 * 10 + 1); // 10 MiB + 1 byte
                await($client->sendBinary($payload));
            }
        });

        try {
            $client = connect(new Client\Handshake('ws://localhost:' . $port . '/', $options));

            $message = $client->receive();
            $message->buffer();
        } catch (ClosedException $exception) {
            $this->assertSame('Received payload exceeds maximum allowable size', $exception->getReason());
        } finally {
            $server->stop();
        }
    }
}
