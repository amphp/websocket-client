<?php

namespace Amp\Websocket\Client;

use Amp\Http\Client\Request;
use Amp\Http\Message;
use Amp\Websocket\Options;
use League\Uri;
use Psr\Http\Message\UriInterface as PsrUri;

final class Handshake extends Message
{
    private PsrUri $uri;

    private Options $options;

    private int $tcpConnectTimeout = 10000;

    private int $tlsHandshakeTimeout = 10000;

    private int $headerSizeLimit = Request::DEFAULT_HEADER_SIZE_LIMIT;

    /**
     * @param string|PsrUri       $uri target address of websocket (e.g. ws://foo.bar/bar or a
     *                                 wss://crypto.example/?secureConnection) or a PsrUri instance.
     * @param Options|null        $options
     * @param string[]|string[][] $headers
     *
     * @throws \TypeError If $uri is not a string or an instance of PsrUri.
     */
    public function __construct(PsrUri|string $uri, ?Options $options = null, array $headers = [])
    {
        $this->uri = $this->makeUri($uri);
        $this->options = $this->checkOptions($options);
        $this->setHeaders($headers);
    }

    /**
     * @return PsrUri Websocket URI (scheme will be either ws or wss).
     */
    public function getUri(): PsrUri
    {
        return $this->uri;
    }

    /**
     * @param string|PsrUri $uri
     *
     * @return self Cloned object
     */
    public function withUri(PsrUri|string $uri): self
    {
        $clone = clone $this;
        $clone->uri = $clone->makeUri($uri);

        return $clone;
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
        $clone->options = $clone->checkOptions($options);

        return $clone;
    }

    /**
     * @return int Timeout in milliseconds for the TCP connection.
     */
    public function getTcpConnectTimeout(): int
    {
        return $this->tcpConnectTimeout;
    }

    public function withTcpConnectTimeout(int $tcpConnectTimeout): self
    {
        $clone = clone $this;
        $clone->tcpConnectTimeout = $tcpConnectTimeout;

        return $clone;
    }

    /**
     * @return int Timeout in milliseconds for the TLS handshake.
     */
    public function getTlsHandshakeTimeout(): int
    {
        return $this->tlsHandshakeTimeout;
    }

    public function withTlsHandshakeTimeout(int $tlsHandshakeTimeout): self
    {
        $clone = clone $this;
        $clone->tlsHandshakeTimeout = $tlsHandshakeTimeout;

        return $clone;
    }

    public function getHeaderSizeLimit(): int
    {
        return $this->headerSizeLimit;
    }

    public function withHeaderSizeLimit(int $headerSizeLimit): self
    {
        $clone = clone $this;
        $clone->headerSizeLimit = $headerSizeLimit;

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

        foreach ($clone->getRawHeaders() as [$field]) {
            $clone->removeHeader($field);
        }

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

    protected function setHeader(string $name, $value): void
    {
        if (($name[0] ?? ':') === ':') {
            throw new \Error("Header name cannot be empty or start with a colon (:)");
        }

        parent::setHeader($name, $value);
    }

    protected function addHeader(string $name, $value): void
    {
        if (($name[0] ?? ':') === ':') {
            throw new \Error("Header name cannot be empty or start with a colon (:)");
        }

        parent::addHeader($name, $value);
    }

    /**
     * @param string|PsrUri $uri
     *
     * @return PsrUri
     */
    private function makeUri(PsrUri|string $uri): PsrUri
    {
        if (\is_string($uri)) {
            try {
                $uri = Uri\Http::createFromString($uri);
            } catch (\Exception $exception) {
                throw new \Error('Invalid Websocket URI provided', 0, $exception);
            }
        }

        switch ($uri->getScheme()) {
            case 'ws':
            case 'wss':
                break;

            default:
                throw new \Error('The URI scheme must be ws or wss: \'' . $uri->getScheme() . '\'');
        }

        return $uri;
    }

    private function checkOptions(?Options $options): Options
    {
        if ($options === null) {
            return Options::createClientDefault();
        }

        return $options;
    }
}
