<?php

namespace Tests\Units;

use PHPUnit\Framework\TestCase;
use VoltTest\Exceptions\ErrorHandler;
use VoltTest\VoltTest;

class VoltTestNameDescriptionTest extends TestCase
{
    private VoltTest $test;

    public function setUp(): void
    {
        $this->test = new VoltTest('Original Name', 'Original Description');
    }

    protected function tearDown(): void
    {
        ErrorHandler::unregister();
        parent::tearDown();
    }

    public function testSetNameReturnsSelf(): void
    {
        $result = $this->test->setName('New Name');
        $this->assertInstanceOf(VoltTest::class, $result);
    }

    public function testSetDescriptionReturnsSelf(): void
    {
        $result = $this->test->setDescription('New Description');
        $this->assertInstanceOf(VoltTest::class, $result);
    }

    public function testSetNameFluentChaining(): void
    {
        $result = $this->test
            ->setName('New Name')
            ->setDescription('New Description')
            ->setVirtualUsers(10);

        $this->assertInstanceOf(VoltTest::class, $result);
    }

    public function testSetDescriptionFluentChaining(): void
    {
        $result = $this->test
            ->setDescription('New Description')
            ->setName('New Name')
            ->setDuration('30s');

        $this->assertInstanceOf(VoltTest::class, $result);
    }
}
