<?php

namespace Amp\Websocket\Client;

use Amp\Http\Client\Response;
use Amp\Socket\Socket;
use Amp\Websocket\Client;
use Amp\Websocket\CompressionContext;
use Amp\Websocket\HeartbeatQueue;
use Amp\Websocket\RateLimiter;
use Amp\Websocket\Rfc6455Client;

final class Rfc6455ConnectionFactory implements ConnectionFactory
{
    public function __construct(
        private readonly ?HeartbeatQueue $heartbeatQueue = null,
        private readonly ?RateLimiter $rateLimiter = null,
        private readonly bool $textOnly = Client::DEFAULT_TEXT_ONLY,
        private readonly bool $validateUtf8 = Client::DEFAULT_VALIDATE_UTF8,
        private readonly int $messageSizeLimit = Connection::DEFAULT_MESSAGE_SIZE_LIMIT,
        private readonly int $frameSizeLimit = Connection::DEFAULT_FRAME_SIZE_LIMIT,
        private readonly int $streamThreshold = Client::DEFAULT_STREAM_THRESHOLD,
        private readonly int $frameSplitThreshold = Client::DEFAULT_FRAME_SPLIT_THRESHOLD,
        private readonly float $closePeriod = Client::DEFAULT_CLOSE_PERIOD,
    ) {
    }

    public function createConnection(
        Response $response,
        Socket $socket,
        ?CompressionContext $compressionContext = null
    ): Connection {
        $client = new Rfc6455Client(
            socket: $socket,
            masked: true,
            compressionContext: $compressionContext,
            heartbeatQueue: $this->heartbeatQueue,
            rateLimiter: $this->rateLimiter,
            textOnly: $this->textOnly,
            validateUtf8: $this->validateUtf8,
            messageSizeLimit: $this->messageSizeLimit,
            frameSizeLimit: $this->frameSizeLimit,
            streamThreshold: $this->streamThreshold,
            frameSplitThreshold: $this->frameSplitThreshold,
            closePeriod: $this->closePeriod,
        );

        return new Rfc6455Connection($client, $response);
    }
}
