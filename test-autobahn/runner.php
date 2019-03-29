<?php

use Amp\ByteStream\StreamException;
use Amp\Loop;
use Amp\Websocket\Client;
use Amp\Websocket\Client\Connection;
use Amp\Websocket\Client\Handshake;
use Amp\Websocket\ClosedException;
use Amp\Websocket\Message;
use Amp\Websocket\Options;

require __DIR__ . '/../vendor/autoload.php';

const AGENT = 'amphp/websocket';

Loop::run(function () {
    $errors = 0;

    $options = Options::createClientDefault()
        ->withBytesPerSecondLimit(\PHP_INT_MAX)
        ->withFrameSizeLimit(\PHP_INT_MAX)
        ->withFramesPerSecondLimit(\PHP_INT_MAX)
        ->withMessageSizeLimit(\PHP_INT_MAX)
        ->withValidateUtf8(true);

    /** @var Connection $connection */
    $connection = yield Client\connect('ws://127.0.0.1:9001/getCaseCount');
    /** @var Message $message */
    $message = yield $connection->receive();
    $cases = (int) yield $message->buffer();

    echo "Going to run {$cases} test cases." . PHP_EOL;

    for ($i = 1; $i < $cases; $i++) {
        $connection = yield Client\connect('ws://127.0.0.1:9001/getCaseInfo?case=' . $i . '&agent=' . AGENT);
        $message = yield $connection->receive();
        $info = \json_decode(yield $message->buffer(), true);

        print $info['id'] . ' ' . \str_repeat('-', 80 - \strlen($info['id']) - 1) . PHP_EOL;
        print \wordwrap($info['description'], 80, PHP_EOL) . ' ';

        $handshake = new Handshake('ws://127.0.0.1:9001/runCase?case=' . $i . '&agent=' . AGENT, $options);
        $connection = yield Client\connect($handshake);

        try {
            while ($message = yield $connection->receive()) {
                $content = yield $message->buffer();

                if ($message->isBinary()) {
                    yield $connection->sendBinary($content);
                } else {
                    yield $connection->send($content);
                }
            }
        } catch (ClosedException $e) {
            // ignore
        } catch (AssertionError $e) {
            print 'Assertion error: ' . $e->getMessage() . PHP_EOL;
            $connection->close();
        } catch (Error $e) {
            print 'Error: ' . $e->getMessage() . PHP_EOL;
            $connection->close();
        } catch (StreamException $e) {
            print 'Stream exception: ' . $e->getMessage() . PHP_EOL;
            $connection->close();
        }

        $connection = yield Client\connect('ws://127.0.0.1:9001/getCaseStatus?case=' . $i . '&agent=' . AGENT);
        $message = yield $connection->receive();
        print($result = \json_decode(yield $message->buffer(), true)['behavior']);

        if ($result === 'FAILED') {
            $errors++;
        }

        print PHP_EOL . PHP_EOL;
    }

    $connection = yield Client\connect('ws://127.0.0.1:9001/updateReports?agent=' . AGENT);
    $connection->close();

    Loop::stop();

    if ($errors) {
        exit(1);
    }
});
