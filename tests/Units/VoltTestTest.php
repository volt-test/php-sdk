<?php

namespace Tests\Units;

use PHPUnit\Framework\TestCase;
use VoltTest\Exceptions\ErrorHandler;
use VoltTest\Exceptions\VoltTestException;
use VoltTest\ProcessManager;
use VoltTest\TestResult;
use VoltTest\VoltTest;

class VoltTestTest extends TestCase
{
    private VoltTest $voltTest;

    protected function setUp(): void
    {
        $this->voltTest = new VoltTest('Test Suite', 'Test Description');
    }

    protected function tearDown(): void
    {
        ErrorHandler::unregister();
        parent::tearDown();
    }

    public function testBasicConfiguration(): void
    {
        $result = $this->voltTest
            ->setVirtualUsers(10)
            ->setDuration('1m')
            ->setRampUp('10s')
            ->setTarget('30s');

        $this->assertInstanceOf(VoltTest::class, $result);
    }

    public function testInvalidVirtualUsers(): void
    {
        $this->expectException(VoltTestException::class);
        $this->voltTest->setVirtualUsers(0);
    }

    public function testSetHttpDebug(): void
    {
        $this->voltTest->setHttpDebug(true);
        $this->assertTrue($this->getPrivateProperty($this->voltTest, 'config')->toArray()['http_debug']);
    }

    public function testInvalidDuration(): void
    {
        $this->expectException(VoltTestException::class);
        $this->voltTest->setDuration('invalid');
    }

    public function testInvalidRampUp(): void
    {
        $this->expectException(VoltTestException::class);
        $this->voltTest->setRampUp('invalid');
    }

    public function testInvalidTarget(): void
    {
        $this->expectException(VoltTestException::class);
        $this->voltTest->setTarget('invalid-url');
    }

    public function testTargetSetsUrlAndIdleTimeout(): void
    {
        $result = $this->voltTest->target('https://api.example.com', '10s');

        $this->assertInstanceOf(VoltTest::class, $result);
        $config = $this->getPrivateProperty($this->voltTest, 'config')->toArray();
        $this->assertEquals('https://api.example.com', $config['target']['url']);
        $this->assertEquals('10s', $config['target']['idle_timeout']);
    }

    public function testTargetWithDefaultIdleTimeout(): void
    {
        $this->voltTest->target('https://api.example.com');

        $config = $this->getPrivateProperty($this->voltTest, 'config')->toArray();
        $this->assertEquals('https://api.example.com', $config['target']['url']);
        $this->assertEquals('30s', $config['target']['idle_timeout']);
    }

    public function testTargetWithInvalidUrl(): void
    {
        $this->expectException(VoltTestException::class);
        $this->voltTest->target('not-a-url');
    }

    public function testSetIdleTimeout(): void
    {
        $result = $this->voltTest->setIdleTimeout('15s');

        $this->assertInstanceOf(VoltTest::class, $result);
        $config = $this->getPrivateProperty($this->voltTest, 'config')->toArray();
        $this->assertEquals('15s', $config['target']['idle_timeout']);
    }

    public function testSetIdleTimeoutInvalid(): void
    {
        $this->expectException(VoltTestException::class);
        $this->voltTest->setIdleTimeout('invalid');
    }

    public function testSetTargetDelegatesToSetIdleTimeout(): void
    {
        $this->voltTest->setTarget('20s');

        $config = $this->getPrivateProperty($this->voltTest, 'config')->toArray();
        $this->assertEquals('20s', $config['target']['idle_timeout']);
    }

    public function testScenarioCreation(): void
    {
        $scenario = $this->voltTest->scenario('Login Flow', 'Test login functionality');

        $scenario->step('Login')
            ->post('http://example.com/login', '{"username": "test", "password": "test"}')
            ->header('Content-Type', 'application/json')
            ->validateStatus('success', 200);

        $this->assertCount(1, $this->getPrivateProperty($this->voltTest, 'scenarios'));
    }

    public function testScenarioExecution(): void
    {
        // Mock ProcessManager to avoid actual execution
        $mockProcessManager = $this->createMock(ProcessManager::class);
        $mockProcessManager->expects($this->once())
            ->method('execute')
            ->willReturn($this->getSampleOutput());

        $this->setPrivateProperty($this->voltTest, 'processManager', $mockProcessManager);

        // Configure test
        $this->voltTest
            ->setVirtualUsers(1)
            ->setDuration('1s')
            ->setTarget('40s');

        // Add a scenario
        $this->voltTest->scenario('Simple Test')
            ->step('Homepage')
            ->get('http://example.com')
            ->validateStatus('success', 200);

        // Run test
        $result = $this->voltTest->run(false);

        $this->assertInstanceOf(TestResult::class, $result);
        $this->assertEquals(100.0, $result->getSuccessRate());
    }

    private function getSampleOutput(): string
    {
        return <<<EOT
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

    private function setPrivateProperty($object, string $propertyName, $value): void
    {
        $reflection = new \ReflectionClass(get_class($object));
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }

    private function getPrivateProperty($object, string $propertyName)
    {
        $reflection = new \ReflectionClass(get_class($object));
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);

        return $property->getValue($object);
    }
}
