<?php

namespace Tests\Units;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use VoltTest\Exceptions\InvalidJsonPathException;
use VoltTest\Exceptions\InvalidRequestValidationException;
use VoltTest\Exceptions\InvalidStepException;
use VoltTest\Exceptions\VoltTestException;
use VoltTest\Step;

class StepTest extends TestCase
{
    private const TEST_URL = 'https://example.com';
    private const TEST_BODY = '{"key": "value"}';

    private Step $step;

    protected function setUp(): void
    {
        $this->step = new Step('test-step');
    }

    public function testStepCreationWithEmptyNameThrowsException()
    {
        $this->expectException(InvalidStepException::class);
        new Step('');
    }

    #[DataProvider('httpMethodProvider')]
    public function testHttpMethods(string $method, ?string $body = null): void
    {
        $methodName = $method;
        if ($body !== null) {
            $this->step->$methodName(self::TEST_URL, $body);
        } else {
            $this->step->$methodName(self::TEST_URL);
        }

        $stepArray = $this->step->toArray();
        $this->assertEquals($method, $stepArray['request']['method']);
        $this->assertEquals(self::TEST_URL, $stepArray['request']['url']);

        if ($body !== null) {
            $this->assertEquals($body, $stepArray['request']['body']);
        }
    }

    public static function httpMethodProvider(): array
    {
        return [
            ['GET'],
            ['GET'],
            ['POST'],
            ['POST', self::TEST_BODY],
            ['PUT', self::TEST_BODY],
            ['PUT'],
            ['DELETE'],
            ['PATCH', self::TEST_BODY],
            ['PATCH'],
            ['HEAD'],
            ['OPTIONS'],
        ];
    }

    public function testHeaderAddition(): void
    {
        $this->step->get(self::TEST_URL)
            ->header('Content-Type', 'application/json')
            ->header('Authorization', 'Bearer token');

        $stepArray = $this->step->toArray();
        $this->assertEquals('application/json', $stepArray['request']['header']['Content-Type']);
        $this->assertEquals('Bearer token', $stepArray['request']['header']['Authorization']);
        $this->assertEquals([
            'Content-Type' => 'application/json', 'Authorization' => 'Bearer token',
        ], $stepArray['request']['header']);
    }

    public function testExtractFromCookie(): void
    {
        $this->step->get(self::TEST_URL)
            ->extractFromCookie('sessionId', 'PHPSESSID');

        $stepArray = $this->step->toArray();
        $extract = $stepArray['extract'][0];

        $this->assertEquals('sessionId', $extract['variable_name']);
        $this->assertEquals('PHPSESSID', $extract['selector']);
        $this->assertEquals('cookie', $extract['type']);
    }

    public function testExtractFromHeader(): void
    {
        $this->step->get(self::TEST_URL)
            ->extractFromHeader('token', 'Authorization');

        $stepArray = $this->step->toArray();
        $extract = $stepArray['extract'][0];

        $this->assertEquals('token', $extract['variable_name']);
        $this->assertEquals('Authorization', $extract['selector']);
        $this->assertEquals('header', $extract['type']);
    }

    public function testExtractFromJson(): void
    {
        $this->step->get(self::TEST_URL)
            ->extractFromJson('userId', 'data.user.id');

        $stepArray = $this->step->toArray();
        $extract = $stepArray['extract'][0];

        $this->assertEquals('userId', $extract['variable_name']);
        $this->assertEquals('$.data.user.id', $extract['selector']);
        $this->assertEquals('json', $extract['type']);
    }

    public function testExtractFromHtml(): void
    {
        $this->step->get(self::TEST_URL)
            ->extractFromHtml('userId', 'div#user-id');
        $stepArray = $this->step->toArray();
        $extract = $stepArray['extract'][0];
        $this->assertEquals('userId', $extract['variable_name']);
        $this->assertEquals('div#user-id', $extract['selector']);
        $this->assertEquals('html', $extract['type']);
    }


    public function testExtractFromHtmlWithAttribute(): void
    {
        $this->step->get(self::TEST_URL)
            ->extractFromHtml('userId', 'div#user-id', 'data-id');
        $stepArray = $this->step->toArray();
        $extract = $stepArray['extract'][0];
        $this->assertEquals('userId', $extract['variable_name']);
        $this->assertEquals('div#user-id', $extract['selector']);
        $this->assertEquals('html', $extract['type']);
    }

    public function testExtractFromHtmlThrowsException(): void
    {
        $this->expectException(VoltTestException::class);
        $this->step->get(self::TEST_URL)
            ->extractFromHtml('userId', '#div#user-id', '');
        $this->step->toArray();
    }

    public function testInvalidJsonPathThrowsException(): void
    {
        $this->expectException(InvalidJsonPathException::class);
        $this->step->get(self::TEST_URL)
            ->extractFromJson('test', 'invalid.$.path');
    }

    public function testValidateStatus(): void
    {
        $this->step->get(self::TEST_URL)
            ->validateStatus('success', 200);

        $stepArray = $this->step->toArray();
        $validation = $stepArray['validate'][0];

        $this->assertEquals('success', $validation['name']);
        $this->assertEquals(200, $validation['expected']);
        $this->assertEquals('status_code', $validation['type']);
    }

    public function testComplexStepCreation(): void
    {
        $step = $this->step->post(self::TEST_URL, self::TEST_BODY)
            ->header('Content-Type', 'application/json')
            ->extractFromJson('userId', 'data.id')
            ->extractFromHeader('sessionId', 'X-Session-Id')
            ->validateStatus('success', 201);

        $stepArray = $step->toArray();

        $this->assertEquals('test-step', $stepArray['name']);
        $this->assertEquals('POST', $stepArray['request']['method']);
        $this->assertEquals(self::TEST_URL, $stepArray['request']['url']);
        $this->assertEquals(self::TEST_BODY, $stepArray['request']['body']);
        $this->assertEquals(['Content-Type' => 'application/json'], $stepArray['request']['header']);

        // Verify extracts
        $this->assertCount(2, $stepArray['extract']);
        $this->assertEquals('json', $stepArray['extract'][0]['type']);
        $this->assertEquals('header', $stepArray['extract'][1]['type']);

        // Verify validations
        $this->assertCount(1, $stepArray['validate']);
        $this->assertEquals('status_code', $stepArray['validate'][0]['type']);
        $this->assertEquals(201, $stepArray['validate'][0]['expected']);
    }

    #[DataProvider('invalidUrlProvider')]
    public function testInvalidUrlThrowsException(string $url): void
    {
        $this->expectException(InvalidRequestValidationException::class);
        $this->step->get($url);
    }

    public static function invalidUrlProvider(): array
    {
        return [
            [''],
            ['not-a-url'],
            ['http://'],
            ['ftp://example.com'],
        ];
    }

    public function testThinkTime(): void
    {
        $this->step->get(self::TEST_URL)
            ->setThinkTime('1s');

        $stepArray = $this->step->toArray();
        $this->assertEquals('1s', $stepArray['think_time']);
    }

    public function testInvalidThinkTimeThrowsException(): void
    {
        $this->expectException(VoltTestException::class);
        $this->step->get(self::TEST_URL)
            ->setThinkTime('invalid');
    }
}
