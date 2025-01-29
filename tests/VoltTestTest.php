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
        $scenario1 = $voltTest->scenario("Test Scenario")->setWeight(10);
        $scenario1->step("Step 1")
            ->get('https://www.google.com')
            ->validateStatus('success', 200);

        // Create second scenario
        $scenario2 = $voltTest->scenario("Test Scenario 2")->setWeight(90);
        $scenario2->step("Step 1")
            ->get('https://www.google.com')
            ->validateStatus('success', 200);

        // Run test and get results
        $result = $voltTest->run(true);

        // Basic assertions about test execution
        $this->assertNotEmpty($result->getRawOutput(), "Raw output should not be empty");

        // Get duration and remove any 's' suffix if present
        $duration = str_replace('s', '', $result->getDuration());
        $this->assertNotEquals(0, (float)$duration, "Duration should not be 0");

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
