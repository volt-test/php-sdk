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
        $result = $voltTest->run();
        $this->assertNotEmpty($result->getRawOutput(), "Raw output is empty");
        $this->assertGreaterThan(0, $result->getDuration(), "Duration is not greater than 0");
        $this->assertGreaterThan(0, $result->getAvgResponseTime(), "Average response time is not greater than 0");
    }
}