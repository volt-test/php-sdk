<?php

namespace Tests\Units;

use PHPUnit\Framework\TestCase;
use VoltTest\CloudClient;
use VoltTest\CloudRun;
use VoltTest\Exceptions\CloudTimeoutException;
use VoltTest\Exceptions\ErrorHandler;
use VoltTest\Exceptions\RunFailedException;
use VoltTest\Exceptions\VoltTestException;
use VoltTest\ProcessManager;
use VoltTest\TestResult;
use VoltTest\VoltTest;

class TestableVoltTest extends VoltTest
{
    public ?CloudClient $mockClient = null;

    public int $pollInterval = 0;

    public function setTestCloudTimeout(int $seconds): void
    {
        $reflection = new \ReflectionClass(VoltTest::class);
        $property = $reflection->getProperty('cloudTimeout');
        $property->setAccessible(true);
        $property->setValue($this, $seconds);
    }

    protected function createCloudClient(): CloudClient
    {
        return $this->mockClient;
    }
}

class VoltTestCloudTest extends TestCase
{
    private VoltTest $voltTest;

    protected function setUp(): void
    {
        $this->voltTest = new VoltTest('Cloud Test Suite', 'Testing cloud features');
    }

    protected function tearDown(): void
    {
        ErrorHandler::unregister();
        parent::tearDown();
    }

    public function testCloudSetsApiKey(): void
    {
        $this->voltTest->cloud('vt_test_key_123');

        $this->assertEquals('vt_test_key_123', $this->getPrivateProperty($this->voltTest, 'cloudApiKey'));
    }

    public function testCloudReturnsSelf(): void
    {
        $result = $this->voltTest->cloud('vt_test_key_123');

        $this->assertSame($this->voltTest, $result);
    }

    public function testCloudThrowsOnEmptyKey(): void
    {
        $this->expectException(VoltTestException::class);
        $this->expectExceptionMessage('API key is required');

        $this->voltTest->cloud('');
    }

    public function testCloudThrowsOnInvalidPrefix(): void
    {
        $this->expectException(VoltTestException::class);
        $this->expectExceptionMessage('API key must start with "vt_"');

        $this->voltTest->cloud('invalid_key');
    }

    public function testSetCloudTimeoutSetsValue(): void
    {
        $this->voltTest->setCloudTimeout(120);

        $this->assertEquals(120, $this->getPrivateProperty($this->voltTest, 'cloudTimeout'));
    }

    public function testSetCloudTimeoutReturnsSelf(): void
    {
        $result = $this->voltTest->setCloudTimeout(120);

        $this->assertSame($this->voltTest, $result);
    }

    public function testSetCloudTimeoutClampsToMinimum60(): void
    {
        $this->voltTest->setCloudTimeout(30);

        $this->assertEquals(60, $this->getPrivateProperty($this->voltTest, 'cloudTimeout'));
    }

    public function testSetCloudTimeoutAccepts60(): void
    {
        $this->voltTest->setCloudTimeout(60);

        $this->assertEquals(60, $this->getPrivateProperty($this->voltTest, 'cloudTimeout'));
    }

    public function testDefaultCloudTimeoutIs1800(): void
    {
        $this->assertEquals(1800, $this->getPrivateProperty($this->voltTest, 'cloudTimeout'));
    }

    public function testParseDurationSeconds(): void
    {
        $method = new \ReflectionMethod(VoltTest::class, 'parseDurationToSeconds');
        $method->setAccessible(true);

        $this->assertEquals(30, $method->invoke($this->voltTest, '30s'));
    }

    public function testParseDurationMinutes(): void
    {
        $method = new \ReflectionMethod(VoltTest::class, 'parseDurationToSeconds');
        $method->setAccessible(true);

        $this->assertEquals(300, $method->invoke($this->voltTest, '5m'));
    }

    public function testParseDurationHours(): void
    {
        $method = new \ReflectionMethod(VoltTest::class, 'parseDurationToSeconds');
        $method->setAccessible(true);

        $this->assertEquals(3600, $method->invoke($this->voltTest, '1h'));
    }

    public function testParseDurationInvalid(): void
    {
        $method = new \ReflectionMethod(VoltTest::class, 'parseDurationToSeconds');
        $method->setAccessible(true);

        $this->assertEquals(0, $method->invoke($this->voltTest, 'invalid'));
    }

    public function testParseDurationEmpty(): void
    {
        $method = new \ReflectionMethod(VoltTest::class, 'parseDurationToSeconds');
        $method->setAccessible(true);

        $this->assertEquals(0, $method->invoke($this->voltTest, ''));
    }

