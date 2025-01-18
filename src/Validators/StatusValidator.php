<?php

namespace VoltTest\Validators;

class StatusValidator implements Validator
{
    private string $name;

    private int $expected;

    public function __construct(string $name, int $expected)
    {
        $this->name = $name;
        $this->expected = $expected;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'expected' => $this->expected,
            'type' => 'status_code',
        ];
    }
}
