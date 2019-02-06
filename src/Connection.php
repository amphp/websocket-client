<?php

namespace Amp\Websocket\Client;

use Amp\Websocket\Client;

interface Connection extends Client
{
    /**
     * Returns the headers as a string-indexed array of arrays of strings or an empty array if no headers
     * have been set.
     *
     * @return string[][]
     */
    public function getHeaders(): array;

    /**
     * Returns the array of values for the given header or an empty array if the header does not exist.
     *
     * @param string $name
     *
     * @return string[]
     */
    public function getHeaderArray(string $name): array;

    /**
     * Returns the value of the given header. If multiple headers are present for the named header, only the first
     * header value will be returned. Use getHeaderArray() to return an array of all values for the particular header.
     * Returns null if the header does not exist.
     *
     * @param string $name
     *
     * @return string|null
     */
    public function getHeader(string $name): ?string;
}
