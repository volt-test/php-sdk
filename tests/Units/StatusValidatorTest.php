<?php

namespace Tests\Units;

use PHPUnit\Framework\TestCase;
use VoltTest\Validators\StatusValidator;
use VoltTest\Validators\Validator;

class StatusValidatorTest extends TestCase
{
    private StatusValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new StatusValidator('success', 200);
    }

    public function testImplementsValidatorInterface(): void
    {
        $this->assertInstanceOf(Validator::class, $this->validator);
    }

    public function testBasicValidatorCreation(): void
    {
        $expected = [
            'name' => 'success',
            'expected' => 200,
            'type' => 'status_code',
        ];

        $this->assertEquals($expected, $this->validator->toArray());
    }

    public function testDifferentStatusCodes(): void
    {
        $testCases = [
            ['created', 201],
            ['not_found', 404],
            ['server_error', 500],
        ];

        foreach ($testCases as [$name, $code]) {
            $validator = new StatusValidator($name, $code);
            $result = $validator->toArray();

            $this->assertEquals($name, $result['name']);
            $this->assertEquals($code, $result['expected']);
            $this->assertEquals('status_code', $result['type']);
        }
    }

    public function testCustomStatusValidation(): void
    {
        $validator = new StatusValidator('custom_error', 418); // I'm a teapot!
        $result = $validator->toArray();

        $this->assertEquals('custom_error', $result['name']);
        $this->assertEquals(418, $result['expected']);
        $this->assertEquals('status_code', $result['type']);
    }

    public function testMultipleValidators(): void
    {
        $validator1 = new StatusValidator('success', 200);
        $validator2 = new StatusValidator('error', 400);

        $result1 = $validator1->toArray();
        $result2 = $validator2->toArray();

        $this->assertEquals('success', $result1['name']);
        $this->assertEquals(200, $result1['expected']);
        $this->assertEquals('error', $result2['name']);
        $this->assertEquals(400, $result2['expected']);
    }
}