    public function testParseDurationMissingUnit(): void
    {
        $method = new \ReflectionMethod(VoltTest::class, 'parseDurationToSeconds');
        $method->setAccessible(true);

        $this->assertEquals(0, $method->invoke($this->voltTest, '10'));
    }

    public function testParseDurationZero(): void
    {
        $method = new \ReflectionMethod(VoltTest::class, 'parseDurationToSeconds');
        $method->setAccessible(true);

        $this->assertEquals(0, $method->invoke($this->voltTest, '0s'));
    }

    public function testRunRoutesToLocalWhenNoCloudKey(): void
    {
        $mockProcessManager = $this->createMock(ProcessManager::class);
        $mockProcessManager->expects($this->once())
            ->method('execute')
            ->willReturn($this->getSampleOutput());

        $this->setPrivateProperty($this->voltTest, 'processManager', $mockProcessManager);

        $this->voltTest
            ->setVirtualUsers(1)
            ->setDuration('1s')
            ->setTarget('40s');

        $this->voltTest->scenario('Simple Test')
            ->step('Homepage')
            ->get('http://example.com')
            ->validateStatus('success', 200);

        $result = $this->voltTest->run(false);

        $this->assertInstanceOf(TestResult::class, $result);
    }

    public function testRunRoutesToCloudWhenKeySet(): void
    {
        $testable = new TestableVoltTest('Cloud Route Test');
        $testable->setCloudTimeout(60);

        $mockClient = $this->createMock(CloudClient::class);
        $mockClient->expects($this->once())
            ->method('createTest')
            ->willReturn(['id' => 'test-1']);
        $mockClient->expects($this->once())
            ->method('startRun')
            ->with('test-1')
            ->willReturn(['id' => 'run-1']);
        $mockClient->expects($this->once())
            ->method('getRunStatus')
            ->with('run-1')
            ->willReturn(['status' => 'completed']);

        $testable->mockClient = $mockClient;

        $testable->cloud('vt_test_key_123')
            ->setVirtualUsers(1)
            ->setDuration('1s');

        $testable->scenario('Test')
            ->step('Step')
            ->get('http://example.com')
            ->validateStatus('success', 200);

        ob_start();
        $result = $testable->run(false);
        ob_end_clean();

        $this->assertInstanceOf(CloudRun::class, $result);
    }

    public function testRunCloudCompletedSuccessfully(): void
    {
        $testable = $this->createTestableVoltTest();

        $mockClient = $this->createMock(CloudClient::class);
        $mockClient->method('createTest')->willReturn(['id' => 'test-1']);
        $mockClient->method('startRun')->willReturn(['id' => 'run-1']);
        $mockClient->method('getRunStatus')->willReturn(['status' => 'completed']);

        $testable->mockClient = $mockClient;

        ob_start();
        $result = $testable->run(false);
        ob_end_clean();

        $this->assertInstanceOf(CloudRun::class, $result);
        $this->assertTrue($result->isSuccessful());
        $this->assertEquals('run-1', $result->getRunId());
        $this->assertEquals('test-1', $result->getTestId());
        $this->assertEquals('completed', $result->getStatus());
    }

    public function testRunCloudOutputContainsDashboardUrl(): void
    {
        $testable = $this->createTestableVoltTest();

        $mockClient = $this->createMock(CloudClient::class);
        $mockClient->method('createTest')->willReturn(['id' => 'test-1']);
        $mockClient->method('startRun')->willReturn(['id' => 'run-abc']);
        $mockClient->method('getRunStatus')->willReturn(['status' => 'completed']);

        $testable->mockClient = $mockClient;

        ob_start();
        $testable->run(false);
        $output = ob_get_clean();

        $this->assertStringContainsString('https://app.volt-test.com/runs/run-abc', $output);
        $this->assertStringContainsString('Test completed', $output);
    }

    public function testRunCloudFailedThrowsRunFailedException(): void
    {
        $testable = $this->createTestableVoltTest();

        $mockClient = $this->createMock(CloudClient::class);
        $mockClient->method('createTest')->willReturn(['id' => 'test-1']);
        $mockClient->method('startRun')->willReturn(['id' => 'run-1']);
        $mockClient->method('getRunStatus')->willReturn([
            'status' => 'failed',
            'error_message' => 'Out of memory',
        ]);

        $testable->mockClient = $mockClient;

        $this->expectException(RunFailedException::class);
        $this->expectExceptionMessage('Cloud run failed: Out of memory');

        ob_start();

        try {
            $testable->run(false);
        } finally {
            ob_end_clean();
        }
    }

