<?php

namespace VoltTest\Exceptions;

class InvalidRegexException extends VoltTestException
{
    public static function create(string $pattern, string $error): self
    {
        return new self(
            sprintf(
                'Invalid regex pattern provided: "%s". Error: %s',
                $pattern,
                $error
            )
        );
    }
}
