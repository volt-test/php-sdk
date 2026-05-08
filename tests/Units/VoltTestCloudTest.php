<?php

namespace Tests\Units;

use PHPUnit\Framework\TestCase;
use VoltTest\CloudRun;
use VoltTest\Exceptions\AuthenticationException;
use VoltTest\Exceptions\CloudConnectionException;
use VoltTest\Exceptions\CloudException;
use VoltTest\Exceptions\CloudTimeoutException;
use VoltTest\Exceptions\ErrorHandler;
use VoltTest\Exceptions\PlanLimitException;
use VoltTest\Exceptions\RunFailedException;
use VoltTest\Exceptions\VoltTestException;
use VoltTest\ProcessManager;
use VoltTest\TestResult;
use VoltTest\VoltTest;

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

    public function testRunCloudAddsCloudFieldsToConfig(): void
    {
        $mockProcessManager = $this->createMock(ProcessManager::class);
        $capturedConfig = null;
        $mockProcessManager->expects($this->once())
            ->method('executeCloud')
            ->willReturnCallback(function (array $config) use (&$capturedConfig) {
                $capturedConfig = $config;

                return json_encode(['run_id' => 'run-1', 'test_id' => 'test-1', 'status' => 'completed']);
            });

        $this->setPrivateProperty($this->voltTest, 'processManager', $mockProcessManager);

        $this->voltTest->cloud('vt_test_key_123')
            ->setCloudTimeout(120)
            ->setVirtualUsers(1)
            ->setDuration('1s');

        $this->voltTest->scenario('Test')
            ->step('Step')
            ->get('http://example.com')
            ->validateStatus('success', 200);

        $this->voltTest->run(false);

        $this->assertTrue($capturedConfig['cloud']);
        $this->assertEquals('vt_test_key_123', $capturedConfig['api_key']);
        $this->assertEquals(120, $capturedConfig['cloud_timeout']);
    }

    public function testRunCloudParsesSuccessJson(): void
    {
        $mockProcessManager = $this->createMock(ProcessManager::class);
        $mockProcessManager->method('executeCloud')
            ->willReturn(json_encode(['run_id' => 'run-abc', 'test_id' => 'test-456', 'status' => 'completed']));

        $this->setPrivateProperty($this->voltTest, 'processManager', $mockProcessManager);

        $this->voltTest->cloud('vt_test_key_123')
            ->setVirtualUsers(1)
            ->setDuration('1s');

        $this->voltTest->scenario('Test')
            ->step('Step')
            ->get('http://example.com')
            ->validateStatus('success', 200);

        $result = $this->voltTest->run(false);

        $this->assertInstanceOf(CloudRun::class, $result);
        $this->assertEquals('run-abc', $result->getRunId());
        $this->assertEquals('test-456', $result->getTestId());
        $this->assertEquals('completed', $result->getStatus());
        $this->assertTrue($result->isSuccessful());
    }

    public function testRunCloudParsesFailedStatus(): void
    {
        $mockProcessManager = $this->createMock(ProcessManager::class);
        $mockProcessManager->method('executeCloud')
            ->willReturn(json_encode(['run_id' => 'run-1', 'test_id' => 'test-1', 'status' => 'failed']));

        $this->setPrivateProperty($this->voltTest, 'processManager', $mockProcessManager);

        $this->voltTest->cloud('vt_test_key_123')
            ->setVirtualUsers(1)
            ->setDuration('1s');

        $this->voltTest->scenario('Test')
            ->step('Step')
            ->get('http://example.com')
            ->validateStatus('success', 200);

        $this->expectException(RunFailedException::class);
        $this->expectExceptionMessage('Cloud run failed');

        $this->voltTest->run(false);
    }

    public function testRunCloudParsesFailedStatusWithErrorMessage(): void
    {
        $mockProcessManager = $this->createMock(ProcessManager::class);
        $mockProcessManager->method('executeCloud')
            ->willReturn(json_encode([
                'run_id' => 'run-1',
                'test_id' => 'test-1',
                'status' => 'failed',
                'error_message' => 'Target unreachable',
            ]));

        $this->setPrivateProperty($this->voltTest, 'processManager', $mockProcessManager);

        $this->voltTest->cloud('vt_test_key_123')
            ->setVirtualUsers(1)
            ->setDuration('1s');

        $this->voltTest->scenario('Test')
            ->step('Step')
            ->get('http://example.com')
            ->validateStatus('success', 200);

        $this->expectException(RunFailedException::class);
        $this->expectExceptionMessage('Cloud run failed: Target unreachable');

        $this->voltTest->run(false);
    }

    public function testRunCloudParsesStoppedStatus(): void
    {
        $mockProcessManager = $this->createMock(ProcessManager::class);
        $mockProcessManager->method('executeCloud')
            ->willReturn(json_encode(['run_id' => 'run-1', 'test_id' => 'test-1', 'status' => 'stopped']));

        $this->setPrivateProperty($this->voltTest, 'processManager', $mockProcessManager);

        $this->voltTest->cloud('vt_test_key_123')
            ->setVirtualUsers(1)
            ->setDuration('1s');

        $this->voltTest->scenario('Test')
            ->step('Step')
            ->get('http://example.com')
            ->validateStatus('success', 200);

        $this->expectException(RunFailedException::class);
        $this->expectExceptionMessage('Cloud run was stopped');

        $this->voltTest->run(false);
    }

    public function testRunCloudAuthenticationError(): void
    {
        $mockProcessManager = $this->createMock(ProcessManager::class);
        $mockProcessManager->method('executeCloud')
            ->willReturn(json_encode(['error' => true, 'error_type' => 'authentication', 'message' => 'Invalid API key']));

        $this->setPrivateProperty($this->voltTest, 'processManager', $mockProcessManager);

        $this->voltTest->cloud('vt_test_key_123')
            ->setVirtualUsers(1)
            ->setDuration('1s');

        $this->voltTest->scenario('Test')
            ->step('Step')
            ->get('http://example.com')
            ->validateStatus('success', 200);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid API key');

        $this->voltTest->run(false);
    }

    public function testRunCloudPlanLimitError(): void
    {
        $mockProcessManager = $this->createMock(ProcessManager::class);
        $mockProcessManager->method('executeCloud')
            ->willReturn(json_encode(['error' => true, 'error_type' => 'plan_limit', 'message' => 'Plan limit exceeded']));

        $this->setPrivateProperty($this->voltTest, 'processManager', $mockProcessManager);

        $this->voltTest->cloud('vt_test_key_123')
            ->setVirtualUsers(1)
            ->setDuration('1s');

        $this->voltTest->scenario('Test')
            ->step('Step')
            ->get('http://example.com')
            ->validateStatus('success', 200);

        $this->expectException(PlanLimitException::class);

        $this->voltTest->run(false);
    }

    public function testRunCloudConnectionError(): void
    {
        $mockProcessManager = $this->createMock(ProcessManager::class);
        $mockProcessManager->method('executeCloud')
            ->willReturn(json_encode(['error' => true, 'error_type' => 'connection', 'message' => 'Connection failed']));

        $this->setPrivateProperty($this->voltTest, 'processManager', $mockProcessManager);

        $this->voltTest->cloud('vt_test_key_123')
            ->setVirtualUsers(1)
            ->setDuration('1s');

        $this->voltTest->scenario('Test')
            ->step('Step')
            ->get('http://example.com')
            ->validateStatus('success', 200);

        $this->expectException(CloudConnectionException::class);

        $this->voltTest->run(false);
    }

    public function testRunCloudTimeoutError(): void
    {
        $mockProcessManager = $this->createMock(ProcessManager::class);
        $mockProcessManager->method('executeCloud')
            ->willReturn(json_encode(['error' => true, 'error_type' => 'timeout', 'message' => 'Cloud run timed out']));

        $this->setPrivateProperty($this->voltTest, 'processManager', $mockProcessManager);

        $this->voltTest->cloud('vt_test_key_123')
            ->setVirtualUsers(1)
            ->setDuration('1s');

        $this->voltTest->scenario('Test')
            ->step('Step')
            ->get('http://example.com')
            ->validateStatus('success', 200);

        $this->expectException(CloudTimeoutException::class);

        $this->voltTest->run(false);
    }

    public function testRunCloudMalformedOutput(): void
    {
        $mockProcessManager = $this->createMock(ProcessManager::class);
        $mockProcessManager->method('executeCloud')
            ->willReturn('not json');

        $this->setPrivateProperty($this->voltTest, 'processManager', $mockProcessManager);

        $this->voltTest->cloud('vt_test_key_123')
            ->setVirtualUsers(1)
            ->setDuration('1s');

        $this->voltTest->scenario('Test')
            ->step('Step')
            ->get('http://example.com')
            ->validateStatus('success', 200);

        $this->expectException(CloudException::class);
        $this->expectExceptionMessage('Failed to parse cloud result');

        $this->voltTest->run(false);
    }

    public function testRunCloudConflictUpdateReInvokesWithExistingTestId(): void
    {
        $callCount = 0;
        $capturedConfigs = [];

        $mockProcessManager = $this->createMock(ProcessManager::class);
        $mockProcessManager->expects($this->exactly(2))
            ->method('executeCloud')
            ->willReturnCallback(function (array $config) use (&$callCount, &$capturedConfigs) {
                $capturedConfigs[] = $config;
                $callCount++;

                if ($callCount === 1) {
                    return json_encode([
                        'conflict' => true,
                        'existing_tests' => [
                            ['id' => 'test-aaa', 'name' => 'Cloud Test Suite', 'target_url' => 'https://example.com', 'virtual_users' => 100, 'updated_at' => '2026-05-08'],
                            ['id' => 'test-bbb', 'name' => 'Cloud Test Suite', 'target_url' => 'https://other.com', 'virtual_users' => 50, 'updated_at' => '2026-05-07'],
                        ],
                    ]);
                }

                return json_encode(['run_id' => 'run-1', 'test_id' => 'test-aaa', 'status' => 'completed']);
            });

        $this->setPrivateProperty($this->voltTest, 'processManager', $mockProcessManager);

        $this->voltTest->cloud('vt_test_key_123')
            ->setVirtualUsers(1)
            ->setDuration('1s');

        // Callback returns the ID of the first test (update it)
        $this->voltTest->setOnConflictPrompt(function (array $existingTests) {
            return $existingTests[0]['id'];
        });

        $this->voltTest->scenario('Test')
            ->step('Step')
            ->get('http://example.com')
            ->validateStatus('success', 200);

        $result = $this->voltTest->run(false);

        $this->assertInstanceOf(\VoltTest\CloudRun::class, $result);
        $this->assertArrayNotHasKey('existing_test_id', $capturedConfigs[0]);
        $this->assertEquals('test-aaa', $capturedConfigs[1]['existing_test_id']);
    }

    public function testRunCloudConflictCreateReInvokesWithSkipLookup(): void
    {
        $callCount = 0;
        $capturedConfigs = [];

        $mockProcessManager = $this->createMock(ProcessManager::class);
        $mockProcessManager->expects($this->exactly(2))
            ->method('executeCloud')
            ->willReturnCallback(function (array $config) use (&$callCount, &$capturedConfigs) {
                $capturedConfigs[] = $config;
                $callCount++;

                if ($callCount === 1) {
                    return json_encode([
                        'conflict' => true,
                        'existing_tests' => [
                            ['id' => 'test-aaa', 'name' => 'Cloud Test Suite'],
                        ],
                    ]);
                }

                return json_encode(['run_id' => 'run-2', 'test_id' => 'new-test-id', 'status' => 'completed']);
            });

        $this->setPrivateProperty($this->voltTest, 'processManager', $mockProcessManager);

        $this->voltTest->cloud('vt_test_key_123')
            ->setVirtualUsers(1)
            ->setDuration('1s');

        // Callback returns null = create new
        $this->voltTest->setOnConflictPrompt(function (array $existingTests) {
            return null;
        });

        $this->voltTest->scenario('Test')
            ->step('Step')
            ->get('http://example.com')
            ->validateStatus('success', 200);

        $result = $this->voltTest->run(false);

        $this->assertInstanceOf(\VoltTest\CloudRun::class, $result);
        $this->assertArrayNotHasKey('existing_test_id', $capturedConfigs[1]);
        $this->assertTrue($capturedConfigs[1]['skip_lookup']);
    }

    public function testRunCloudSkipsLookupWhenNotCloud(): void
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

        $this->assertInstanceOf(\VoltTest\TestResult::class, $result);
    }

    public function testSetOnConflictPromptReturnsSelf(): void
    {
        $result = $this->voltTest->setOnConflictPrompt(function (array $test) {
            return 'update';
        });

        $this->assertSame($this->voltTest, $result);
    }

    public function testOnConflictPromptCallbackIsStored(): void
    {
        $callback = function (array $test) {
            return 'create';
        };
        $this->voltTest->setOnConflictPrompt($callback);

        $this->assertSame($callback, $this->getPrivateProperty($this->voltTest, 'onConflictPrompt'));
    }

    public function testRunCloudConflictCancelReturnsNull(): void
    {
        $mockProcessManager = $this->createMock(ProcessManager::class);
        $mockProcessManager->expects($this->once())
            ->method('executeCloud')
            ->willReturn(json_encode([
                'conflict' => true,
                'existing_tests' => [
                    ['id' => 'test-aaa', 'name' => 'Cloud Test Suite'],
                ],
            ]));

        $this->setPrivateProperty($this->voltTest, 'processManager', $mockProcessManager);

        $this->voltTest->cloud('vt_test_key_123')
            ->setVirtualUsers(1)
            ->setDuration('1s');

        $this->voltTest->setOnConflictPrompt(function (array $existingTests) {
            return 'cancel';
        });

        $this->voltTest->scenario('Test')
            ->step('Step')
            ->get('http://example.com')
            ->validateStatus('success', 200);

        $result = $this->voltTest->run(false);

        $this->assertNull($result);
    }

    public function testRunCloudConflictCancelDoesNotInvokeEngineAgain(): void
    {
        $mockProcessManager = $this->createMock(ProcessManager::class);
        $mockProcessManager->expects($this->once())
            ->method('executeCloud')
            ->willReturn(json_encode([
                'conflict' => true,
                'existing_tests' => [
                    ['id' => 'test-aaa', 'name' => 'Cloud Test Suite'],
                    ['id' => 'test-bbb', 'name' => 'Cloud Test Suite'],
                ],
            ]));

        $this->setPrivateProperty($this->voltTest, 'processManager', $mockProcessManager);

        $this->voltTest->cloud('vt_test_key_123')
            ->setVirtualUsers(1)
            ->setDuration('1s');

        $this->voltTest->setOnConflictPrompt(function (array $existingTests) {
            return 'cancel';
        });

        $this->voltTest->scenario('Test')
            ->step('Step')
            ->get('http://example.com')
            ->validateStatus('success', 200);

        $result = $this->voltTest->run(false);

        $this->assertNull($result);
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
