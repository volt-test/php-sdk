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
        $voltTest->setVirtualUsers(1);

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
        var_dump($avgResponseTime);
        if ($avgResponseTime !== null) {
            $this->assertStringContainsString('ms', $avgResponseTime, "Average response time should contain 'ms'");
        }

    }
}
