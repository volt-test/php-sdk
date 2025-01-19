<?php

namespace Tests\Units;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use VoltTest\Configuration;
use VoltTest\Exceptions\VoltTestException;

class ConfigurationTest extends TestCase
{
    private Configuration $config;

    public function setUp(): void
    {
        $this->config = new Configuration('Test', 'Test Description');
    }

    public function testConstructorAndDefaults(): void
    {
        $configArray = $this->config->toArray();

        $this->assertEquals('Test', $configArray['name']);
        $this->assertEquals('Test Description', $configArray['description']);
        $this->assertEquals(1, $configArray['virtual_users']);
        $this->assertFalse($configArray['http_debug']);
        $this->assertEquals([
            'url' => 'https://example.com',
            'idle_timeout' => '30s',
        ], $configArray['target']);
    }

    public function testEmptyDescription(): void
    {
        $config = new Configuration('Test');
        $this->assertEquals('', $config->toArray()['description']);
    }

    #[DataProvider('validVirtualUsersProvider')]
    public function testSetVirtualUsers(int $users): void
    {
        $this->config->setVirtualUsers(10);
        $this->assertEquals(10, $this->config->toArray()['virtual_users']);
    }

    public static function validVirtualUsersProvider(): array
    {
        return [
            [1],
            [10],
            [100],
            [1000],
            [PHP_INT_MAX],
        ];
    }

    #[DataProvider('invalidVirtualUsersProvider')]
    public function testSetInvalidVirtualUsers(int $users): void
    {
        $this->expectException(VoltTestException::class);
        $this->expectExceptionMessage('Virtual users count must be at least 1');
        $this->config->setVirtualUsers($users);
    }

    public static function invalidVirtualUsersProvider(): array
    {
        return [
            [0],
            [-1],
            [-100],
        ];
    }

    #[DataProvider('validDurationProvider')]
    public function testSetValidDuration(string $duration): void
    {
        $this->config->setDuration($duration);
        $this->assertEquals($duration, $this->config->toArray()['duration']);
    }

    public static function validDurationProvider(): array
    {
        return [
            ['1s'],
            ['30s'],
            ['1m'],
            ['60m'],
            ['1h'],
            ['24h'],
        ];
    }

    #[DataProvider('invalidDurationProvider')]
    public function testSetInvalidDuration(string $duration): void
    {
        $this->expectException(VoltTestException::class);
        $this->expectExceptionMessage('Invalid duration format. Use <number>[s|m|h]');
        $this->config->setDuration($duration);
    }

    public static function invalidDurationProvider(): array
    {
        return [
            [''],
            ['1'],
            ['s'],
            ['1x'],
            ['30min'],
            ['1hour'],
            ['-1s'],
            ['1.5h'],
        ];
    }

    #[DataProvider('validRampUpProvider')]
    public function testSetValidRampUp(string $rampUp): void
    {
        $this->config->setRampUp($rampUp);
        $this->assertEquals($rampUp, $this->config->toArray()['ramp_up']);
    }

    public static function validRampUpProvider(): array
    {
        return [
            ['0s'],
            ['30s'],
            ['1m'],
            ['5m'],
            ['1h'],
        ];
    }

    #[DataProvider('invalidRampUpProvider')]
    public function testSetInvalidRampUp(string $rampUp): void
    {
        $this->expectException(VoltTestException::class);
        $this->expectExceptionMessage('Invalid ramp-up format. Use <number>[s|m|h]');
        $this->config->setRampUp($rampUp);
    }

    public static function invalidRampUpProvider(): array
    {
        return [
            [''],
            ['1'],
            ['s'],
            ['1x'],
            ['30min'],
            ['1hour'],
            ['-1s'],
            ['1.5h'],
        ];
    }

    #[DataProvider('validTargetProvider')]
    public function testSetValidTarget(string $timeout): void
    {
        $this->config->setTarget($timeout);

        $configArray = $this->config->toArray();
        $expectedTarget = [
            'url' => 'https://example.com',
            'idle_timeout' => $timeout,
        ];

        $this->assertEquals($expectedTarget, $configArray['target']);
    }

    public static function validTargetProvider(): array
    {
        return [
            ['30s'],
            ['1s'],
            ['1m'],
            ['60s'],
            ['1m'],
            ['2h'],
        ];
    }

    #[DataProvider('invalidTargetUrlProvider')]
    public function testSetInvalidTargetUrl(string $url): void
    {
        $this->expectException(VoltTestException::class);
        $this->config->setTarget($url);
    }

    public static function invalidTargetUrlProvider(): array
    {
        return [
            [''],
            ['not-a-url'],
            ['ftp://example.com'],
            ['example.com'],
            ['http://'],
            ['https://'],
        ];
    }

    #[DataProvider('invalidTargetTimeoutProvider')]
    public function testSetInvalidTargetTimeout(string $timeout): void
    {
        $this->expectException(VoltTestException::class);
        $this->config->setTarget($timeout);
    }

    public static function invalidTargetTimeoutProvider(): array
    {
        return [
            [''],
            ['1'],
            ['s'],
            ['1x'],
            ['30min'],
            ['1hour'],
            ['-1s'],
            ['1.5h'],
        ];
    }

    public function testFluentInterface(): void
    {
        $result = $this->config
            ->setVirtualUsers(10)
            ->setDuration('5m')
            ->setRampUp('30s')
            ->setTarget('1m');

        $this->assertInstanceOf(Configuration::class, $result);

        $configArray = $result->toArray();
        $this->assertEquals(10, $configArray['virtual_users']);
        $this->assertEquals('5m', $configArray['duration']);
        $this->assertEquals('30s', $configArray['ramp_up']);
        $this->assertEquals([
            'url' => 'https://example.com',
            'idle_timeout' => '1m',
        ], $configArray['target']);
    }

    public function testValidRamUp(): void
    {
        $this->config->setRampUp('1s');
        $configArray = $this->config->toArray();
        $this->assertEquals('1s', $configArray['ramp_up']);
    }

    public function testInvalidRampUpThrowsException(): void
    {
        $this->expectException(VoltTestException::class);
        $this->config->setRampUp('1');
    }

    public function testValidDuration(): void
    {
        $this->config->setDuration('1s');
        $configArray = $this->config->toArray();
        $this->assertEquals('1s', $configArray['duration']);
    }

    public function testInvalidDurationThrowsException(): void
    {
        $this->expectException(VoltTestException::class);
        $this->config->setDuration('1');
    }

    public function testHttpDebug(): void
    {
        $this->config->setHttpDebug(true);
        $configArray = $this->config->toArray();
        $this->assertTrue($configArray['http_debug']);
    }
}
