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
     * @param string|Ws           $uri target address of websocket (e.g. ws://foo.bar/bar or
     *                                 wss://crypto.example/?secureConnection) or a \League\Uri\Ws instance.
     * @param Options|null        $options
     * @param string[]|string[][] $headers
     *
     * @throws \TypeError If $uri is not a string or and instance of \League\Uri\Ws.
     * @throws \Error If compression is enabled in the options but the zlib extension is not installed.
     */
    public function __construct($uri, ?Options $options = null, array $headers = [])
    {
        $this->uri = $this->makeUri($uri);
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
     * @param $uri string|Ws
     *
     * @return self Cloned object
     */
    public function withUri($uri): self
    {
        $clone = clone $this;
        $clone->uri = $clone->makeUri($uri);

        return $clone;
    }

    private function makeUri($uri): Ws
    {
        if (\is_string($uri)) {
            try {
                return Ws::createFromString($uri);
            } catch (UriException $exception) {
                throw new \Error('Invalid Websocket URI provided', 0, $exception);
            }
        } elseif (!$uri instanceof Ws) {
            throw new \TypeError(\sprintf('Must provide an instance of %s or a URL as a string', Ws::class));
        }

        return $uri;
    }

    /**
     * @return Options
     */
    public function getOptions(): Options
    {
        return $this->options;
    }

    /**
     * @param Options $options
     *
     * @return self Cloned object.
     */
    public function withOptions(Options $options): self
    {
        $clone = clone $this;
        $clone->options = $options;

        return $clone;
    }

    /**
     * Replaces all headers in the returned instance.
     *
     * @param string[]|string[][] $headers
     *
     * @return self Cloned object.
     */
    public function withHeaders(array $headers): self
    {
        $clone = clone $this;
        $clone->setHeaders($headers);

        return $clone;
    }

    /**
     * Replaces the given header in the returned instance.
     *
     * @param string          $name
     * @param string|string[] $value
     *
     * @return self Cloned object.
     */
    public function withHeader(string $name, $value): self
    {
        $clone = clone $this;
        $clone->setHeader($name, $value);

        return $clone;
    }

    /**
     * Adds the given header in the returned instance.
     *
     * @param string          $name
     * @param string|string[] $value
     *
     * @return self Cloned object.
     */
    public function withAddedHeader(string $name, $value): self
    {
        $clone = clone $this;
        $clone->addHeader($name, $value);

        return $clone;
    }

    /**
     * Removes the given header in the returned instance.
     *
     * @param string $name
     *
     * @return self Cloned object.
     */
    public function withoutHeader(string $name): self
    {
        $clone = clone $this;
        $clone->removeHeader($name);

        return $clone;
    }
}
