<?php

namespace Tests\Units;

use PHPUnit\Framework\TestCase;
use VoltTest\Configuration;
use VoltTest\Exceptions\VoltTestException;

class ConfigurationRegionsTest extends TestCase
{
    private Configuration $config;

    public function setUp(): void
    {
        $this->config = new Configuration('Test', 'Test Description');
    }

    public function testHasRegionsReturnsFalseByDefault(): void
    {
        $this->assertFalse($this->config->hasRegions());
    }

    public function testSetRegionsStoresRegions(): void
    {
        $this->config->setRegions(['us-east-1' => 60, 'eu-west-1' => 40]);
        $this->assertTrue($this->config->hasRegions());
    }

    public function testSetRegionsReturnsSelf(): void
    {
        $result = $this->config->setRegions(['us-east-1' => 100]);
        $this->assertInstanceOf(Configuration::class, $result);
    }

    public function testToArrayOmitsRegionConfigWhenNotSet(): void
    {
        $array = $this->config->toArray();
        $this->assertArrayNotHasKey('region_config', $array);
    }

    public function testToArrayIncludesRegionConfigWhenSet(): void
    {
        $this->config->setRegions(['us-east-1' => 100]);
        $array = $this->config->toArray();

        $this->assertArrayHasKey('region_config', $array);
        $this->assertEquals([
            ['region' => 'us-east-1', 'weight' => 100],
        ], $array['region_config']);
    }

    public function testToArraySerializesMultipleRegions(): void
    {
        $this->config->setRegions(['us-east-1' => 60, 'eu-west-1' => 40]);
        $array = $this->config->toArray();

        $this->assertEquals([
            ['region' => 'us-east-1', 'weight' => 60],
            ['region' => 'eu-west-1', 'weight' => 40],
        ], $array['region_config']);
    }

    public function testSetRegionsReplacesOnSecondCall(): void
    {
        $this->config->setRegions(['us-east-1' => 100]);
        $this->config->setRegions(['eu-west-1' => 70, 'ap-southeast-1' => 30]);

        $array = $this->config->toArray();
        $this->assertEquals([
            ['region' => 'eu-west-1', 'weight' => 70],
            ['region' => 'ap-southeast-1', 'weight' => 30],
        ], $array['region_config']);
    }

    public function testRegionsWorkWithStages(): void
    {
        $this->config->addStage('5m', 100);
        $this->config->setRegions(['us-east-1' => 60, 'eu-west-1' => 40]);

        $array = $this->config->toArray();
        $this->assertArrayHasKey('stages', $array);
        $this->assertArrayHasKey('region_config', $array);
    }

    public function testRegionsWorkWithConstantLoad(): void
    {
        $this->config->setVirtualUsers(50);
        $this->config->setRegions(['us-east-1' => 60, 'eu-west-1' => 40]);

        $array = $this->config->toArray();
        $this->assertEquals(50, $array['virtual_users']);
        $this->assertArrayHasKey('region_config', $array);
    }

    public function testSetRegionsThrowsOnEmptyArray(): void
    {
        $this->expectException(VoltTestException::class);
        $this->expectExceptionMessage('Region distribution cannot be empty');
        $this->config->setRegions([]);
    }

    public function testSetRegionsThrowsWhenWeightsSumNot100(): void
    {
        $this->expectException(VoltTestException::class);
        $this->expectExceptionMessage('Region weights must sum to 100, got 80');
        $this->config->setRegions(['us-east-1' => 50, 'eu-west-1' => 30]);
    }

    public function testSetRegionsThrowsOnZeroWeight(): void
    {
        $this->expectException(VoltTestException::class);
        $this->expectExceptionMessage('Region weight must be greater than 0');
        $this->config->setRegions(['us-east-1' => 0, 'eu-west-1' => 100]);
    }

    public function testSetRegionsThrowsOnNegativeWeight(): void
    {
        $this->expectException(VoltTestException::class);
        $this->expectExceptionMessage('Region weight must be greater than 0');
        $this->config->setRegions(['us-east-1' => -10, 'eu-west-1' => 110]);
    }

    public function testSetRegionsThrowsOnEmptyRegionCode(): void
    {
        $this->expectException(VoltTestException::class);
        $this->expectExceptionMessage('Region code must be a non-empty string');
        $this->config->setRegions(['' => 100]);
    }

    public function testSetRegionsThrowsOnWhitespaceOnlyRegionCode(): void
    {
        $this->expectException(VoltTestException::class);
        $this->expectExceptionMessage('Region code must be a non-empty string');
        $this->config->setRegions(['  ' => 100]);
    }

    public function testSetRegionsThrowsOnNonIntegerWeight(): void
    {
        $this->expectException(VoltTestException::class);
        $this->expectExceptionMessage('Region weight must be an integer');
        $this->config->setRegions(['us-east-1' => 60.5, 'eu-west-1' => 39.5]);
    }
}
