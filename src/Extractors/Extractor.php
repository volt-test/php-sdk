<?php

namespace VoltTest\Extractors;

interface Extractor
{
    // ToArray method to be implemented by the Extractor classes
    public function toArray(): array;

    //Validate method to validate the selector and the content
    public function validate(): bool;
}
