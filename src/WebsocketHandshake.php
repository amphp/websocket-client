<?php declare(strict_types=1);

namespace Amp\Websocket\Client;

use Amp\Http\Client\Request;
use Amp\Http\Message;
use League\Uri;
use Psr\Http\Message\UriInterface as PsrUri;

final class WebsocketHandshake extends Message
{
    private PsrUri $uri;

    private float $tcpConnectTimeout = 10;

    private float $tlsHandshakeTimeout = 10;

    private int $headerSizeLimit = Request::DEFAULT_HEADER_SIZE_LIMIT;

    /**
     * @param string|PsrUri $uri target address of websocket (e.g. ws://foo.bar/bar or
     * wss://crypto.example/?secureConnection) or a PsrUri instance.
     * @param string[]|string[][] $headers
     */
    public function __construct(PsrUri|string $uri, array $headers = [])
    {
        $this->uri = $this->makeUri($uri);
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
     * @return self Cloned object
     */
    public function withUri(PsrUri|string $uri): self
    {
        $clone = clone $this;
        $clone->uri = $clone->makeUri($uri);

        return $clone;
    }

    /**
     * @return float Timeout in seconds for the TCP connection.
     */
    public function getTcpConnectTimeout(): float
    {
        return $this->tcpConnectTimeout;
    }

    public function withTcpConnectTimeout(float $tcpConnectTimeout): self
    {
        $clone = clone $this;
        $clone->tcpConnectTimeout = $tcpConnectTimeout;

        return $clone;
    }

    /**
     * @return float Timeout in seconds for the TLS handshake.
     */
    public function getTlsHandshakeTimeout(): float
    {
        return $this->tlsHandshakeTimeout;
    }

    public function withTlsHandshakeTimeout(float $tlsHandshakeTimeout): self
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
     * @param string|string[] $value
     *
     * @return self Cloned object.
     */
    public function withHeader(string $name, string|array $value): self
    {
        $clone = clone $this;
        $clone->setHeader($name, $value);

        return $clone;
    }

    /**
     * Adds the given header in the returned instance.
     *
     * @param string|string[] $value
     *
     * @return self Cloned object.
     */
    public function withAddedHeader(string $name, string|array $value): self
    {
        $clone = clone $this;
        $clone->addHeader($name, $value);

        return $clone;
    }

    /**
     * Removes the given header in the returned instance.
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
}
