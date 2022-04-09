<?php

use Amp\ByteStream\StreamException;
use Amp\Websocket\Client;
use Amp\Websocket\Client\Handshake;
use Amp\Websocket\ClosedException;

require __DIR__ . '/../vendor/autoload.php';

const AGENT = 'amphp/websocket';

$errors = 0;

$connection = Client\connect('ws://127.0.0.1:9001/getCaseCount');
$message = $connection->receive();
$cases = (int) $message->buffer();

echo "Going to run {$cases} test cases." . PHP_EOL;

for ($i = 1; $i < $cases; $i++) {
    $connection = Client\connect('ws://127.0.0.1:9001/getCaseInfo?case=' . $i . '&agent=' . AGENT);
    $message = $connection->receive();
    $info = \json_decode($message->buffer(), true);

    print $info['id'] . ' ' . \str_repeat('-', 80 - \strlen($info['id']) - 1) . PHP_EOL;
    print \wordwrap($info['description'], 80, PHP_EOL) . ' ';

    $handshake = new Handshake('ws://127.0.0.1:9001/runCase?case=' . $i . '&agent=' . AGENT);
    $connection = Client\connect($handshake);

    try {
        while ($message = $connection->receive()) {
            $content = $message->buffer();

            if ($message->isBinary()) {
                $connection->sendBinary($content);
            } else {
                $connection->send($content);
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

    $connection = Client\connect('ws://127.0.0.1:9001/getCaseStatus?case=' . $i . '&agent=' . AGENT);
    $message = $connection->receive();
    print($result = \json_decode($message->buffer(), true)['behavior']);

    if ($result === 'FAILED') {
        $errors++;
    }

    print PHP_EOL . PHP_EOL;
}

$connection = Client\connect('ws://127.0.0.1:9001/updateReports?agent=' . AGENT);
$connection->close();

if ($errors) {
    exit(1);
}
