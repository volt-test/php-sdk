<?php

namespace Tests\Units;

use PHPUnit\Framework\TestCase;

class HtmlExtractorTest extends TestCase
{
    private \VoltTest\Extractors\HtmlExtractor $htmlExtractor;

    protected function setUp(): void
    {
        $this->htmlExtractor = new \VoltTest\Extractors\HtmlExtractor('testVar', 'div#test', 'data-id');
    }

    public function testToArray(): void
    {
        $expected = [
            'variable_name' => 'testVar',
            'selector' => 'div#test',
            'attribute' => 'data-id',
            'type' => 'html',
        ];
        $this->assertEquals($expected, $this->htmlExtractor->toArray());
    }

    public function testValidate(): void
    {
        $this->assertTrue($this->htmlExtractor->validate());
    }

    public function testEmptyVariableName(): void
    {
        $extractor = new \VoltTest\Extractors\HtmlExtractor('', 'div#test', 'data-id');
        $this->assertFalse($extractor->validate());
    }

    public function testEmptySelector(): void
    {
        $extractor = new \VoltTest\Extractors\HtmlExtractor('testVar', '', 'data-id');
        $this->assertFalse($extractor->validate());
    }

    public function testEmptyAttribute(): void
    {
        $extractor = new \VoltTest\Extractors\HtmlExtractor('testVar', 'div#test', '');
        $this->assertFalse($extractor->validate());
    }

    public function testNullAttribute(): void
    {
        $extractor = new \VoltTest\Extractors\HtmlExtractor('testVar', 'div#test', null);
        $this->assertTrue($extractor->validate());
    }

    public function testEmptyAttributeWithNullSelector(): void
    {
        $this->expectException(\VoltTest\Exceptions\VoltTestException::class);
        $extractor = new \VoltTest\Extractors\HtmlExtractor('testVar', 'div#date', '');
        $extractor->toArray();

    }
}