<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use VoltTest\Exceptions\ErrorHandler;
use VoltTest\VoltTest;

class VoltTestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Ensure error handlers are registered at the start of each test
        ErrorHandler::register();
    }

    protected function tearDown(): void
    {
        // Clean up error handlers after each test
        ErrorHandler::unregister();
        parent::tearDown();
    }

    public function testVoltTest()
    {
        $voltTest = new VoltTest("test");
        $voltTest->setVirtualUsers(10);

        // Create first scenario
        $scenario1 = $voltTest->scenario("Test Scenario")->setWeight(100);
        $scenario1->step("Step 1")
            ->get('https://www.google.com')
            ->setThinkTime('1s')
            ->validateStatus('success', 200);

        $scenario1->step("Step 2")
            ->get('https://www.google.com')
            ->setThinkTime('1s')
            ->validateStatus('success', 200);

        $result = $voltTest->run(true);

        // Verify response time metrics
        $avgResponseTime = $result->getAvgResponseTime();
        if ($avgResponseTime !== null) {
            $this->assertStringContainsString('ms', $avgResponseTime, "Average response time should contain 'ms'");
        }

        // Test rate metrics
        $this->assertGreaterThan(0, $result->getSuccessRate(), "Success rate should be greater than 0");
        $this->assertGreaterThanOrEqual(0, $result->getRequestsPerSecond(), "Requests per second should be non-negative");

        // Request counts
        $this->assertGreaterThan(0, $result->getTotalRequests(), "Total requests should be greater than 0");
        $this->assertGreaterThanOrEqual(0, $result->getSuccessRequests(), "Success requests should be non-negative");
        $this->assertGreaterThanOrEqual(0, $result->getFailedRequests(), "Failed requests should be non-negative");

        // Total requests should equal success + failed requests
        $this->assertEquals(
            $result->getTotalRequests(),
            $result->getSuccessRequests() + $result->getFailedRequests(),
            "Total requests should equal sum of success and failed requests"
        );

    }
}
