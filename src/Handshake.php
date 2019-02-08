<?php

namespace Amp\Websocket\Client;

use Amp\Http\Message;
use Amp\Websocket\Options;
use League\Uri\UriException;
use League\Uri\Ws;

final class Handshake extends Message
{
    /** @var Ws */
    private $uri;

    /** @var Options */
    private $options;

    /**
     * @param string              $uri target address of websocket (e.g. ws://foo.bar/bar or
     *                                 wss://crypto.example/?secureConnection)
     * @param Options|null        $options
     * @param string[]|string[][] $headers
     *
     * @throws \Error If compression is enabled in the options but the zlib extension is not installed.
     */
    public function __construct(string $uri, ?Options $options = null, array $headers = [])
    {
        try {
            $this->uri = Ws::createFromString($uri);
        } catch (UriException $exception) {
            throw new \Error('Invalid Websocket URI provided', 0, $exception);
        }

        $this->options = $options ?? new Options;

        if ($this->options->isCompressionEnabled() && !\extension_loaded('zlib')) {
            throw new \Error('Compression is enabled in options, but the zlib extension is not loaded');
        }

        if (!empty($headers)) {
            $this->setHeaders($headers);
        }
    }

    /**
     * @return Ws Websocket URI (scheme will be either ws or wss).
     */
    public function getUri(): Ws
    {
        return $this->uri;
    }

    /**
     * @return Options
     */
    public function getOptions(): Options
    {
        return $this->options;
    }
}
