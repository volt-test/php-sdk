<?php

namespace Tests\Units;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use VoltTest\Extractors\CookieExtractor;

class CookieExtractorTest extends TestCase
{
    private CookieExtractor $cookieExtractor;

    protected function setUp(): void
    {
        $this->cookieExtractor = new CookieExtractor('sessionId', 'PHPSESSID');
    }

    public function testToArray(): void
    {
        $expected = [
            'variable_name' => 'sessionId',
            'selector' => 'PHPSESSID',
            'type' => 'cookie',
        ];
        $this->assertEquals($expected, $this->cookieExtractor->toArray());
    }

    #[DataProvider('validSelectorProvider')]
    public function testValidSelectors(string $selector): void
    {
        $extractor = new CookieExtractor('testVar', $selector);
        $this->assertTrue($extractor->validate());
    }

    public static function validSelectorProvider(): array
    {
        return [
            ['PHPSESSID'],
            ['user_session'],
            ['_ga'],
            ['XSRF-TOKEN'],
            ['laravel_session'],
        ];
    }

    #[DataProvider('invalidSelectorProvider')]
    public function testInvalidSelectors(string $selector): void
    {
        $extractor = new CookieExtractor('testVar', $selector);
        $this->assertFalse($extractor->validate());
    }

    public static function invalidSelectorProvider(): array
    {
        return [
            [''],
            [' '],
            ["\n"],
            ["\t"],
        ];
    }

    public function testConstructorParameters(): void
    {
        $variableName = 'testVariable';
        $selector = 'test_cookie';

        $extractor = new CookieExtractor($variableName, $selector);
        $result = $extractor->toArray();

        $this->assertEquals($variableName, $result['variable_name']);
        $this->assertEquals($selector, $result['selector']);
        $this->assertEquals('cookie', $result['type']);
    }
}
