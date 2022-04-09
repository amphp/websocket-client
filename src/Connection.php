<?php

namespace Amp\Websocket\Client;

use Amp\Http\Client\Response;
use Amp\Websocket\Client;

interface Connection extends Client
{
    public const DEFAULT_MESSAGE_SIZE_LIMIT = 2 ** 30; // 1GB
    public const DEFAULT_FRAME_SIZE_LIMIT = 2 ** 20 * 100; // 100MB

    /**
     * @return Response Server response originating the client connection.
     */
    public function getResponse(): Response;
}
