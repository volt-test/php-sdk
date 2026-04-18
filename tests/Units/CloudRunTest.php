<?php

namespace Tests\Units;

use PHPUnit\Framework\TestCase;
use VoltTest\CloudRun;

class CloudRunTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $run = new CloudRun('run-123', 'test-456', 'completed');

        $this->assertEquals('run-123', $run->getRunId());
        $this->assertEquals('test-456', $run->getTestId());
        $this->assertEquals('completed', $run->getStatus());
    }

    public function testGetDashboardUrl(): void
    {
        $run = new CloudRun('run-abc-123', 'test-456', 'running');

        $this->assertEquals('https://volt-test.com/runs/run-abc-123', $run->getDashboardUrl());
    }

    public function testGetDashboardUrlWithDifferentIds(): void
    {
        $run = new CloudRun('abc-def-ghi', 'test-1', 'pending');

        $this->assertEquals('https://volt-test.com/runs/abc-def-ghi', $run->getDashboardUrl());
    }

    public function testIsSuccessfulWhenCompleted(): void
    {
        $run = new CloudRun('run-1', 'test-1', 'completed');

        $this->assertTrue($run->isSuccessful());
    }

    public function testIsSuccessfulWhenFailed(): void
    {
        $run = new CloudRun('run-1', 'test-1', 'failed');

        $this->assertFalse($run->isSuccessful());
    }

    public function testIsSuccessfulWhenRunning(): void
    {
        $run = new CloudRun('run-1', 'test-1', 'running');

        $this->assertFalse($run->isSuccessful());
    }

    public function testIsSuccessfulWhenStopped(): void
    {
        $run = new CloudRun('run-1', 'test-1', 'stopped');

        $this->assertFalse($run->isSuccessful());
    }

    public function testIsSuccessfulWhenPending(): void
    {
        $run = new CloudRun('run-1', 'test-1', 'pending');

        $this->assertFalse($run->isSuccessful());
    }
}
