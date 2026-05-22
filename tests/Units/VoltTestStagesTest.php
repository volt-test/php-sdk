<?php

namespace Tests\Units;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use VoltTest\Exceptions\ErrorHandler;
use VoltTest\Exceptions\VoltTestException;
use VoltTest\VoltTest;

class VoltTestStagesTest extends TestCase
{
    private VoltTest $voltTest;

    protected function setUp(): void
    {
        $this->voltTest = new VoltTest('Stages Test', 'Testing stages');
    }

    protected function tearDown(): void
    {
        ErrorHandler::unregister();
        parent::tearDown();
    }

    public function testStageReturnsSelf(): void
    {
        $result = $this->voltTest->stage('5m', 100);

        $this->assertSame($this->voltTest, $result);
    }

    public function testSingleStage(): void
    {
        $this->voltTest->stage('5m', 100);

        $config = $this->getPrivateProperty($this->voltTest, 'config');

        $this->assertTrue($config->hasStages());
    }

    public function testMultipleStagesChaining(): void
    {
        $result = $this->voltTest
            ->stage('2m', 10)
            ->stage('5m', 50)
            ->stage('3m', 100)
            ->stage('2m', 0);

        $this->assertSame($this->voltTest, $result);

        $config = $this->getPrivateProperty($this->voltTest, 'config');
        $array = $config->toArray();
        $this->assertCount(4, $array['stages']);
    }

    public function testStageWithZeroTarget(): void
    {
        $this->voltTest->stage('2m', 0);

        $config = $this->getPrivateProperty($this->voltTest, 'config');
        $array = $config->toArray();
        $this->assertEquals(0, $array['stages'][0]['target']);
    }

    public function testStageThrowsOnInvalidDuration(): void
    {
        $this->expectException(VoltTestException::class);
        $this->expectExceptionMessage('Invalid stage duration format');

        $this->voltTest->stage('invalid', 10);
    }

    public function testStageThrowsOnNegativeTarget(): void
    {
        $this->expectException(VoltTestException::class);
        $this->expectExceptionMessage('Stage target must be non-negative');

        $this->voltTest->stage('5m', -1);
    }

    // --- Stages auto-clear constant load when adding first stage ---

    public function testStageClearsConstantLoadAfterSetVirtualUsers(): void
    {
        $this->voltTest->setVirtualUsers(10);

        $result = $this->voltTest->stage('5m', 100);

        $this->assertSame($this->voltTest, $result);
        $this->assertTrue($this->voltTest->hasStages());
    }

    public function testStageClearsConstantLoadAfterSetDuration(): void
    {
        $this->voltTest->setDuration('5m');

        $result = $this->voltTest->stage('5m', 100);

        $this->assertSame($this->voltTest, $result);
        $this->assertTrue($this->voltTest->hasStages());
    }

    public function testStageClearsConstantLoadAfterSetRampUp(): void
    {
        $this->voltTest->setRampUp('10s');

        $result = $this->voltTest->stage('5m', 100);

        $this->assertSame($this->voltTest, $result);
        $this->assertTrue($this->voltTest->hasStages());
    }

    // --- Mutual exclusivity: constant load after stages ---

    public function testSetVirtualUsersThrowsAfterStage(): void
    {
        $this->voltTest->stage('5m', 100);

        $this->expectException(VoltTestException::class);
        $this->expectExceptionMessage('Cannot use setVirtualUsers with stages');

        $this->voltTest->setVirtualUsers(10);
    }

    public function testSetDurationThrowsAfterStage(): void
    {
        $this->voltTest->stage('5m', 100);

        $this->expectException(VoltTestException::class);
        $this->expectExceptionMessage('Cannot use setDuration with stages');

        $this->voltTest->setDuration('5m');
    }

    public function testSetRampUpThrowsAfterStage(): void
    {
        $this->voltTest->stage('5m', 100);

        $this->expectException(VoltTestException::class);
        $this->expectExceptionMessage('Cannot use setRampUp with stages');

        $this->voltTest->setRampUp('10s');
    }

    // --- setHttpTimeout ---

