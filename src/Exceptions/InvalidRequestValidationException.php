<?php

namespace VoltTest\Exceptions;

class InvalidRequestValidationException extends VoltTestException
{
    public static function create(string $message): self
    {
        return new self(
            sprintf(
                'Invalid request validation provided: "%s".',
                $message
            )
        );
    }
}
