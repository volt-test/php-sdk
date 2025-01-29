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
        $scenario1 = $voltTest->scenario("Test Scenario")->setWeight(10);
        $scenario1->step("Step 1")->get('https://www.google.com');
        $scenario2 = $voltTest->scenario("Test Scenario 2")->setWeight(90);
        $scenario2->step("Step 1")->get('https://www.google.com');
        $result = $voltTest->run(true);
        var_dump($result->getDuration());
        $this->assertNotEquals('0', $result->getDuration(), "Duration should not be 0");
        $this->assertNotNull($result->getAvgResponseTime(), "Average response time should not be null");

        $this->assertIsFloat($result->getSuccessRate(), "Success rate should be a float");
        $this->assertIsInt($result->getTotalRequests(), "Total requests should be an integer");
        $this->assertIsInt($result->getSuccessRequests(), "Success requests should be an integer");
        $this->assertIsInt($result->getFailedRequests(), "Failed requests should be an integer");
    }
}