    public function testRunCloudStoppedThrowsRunFailedException(): void
    {
        $testable = $this->createTestableVoltTest();

        $mockClient = $this->createMock(CloudClient::class);
        $mockClient->method('createTest')->willReturn(['id' => 'test-1']);
        $mockClient->method('startRun')->willReturn(['id' => 'run-1']);
        $mockClient->method('getRunStatus')->willReturn(['status' => 'stopped']);

        $testable->mockClient = $mockClient;

        $this->expectException(RunFailedException::class);
        $this->expectExceptionMessage('Cloud run was stopped');

        ob_start();

        try {
            $testable->run(false);
        } finally {
            ob_end_clean();
        }
    }

    public function testRunCloudTimeoutThrowsCloudTimeoutException(): void
    {
        $testable = new TestableVoltTest('Timeout Test');
        $testable->pollInterval = 1;
        $testable->setTestCloudTimeout(2);
        $testable->cloud('vt_test_key_123')
            ->setVirtualUsers(1)
            ->setDuration('1s');

        $testable->scenario('Test')
            ->step('Step')
            ->get('http://example.com')
            ->validateStatus('success', 200);

        $mockClient = $this->createMock(CloudClient::class);
        $mockClient->method('createTest')->willReturn(['id' => 'test-1']);
        $mockClient->method('startRun')->willReturn(['id' => 'run-1']);
        $mockClient->method('getRunStatus')->willReturn([
            'status' => 'running',
            'progress' => ['percentage' => 50, 'elapsed_seconds' => 15, 'total_seconds' => 30],
        ]);

        $testable->mockClient = $mockClient;

        $this->expectException(CloudTimeoutException::class);
        $this->expectExceptionMessage('Cloud run timed out');

        ob_start();

        try {
            $testable->run(false);
        } finally {
            ob_end_clean();
        }
    }

    public function testRunCloudBuildsCorrectTestData(): void
    {
        $testable = new TestableVoltTest('My Load Test', 'Testing the app');
        $testable->pollInterval = 0;
        $testable->setCloudTimeout(60);

        $testable->cloud('vt_test_key_123')
            ->setVirtualUsers(50)
            ->setDuration('5m');

        $testable->scenario('Homepage')
            ->step('Load page')
            ->get('http://example.com')
            ->validateStatus('success', 200);

        $capturedData = null;
        $mockClient = $this->createMock(CloudClient::class);
        $mockClient->expects($this->once())
            ->method('createTest')
            ->willReturnCallback(function (array $data) use (&$capturedData) {
                $capturedData = $data;

                return ['id' => 'test-1'];
            });
        $mockClient->method('startRun')->willReturn(['id' => 'run-1']);
        $mockClient->method('getRunStatus')->willReturn(['status' => 'completed']);

        $testable->mockClient = $mockClient;

        ob_start();
        $testable->run(false);
        ob_end_clean();

        $this->assertEquals('My Load Test', $capturedData['name']);
        $this->assertEquals('Testing the app', $capturedData['description']);
        $this->assertEquals(50, $capturedData['virtual_users']);
        $this->assertEquals(300, $capturedData['duration_seconds']);
        $this->assertArrayHasKey('test_config', $capturedData);
        $this->assertIsString($capturedData['test_config']);
    }

    private function createTestableVoltTest(): TestableVoltTest
    {
        $testable = new TestableVoltTest('Cloud Test');
        $testable->setCloudTimeout(60);
        $testable->cloud('vt_test_key_123')
            ->setVirtualUsers(1)
            ->setDuration('1s');

        $testable->scenario('Test')
            ->step('Step')
            ->get('http://example.com')
            ->validateStatus('success', 200);

        return $testable;
    }

    private function getSampleOutput(): string
    {
        return <<<'EOT'
Test Metrics Summary:
===================
Duration:     1.5s
Total Reqs:   10
Success Rate: 100.00%
Req/sec:      6.67
Success Requests: 10
Failed Requests: 0

Response Time:
------------
Min:    50ms
Max:    200ms
Avg:    100ms
Median: 95ms
P95:    180ms
P99:    195ms
EOT;
    }

    private function setPrivateProperty(object $object, string $propertyName, mixed $value): void
    {
        $reflection = new \ReflectionClass(get_class($object));
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }

    private function getPrivateProperty(object $object, string $propertyName): mixed
    {
        $reflection = new \ReflectionClass(get_class($object));
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);

        return $property->getValue($object);
    }
}