    public function testSetHttpTimeoutReturnsSelf(): void
    {
        $result = $this->voltTest->setHttpTimeout('60s');

        $this->assertSame($this->voltTest, $result);
    }

    #[DataProvider('validHttpTimeoutProvider')]
    public function testSetHttpTimeoutWithValidValues(string $timeout): void
    {
        $this->voltTest->setHttpTimeout($timeout);

        $config = $this->getPrivateProperty($this->voltTest, 'config');
        $this->assertEquals($timeout, $config->toArray()['http_timeout']);
    }

    public static function validHttpTimeoutProvider(): array
    {
        return [
            ['30s'],
            ['60s'],
            ['1m'],
            ['5m'],
            ['1h'],
        ];
    }

    #[DataProvider('invalidHttpTimeoutProvider')]
    public function testSetHttpTimeoutThrowsOnInvalidValues(string $timeout): void
    {
        $this->expectException(VoltTestException::class);
        $this->expectExceptionMessage('Invalid HTTP timeout format');

        $this->voltTest->setHttpTimeout($timeout);
    }

    public static function invalidHttpTimeoutProvider(): array
    {
        return [
            [''],
            ['10'],
            ['s'],
            ['1x'],
            ['30min'],
            ['-1s'],
            ['1.5h'],
        ];
    }

    // --- setTarget (idle timeout) ---

    public function testSetTargetReturnsSelf(): void
    {
        $result = $this->voltTest->setTarget('60s');

        $this->assertSame($this->voltTest, $result);
    }

    public function testSetTargetUpdatesIdleTimeout(): void
    {
        $this->voltTest->setTarget('2m');

        $config = $this->getPrivateProperty($this->voltTest, 'config');
        $array = $config->toArray();
        $this->assertEquals('2m', $array['target']['idle_timeout']);
    }

    public function testSetTargetThrowsOnInvalidFormat(): void
    {
        $this->expectException(VoltTestException::class);
        $this->expectExceptionMessage('Invalid idle timeout format');

        $this->voltTest->setTarget('invalid');
    }

    // --- Stages combined with non-exclusive settings ---

    public function testStagesWithHttpTimeout(): void
    {
        $this->voltTest
            ->stage('2m', 10)
            ->stage('5m', 50)
            ->setHttpTimeout('60s');

        $config = $this->getPrivateProperty($this->voltTest, 'config');
        $array = $config->toArray();

        $this->assertCount(2, $array['stages']);
        $this->assertEquals('60s', $array['http_timeout']);
    }

    public function testStagesWithTarget(): void
    {
        $this->voltTest
            ->stage('5m', 100)
            ->setTarget('2m');

        $config = $this->getPrivateProperty($this->voltTest, 'config');
        $array = $config->toArray();

        $this->assertCount(1, $array['stages']);
        $this->assertEquals('2m', $array['target']['idle_timeout']);
    }

    public function testStagesWithHttpDebug(): void
    {
        $this->voltTest
            ->stage('5m', 100)
            ->setHttpDebug(true);

        $config = $this->getPrivateProperty($this->voltTest, 'config');
        $array = $config->toArray();

        $this->assertCount(1, $array['stages']);
        $this->assertTrue($array['http_debug']);
    }

    // --- Typical ramp-up / ramp-down pattern ---

    public function testTypicalRampUpRampDownPattern(): void
    {
        $this->voltTest
            ->stage('2m', 10)
            ->stage('5m', 50)
            ->stage('10m', 100)
            ->stage('5m', 50)
            ->stage('2m', 0);

        $config = $this->getPrivateProperty($this->voltTest, 'config');
        $array = $config->toArray();

        $this->assertCount(5, $array['stages']);
        $this->assertEquals(10, $array['stages'][0]['target']);
        $this->assertEquals(100, $array['stages'][2]['target']);
        $this->assertEquals(0, $array['stages'][4]['target']);
    }

    private function getPrivateProperty(object $object, string $propertyName): mixed
    {
        $reflection = new \ReflectionClass(get_class($object));
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);

        return $property->getValue($object);
    }
}
