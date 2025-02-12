<?php

namespace VoltTest\Extractors;

use VoltTest\Exceptions\InvalidJsonPathException;

class JsonExtractor implements Extractor
{
    private string $variableName;

    private string $selector;

    public function __construct(string $variableName, string $jsonPath)
    {
        $this->variableName = $variableName;
        $this->selector = '$.' . $jsonPath;
    }

    /*
     * Convert the object to an array
     * @return array
     * @throws InvalidJsonPathException
     *
     * */
    public function toArray(): array
    {
        $this->validate();

        return [
            'variable_name' => $this->variableName,
            'selector' => $this->selector,
            'type' => 'json',
        ];
    }

    /*
     * Validate the selector and the content
     * @return bool
     * @throws InvalidJsonPathException
     * */
    public function validate(): bool
    {
        // Validate the selector data
        if (empty($this->selector) || $this->selector === '$.') {
            throw new InvalidJsonPathException('JSON path cannot be empty');
        }
        // Validate the selector ex: $.meta.token or $.data[0].name
        // Validate the selector follows proper JSON path format
        // Should start with $ followed by dot and valid path segments
        $pattern = '/^\$(\.[a-zA-Z0-9_]+|\[[0-9]+\])*$/';
        if (! preg_match($pattern, $this->selector)) {
            throw new InvalidJsonPathException('Invalid JSON path');
        }

        return true;
    }
}
