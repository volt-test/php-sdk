<?php

namespace VoltTest\Extractors;

use VoltTest\Exceptions\VoltTestException;

class HeaderExtractor implements Extractor
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
            throw new VoltTestException('Invalid Header Extractor');
        }

        return [
            'variable_name' => $this->variableName,
            'selector' => $this->selector,
            'type' => 'header',
        ];
    }

    public function validate(): bool
    {
        if (trim($this->variableName) === '') {
            return false;
        }

        // Check for empty or whitespace-only selector
        if (trim($this->selector) === '') {
            return false;
        }

        // Header names should match RFC 7230 requirements
        // Only alphanumeric characters, hyphens, and underscores are allowed
        // Must not start with a number and no spaces allowed
        return (bool) preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $this->selector);
    }
}
