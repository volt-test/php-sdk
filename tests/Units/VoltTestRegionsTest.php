<?php

namespace Tests\Units;

use PHPUnit\Framework\TestCase;
use VoltTest\Exceptions\VoltTestException;
use VoltTest\VoltTest;

class VoltTestRegionsTest extends TestCase
{
    private VoltTest $test;

    public function setUp(): void
    {
        $this->test = new VoltTest('Region Test', 'Testing region distribution');
    }

    public function testRegionsReturnsSelf(): void
    {
        $result = $this->test->regions(['us-east-1' => 100]);
        $this->assertInstanceOf(VoltTest::class, $result);
    }

    public function testRegionsWithSingleRegion(): void
    {
        $this->test->regions(['us-east-1' => 100]);
        $this->assertInstanceOf(VoltTest::class, $this->test);
    }

    public function testRegionsWithMultipleRegions(): void
    {
        $this->test->regions(['us-east-1' => 60, 'eu-west-1' => 40]);
        $this->assertInstanceOf(VoltTest::class, $this->test);
    }

    public function testRegionsThrowsOnEmptyArray(): void
    {
        $this->expectException(VoltTestException::class);
        $this->expectExceptionMessage('Region distribution cannot be empty');
        $this->test->regions([]);
    }

    public function testRegionsThrowsWhenWeightsNotSumTo100(): void
    {
        $this->expectException(VoltTestException::class);
        $this->expectExceptionMessage('Region weights must sum to 100');
        $this->test->regions(['us-east-1' => 50, 'eu-west-1' => 30]);
    }

    public function testRegionsThrowsOnInvalidWeight(): void
    {
        $this->expectException(VoltTestException::class);
        $this->expectExceptionMessage('Region weight must be greater than 0');
        $this->test->regions(['us-east-1' => 0, 'eu-west-1' => 100]);
    }

    public function testRegionsThrowsOnEmptyRegionCode(): void
    {
        $this->expectException(VoltTestException::class);
        $this->expectExceptionMessage('Region code must be a non-empty string');
        $this->test->regions(['' => 100]);
    }

    public function testRegionsFluentChaining(): void
    {
        $result = $this->test
            ->setVirtualUsers(100)
            ->setDuration('5m')
            ->regions(['us-east-1' => 60, 'eu-west-1' => 40]);

        $this->assertInstanceOf(VoltTest::class, $result);
    }

    public function testRegionsWithStages(): void
    {
        $result = $this->test
            ->stage('1m', 50)
            ->stage('5m', 100)
            ->regions(['us-east-1' => 60, 'eu-west-1' => 40]);

        $this->assertInstanceOf(VoltTest::class, $result);
    }

    public function testRunThrowsWhenRegionsSetWithoutCloud(): void
    {
        $this->test->regions(['us-east-1' => 100]);
        $this->test->scenario('Test')->step('Step')->get('http://localhost');

        $this->expectException(VoltTestException::class);
        $this->expectExceptionMessage('Region distribution requires cloud execution mode');
        $this->test->run();
    }
}
