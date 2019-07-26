<?php

namespace Amp\Websocket\Client;

use League\Uri\AbstractUri;

final class WebsocketUri extends AbstractUri
{
    protected static $supported_schemes = [
        'ws' => 80,
        'wss' => 443,
    ];

    protected function isValidUri(): bool
    {
        return '' !== $this->host
            && (null === $this->scheme || isset(self::$supported_schemes[$this->scheme]))
            && !('' != $this->scheme && null === $this->host);
    }
}
