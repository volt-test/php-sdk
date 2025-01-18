<?php

namespace VoltTest\Extractors;

use VoltTest\Exceptions\InvalidRegexException;

class RegexExtractor implements Extractor
{
    private $variableName;

    private $selector;

    public function __construct(string $variableName, string $selector)
    {
        $this->variableName = $variableName;
        $this->selector = $selector;
    }

    /*
     *  Convert the object to an array
     * @return array
     * @throws InvalidRegexException
     */
    public function toArray(): array
    {
        if (! $this->validate()) {
            throw new InvalidRegexException('Invalid regex selector or variable name');
        }

        return [
            'variable_name' => $this->variableName,
            'selector' => $this->selector,
            'type' => 'regex',
        ];
    }

    /*
     * Validate the selector and the content
     * @return bool
     * */
    public function validate(): bool
    {
        return trim($this->selector) !== '' && trim($this->variableName) !== '';
    }
}
