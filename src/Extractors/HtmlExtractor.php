<?php

namespace VoltTest\Extractors;

use VoltTest\Exceptions\VoltTestException;

class HtmlExtractor implements Extractor
{

    private string $variableName;

    private string $selector;

    private ?string $attribute;

    public function __construct(string $variableName, string $selector, ?string $attribute = null)
    {
        $this->variableName = $variableName;
        $this->selector = $selector;
        $this->attribute = $attribute;
    }

    public function toArray(): array
    {
        if (! $this->validate()) {
            throw new VoltTestException('Invalid HTML Extractor');
        }

        return [
            'variable_name' => $this->variableName,
            'selector' => $this->selector,
            'attribute' => $this->attribute,
            'type' => 'html',
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

        // Check for empty or whitespace-only attribute
        if ($this->attribute !== null && trim($this->attribute) === '') {
            return false;
        }

        return true;
    }
}
