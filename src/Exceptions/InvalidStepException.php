<?php

namespace VoltTest\Exceptions;

class InvalidStepException extends VoltTestException
{
    public static function create(string $message): self
    {
        return new self(
            sprintf(
                'Invalid step provided: "%s".',
                $message
            )
        );
    }
}
