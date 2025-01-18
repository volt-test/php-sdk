<?php

namespace VoltTest\Extractors;

use VoltTest\Exceptions\VoltTestException;

class CookieExtractor implements Extractor
{
    private string $variableName;

    private string $selector;

    public function __construct(string $variableName, string $selector)
    {
        $this->variableName = $variableName;
        $this->selector = $selector;
    }

    public function toArray(): array
    {
        if (! $this->validate()) {
            throw new VoltTestException('Invalid Cookie Extractor');
        }

        return [
            'variable_name' => $this->variableName,
            'selector' => $this->selector,
            'type' => 'cookie',
        ];
    }

    public function validate(): bool
    {
        return trim($this->selector) !== '' && trim($this->variableName) !== '';
    }
}
