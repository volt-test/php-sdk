<?php

namespace Tests\Units;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use VoltTest\Exceptions\VoltTestException;
use VoltTest\Extractors\HeaderExtractor;

class HeaderExtractorTest extends TestCase
{
    private HeaderExtractor $headerExtractor;

    protected function setUp(): void
    {
        $this->headerExtractor = new HeaderExtractor('token', 'Authorization');
    }

    public function testToArray(): void
    {
        $expected = [
            'variable_name' => 'token',
            'selector' => 'Authorization',
            'type' => 'header',
        ];
        $this->assertEquals($expected, $this->headerExtractor->toArray());
    }

    #[DataProvider('validSelectorProvider')]
    public function testValidSelectors(string $selector): void
    {
        $extractor = new HeaderExtractor('testVar', $selector);
        $this->assertTrue($extractor->validate());
    }

    public static function validSelectorProvider(): array
    {
        return [
            'standard header' => ['Authorization'],
            'content type' => ['Content-Type'],
            'accept' => ['Accept'],
            'custom header' => ['X-Custom-Header'],
            'lowercase' => ['accept-language'],
            'uppercase' => ['USER-AGENT'],
            'with numbers' => ['X-API-Version-2'],
            'single letter' => ['X'],
            'with underscore' => ['X_Custom_Header'],
        ];
    }

    #[DataProvider('invalidSelectorProvider')]
    public function testInvalidSelectors(string $selector): void
    {
        $extractor = new HeaderExtractor('testVar', $selector);
        $this->assertFalse($extractor->validate());
    }

    public static function invalidSelectorProvider(): array
    {
        return [
            'empty string' => [''],
            'space only' => [' '],
            'newline only' => ["\n"],
            'tab only' => ["\t"],
            'multiple spaces' => ['   '],
            'mixed whitespace' => [" \n\t "],
            'invalid characters' => ['Header@Value'],
            'spaces in name' => ['Header Value'],
            'special chars' => ['Header#Value'],
            'starts with number' => ['1-Invalid-Header'],
            'parentheses' => ['Header(1)'],
            'brackets' => ['Header[1]'],
            'contains spaces' => ['Invalid Header Name'],
        ];
    }

    public function testConstructorParameters(): void
    {
        $variableName = 'testVariable';
        $selector = 'X-Test-Header';

        $extractor = new HeaderExtractor($variableName, $selector);
        $result = $extractor->toArray();

        $this->assertEquals($variableName, $result['variable_name']);
        $this->assertEquals($selector, $result['selector']);
        $this->assertEquals('header', $result['type']);
    }

    #[DataProvider('invalidSelectorProvider')]
    public function testToArrayThrowsExceptionForInvalidSelector(string $selector): void
    {
        $this->expectException(VoltTestException::class);
        $this->expectExceptionMessage('Invalid Header Extractor');

        $extractor = new HeaderExtractor('testVar', $selector);
        $extractor->toArray();
    }

    public function testValidateVariableName(): void
    {
        $extractor = new HeaderExtractor('', 'X-Test-Header');
        $this->assertFalse($extractor->validate(), 'Empty variable name should be invalid');

        $extractor = new HeaderExtractor(' ', 'X-Test-Header');
        $this->assertFalse($extractor->validate(), 'Whitespace variable name should be invalid');
    }
}
