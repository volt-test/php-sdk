<?php

namespace Tests\Units;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use VoltTest\Configuration;
use VoltTest\Exceptions\VoltTestException;

class ConfigurationStagesTest extends TestCase
{
    private Configuration $config;

    public function setUp(): void
    {
        $this->config = new Configuration('Test', 'Test Description');
    }

    public function testHasStagesReturnsFalseByDefault(): void
    {
        $this->assertFalse($this->config->hasStages());
    }

    public function testHasStagesReturnsTrueAfterAddingStage(): void
    {
        $this->config->addStage('5m', 100);

        $this->assertTrue($this->config->hasStages());
    }

    public function testHasConstantLoadReturnsFalseByDefault(): void
    {
        $this->assertFalse($this->config->hasConstantLoad());
    }

    public function testHasConstantLoadReturnsTrueWithVirtualUsers(): void
    {
        $this->config->setVirtualUsers(10);

        $this->assertTrue($this->config->hasConstantLoad());
    }

    public function testHasConstantLoadReturnsTrueWithDuration(): void
    {
        $this->config->setDuration('5m');

        $this->assertTrue($this->config->hasConstantLoad());
    }

    public function testHasConstantLoadReturnsTrueWithRampUp(): void
    {
        $this->config->setRampUp('10s');

        $this->assertTrue($this->config->hasConstantLoad());
    }

    public function testHasConstantLoadFalseWithOneVirtualUser(): void
    {
        $this->config->setVirtualUsers(1);

        $this->assertFalse($this->config->hasConstantLoad());
    }

    public function testAddStageReturnsSelf(): void
    {
        $result = $this->config->addStage('5m', 100);

        $this->assertSame($this->config, $result);
    }

    public function testAddMultipleStages(): void
    {
        $this->config
            ->addStage('2m', 10)
            ->addStage('5m', 50)
            ->addStage('2m', 0);

        $this->assertTrue($this->config->hasStages());

        $array = $this->config->toArray();
        $this->assertCount(3, $array['stages']);
    }

    public function testToArrayWithStagesOmitsConstantLoadFields(): void
    {
        $this->config->addStage('5m', 100);

        $array = $this->config->toArray();

        $this->assertArrayHasKey('stages', $array);
        $this->assertArrayNotHasKey('virtual_users', $array);
        $this->assertArrayNotHasKey('duration', $array);
        $this->assertArrayNotHasKey('ramp_up', $array);
    }

    public function testToArrayWithStagesSerializesCorrectly(): void
    {
        $this->config
            ->addStage('2m', 10)
            ->addStage('5m', 50)
            ->addStage('2m', 0);

        $array = $this->config->toArray();

        $this->assertEquals([
            ['duration' => '2m', 'target' => 10],
            ['duration' => '5m', 'target' => 50],
            ['duration' => '2m', 'target' => 0],
        ], $array['stages']);
    }

    public function testToArrayWithoutStagesIncludesConstantLoadFields(): void
    {
        $this->config
            ->setVirtualUsers(10)
            ->setDuration('5m')
            ->setRampUp('30s');

        $array = $this->config->toArray();

        $this->assertArrayNotHasKey('stages', $array);
        $this->assertEquals(10, $array['virtual_users']);
        $this->assertEquals('5m', $array['duration']);
        $this->assertEquals('30s', $array['ramp_up']);
    }

    public function testToArrayWithoutDurationOmitsDurationField(): void
    {
        $array = $this->config->toArray();

        $this->assertArrayNotHasKey('duration', $array);
    }

    public function testToArrayWithoutRampUpOmitsRampUpField(): void
    {
        $array = $this->config->toArray();

        $this->assertArrayNotHasKey('ramp_up', $array);
    }

    public function testAddStageWithInvalidDurationThrows(): void
    {
        $this->expectException(VoltTestException::class);

        $this->config->addStage('invalid', 10);
    }

    public function testAddStageWithNegativeTargetThrows(): void
    {
        $this->expectException(VoltTestException::class);

        $this->config->addStage('5m', -1);
    }

    #[DataProvider('validHttpTimeoutProvider')]
    public function testSetHttpTimeout(string $timeout): void
    {
        $this->config->setHttpTimeout($timeout);

        $array = $this->config->toArray();
        $this->assertEquals($timeout, $array['http_timeout']);
    }

    public static function validHttpTimeoutProvider(): array
    {
        return [
            ['30s'],
            ['1m'],
            ['60s'],
            ['2m'],
            ['1h'],
        ];
    }

    #[DataProvider('invalidHttpTimeoutProvider')]
    public function testSetInvalidHttpTimeoutThrows(string $timeout): void
    {
        $this->expectException(VoltTestException::class);
        $this->expectExceptionMessage('Invalid HTTP timeout format. Use <number>[s|m|h]');

        $this->config->setHttpTimeout($timeout);
    }

    public static function invalidHttpTimeoutProvider(): array
    {
        return [
            [''],
            ['10'],
            ['s'],
            ['1x'],
            ['-1s'],
        ];
    }

    public function testHttpTimeoutOmittedWhenNotSet(): void
    {
        $array = $this->config->toArray();

        $this->assertArrayNotHasKey('http_timeout', $array);
    }

    public function testHttpTimeoutIncludedWithStages(): void
    {
        $this->config
            ->addStage('5m', 100)
            ->setHttpTimeout('60s');

        $array = $this->config->toArray();

        $this->assertArrayHasKey('stages', $array);
        $this->assertEquals('60s', $array['http_timeout']);
    }
}
