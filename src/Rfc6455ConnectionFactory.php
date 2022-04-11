<?php

namespace Amp\Websocket\Client;

use Amp\Http\Client\Response;
use Amp\Socket\Socket;
use Amp\Websocket\CompressionContext;
use Amp\Websocket\HeartbeatQueue;
use Amp\Websocket\RateLimiter;
use Amp\Websocket\Rfc6455Client;
use Amp\Websocket\WebsocketClient;

final class Rfc6455ConnectionFactory implements WebsocketConnectionFactory
{
    public function __construct(
        private readonly ?HeartbeatQueue $heartbeatQueue = null,
        private readonly ?RateLimiter $rateLimiter = null,
        private readonly bool $textOnly = WebsocketClient::DEFAULT_TEXT_ONLY,
        private readonly bool $validateUtf8 = WebsocketClient::DEFAULT_VALIDATE_UTF8,
        private readonly int $messageSizeLimit = WebsocketConnection::DEFAULT_MESSAGE_SIZE_LIMIT,
        private readonly int $frameSizeLimit = WebsocketConnection::DEFAULT_FRAME_SIZE_LIMIT,
        private readonly int $streamThreshold = WebsocketClient::DEFAULT_STREAM_THRESHOLD,
        private readonly int $frameSplitThreshold = WebsocketClient::DEFAULT_FRAME_SPLIT_THRESHOLD,
        private readonly float $closePeriod = WebsocketClient::DEFAULT_CLOSE_PERIOD,
    ) {
    }

    public function createConnection(
        Response $response,
        Socket $socket,
        ?CompressionContext $compressionContext = null,
    ): WebsocketConnection {
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
