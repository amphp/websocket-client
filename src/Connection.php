<?php

namespace Amp\Websocket\Client;

use Amp\Http\Client\Response;
use Amp\Websocket\Client;

interface Connection extends Client
{
    /**
     * @return Response Server response originating the client connection.
     */
    public function getResponse(): Response;
}
