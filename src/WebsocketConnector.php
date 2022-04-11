<?php

namespace Amp\Websocket\Client;

use Amp\Cancellation;
use Amp\Http\Client\HttpException;

interface WebsocketConnector
{
    /**
     * @param WebsocketHandshake $handshake
     * @param Cancellation|null $cancellation
     *
     * @return WebsocketConnection
     *
     * @throws HttpException Thrown if the request fails.
     * @throws WebsocketConnectException If the response received is invalid or is not a switching protocols (101) response.
     */
    public function connect(WebsocketHandshake $handshake, ?Cancellation $cancellation = null): WebsocketConnection;
}
