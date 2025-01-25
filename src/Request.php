<?php

namespace VoltTest;

use VoltTest\Exceptions\InvalidRequestValidationException;

class Request
{
    private const METHODS = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'];

    private string $method = 'GET';

    private string $url = '';

    private array $headers = [];

    private string $body = '';

    /*
     * Sets the HTTP method for the request with validation
     * @param string $method
     * @return self
     * @throws InvalidRequestValidationException
     * */
    public function setMethod(string $method): self
    {
        $method = strtoupper($method);
        if (! in_array($method, self::METHODS)) {
            throw new InvalidRequestValidationException('Invalid HTTP method provided');
        }
        $this->method = $method;

        return $this;
    }

    /*
     * Sets the URL for the request with validation
     * @param string $url
     * @return self
     * @throws \InvalidRequestValidationException
     * */
    public function setUrl(string $url): self
    {
        // confirm it start with https:// or http://
        if (! preg_match('/^https?:\/\//', $url)) {
            throw new InvalidRequestValidationException('URL should start with http:// or https://');
        }
        // confirm it is a valid URL
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidRequestValidationException('Invalid URL provided');
        }
        $this->url = $url;

        return $this;
    }

    /*
     * Sets the body for the request with validation
     * @param string $body
     * @return self
     * @throws InvalidRequestValidationException
     * */
    public function setBody(string $body): self
    {
        if (in_array($this->method, ['GET', 'HEAD', 'OPTIONS'])) {
            throw new InvalidRequestValidationException(sprintf(('%s method should not have a body'), $this->method));
        }
        $this->body = $body;

        return $this;
    }

    /*
     * Adds a header to the request with validation
     * @param string $name
     * @param string $value
     * @return self
     * @throws InvalidRequestValidationException
     * */
    public function addHeader(string $name, string $value): self
    {
        // Validate header name format (RFC 7230)
        if (! preg_match('/^[a-zA-Z0-9\'`#$%&*+.^_|~!-]+$/', $name)) {
            throw new InvalidRequestValidationException(
                'Invalid header name. Header names should only contain printable US-ASCII characters except delimiters'
            );
        }

        // Validate header value
        if (preg_match('/[\r\n]/', $value)) {
            throw new InvalidRequestValidationException('Header value cannot contain CR or LF characters');
        }
        $this->headers[$name] = $value;

        return $this;
    }

    /*
     * Converts the request to an array
     * @return array
     * */
    public function toArray(): array
    {
        $array = [
            'method' => $this->method,
            'url' => $this->url,
            'body' => $this->body,
        ];
        if (count($this->headers) > 0) {
            $array['header'] = $this->headers;
        }
        return $array;
    }

    /**
     * Validates the entire request
     * @return bool
     * @throws InvalidRequestValidationException
     */
    public function validate(): bool
    {
        // Validate required fields
        if (empty($this->url)) {
            throw new InvalidRequestValidationException('URL is required');
        }

        // Validate content type header if body is present
        if (! empty($this->body)) {
            $hasContentType = false;
            foreach ($this->headers as $name => $value) {
                if (strtolower($name) === 'content-type') {
                    $hasContentType = true;

                    break;
                }
            }

            if (! $hasContentType) {
                throw new InvalidRequestValidationException('Content-Type header is required when body is present');
            }
        }

        return true;
    }
}
