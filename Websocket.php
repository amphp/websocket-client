<?php

namespace Amp;

interface Websocket {
    /**
     * Invoked when the full two-way websocket upgrade completes
     *
     * All messages are sent to connected clients by calling methods on the
     * Endpoint instance passed in onOpen(). Applications must store
     * the endpoint instance for use once the connection is established.
     *
     * @param \Amp\Websocket\Endpoint $endpoint
     * @param array $headers of the handshake
     * @return mixed
     */
    public function onOpen(Websocket\Endpoint $endpoint, array $headers);

    /**
     * Invoked when data messages arrive from the client
     *
     * @param \Amp\Websocket\Message $msg A stream of data received from the client
     * @return mixed
     */
    public function onData(Websocket\Message $msg);

    /**
     * Invoked when the close handshake completes
     *
     * @param int $code The websocket code describing the close
     * @param string $reason The reason for the close (may be empty)
     * @return mixed
     */
    public function onClose($code, $reason);
}
