<?php

namespace Amp\Websocket\Client;

use Amp\Http\Client\HttpException;
use Amp\Http\Client\Response;

final class ConnectionException extends HttpException
{
    private Response $response;

    public function __construct(string $message, Response $response, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->response = $response;
    }

    public function getResponse(): Response
    {
        return $this->response;
    }
}
