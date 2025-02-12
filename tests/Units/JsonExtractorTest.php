<?php

namespace Tests\Units;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use VoltTest\Exceptions\InvalidJsonPathException;
use VoltTest\Extractors\JsonExtractor;

class JsonExtractorTest extends TestCase
{
    private JsonExtractor $jsonExtractor;

    protected function setUp(): void
    {
        $this->jsonExtractor = new JsonExtractor('testVar', 'data.user.id');
    }

    public function testToArray(): void
    {
        $expected = [
            'variable_name' => 'testVar',
            'selector' => '$.data.user.id',
            'type' => 'json',
        ];
        $this->assertEquals($expected, $this->jsonExtractor->toArray());
    }

    public function testValidate(): void
    {
        $this->assertTrue($this->jsonExtractor->validate());
    }

    #[DataProvider('validJsonPathProvider')]
    public function testValidJsonPaths(string $jsonPath): void
    {
        $extractor = new JsonExtractor('testVar', $jsonPath);
        $this->assertTrue($extractor->validate());
    }

    public static function validJsonPathProvider(): array
    {
        return [
            ['simple.path'],
            ['deeply.nested.json.path'],
            ['with_underscore'],
            ['with.numbers.123'],
            ['mixed.path_with.numbers123'],
            ['mixed[0].path'],
            ['mixed[0].path[1]'],
            ['mixed[0].path[1].with[2].numbers[3]'],
        ];
    }

    #[DataProvider('invalidJsonPathProvider')]
    public function testInvalidJsonPaths(string $jsonPath): void
    {
        $this->expectException(InvalidJsonPathException::class);
        $extractor = new JsonExtractor('testVar', $jsonPath);
        $extractor->validate();
    }

    public static function invalidJsonPathProvider(): array
    {
        return [
            [''],
            ['invalid..path'],
            ['path.with.special#chars'],
            ['path.with.spaces .invalid'],
            ['$invalid.start'],
            ['invalid$.middle'],
            ['path.with.$'],
            ['$.data[abc]'],
            ['$.data[0].name[abc]'],
            ['$.data[0].name[0].'],
            ['$.data[0].name[0].[1]'],
            ['$.data[0].name[0].[1].'],
            ['$.data[0].name[0].[1].name'],
        ];
    }

    public function testEmptySelectorValidation(): void
    {
        $this->expectException(InvalidJsonPathException::class);
        $this->expectExceptionMessage('JSON path cannot be empty');
        $extractor = new JsonExtractor('testVar', '');
        $extractor->validate();
    }
}
