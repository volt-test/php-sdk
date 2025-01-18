<?php

namespace Tests\Units;

use PHPUnit\Framework\TestCase;
use VoltTest\TestResult;

class TestResultTest extends TestCase
{
    private const SAMPLE_OUTPUT = <<<EOT
Test Metrics Summary:
===================
Duration:     24.000873057s
Total Reqs:   5000
Success Rate: 82.96%
Req/sec:      208.33
Success Requests: 4148
Failed Requests: 852

Response Time:
------------
Min:    7.388011ms
Max:    18.179649581s
Avg:    3.848391356s
Median: 8.997304894s
P95:    16.74641748s
P99:    17.552319263s

Errors:
-------
failed to execute request: failed to execute request: Get "http://localhost:8001/register": read tcp 127.0.0.1:51336->127.0.0.1:8001: read: connection reset by peer: 1
failed to execute request: failed to execute request: Post "http://localhost:8001/register": read tcp 127.0.0.1:53103->127.0.0.1:8001: read: connection reset by peer: 1
EOT;

    private TestResult $result;

    protected function setUp(): void
    {
        $this->result = new TestResult(self::SAMPLE_OUTPUT);
    }

    public function testMainMetricsParsing(): void
    {
        $this->assertEquals('24.000873057s', $this->result->getDuration());
        $this->assertEquals(5000, $this->result->getTotalRequests());
        $this->assertEquals(82.96, $this->result->getSuccessRate());
        $this->assertEquals(208.33, $this->result->getRequestsPerSecond());
        $this->assertEquals(4148, $this->result->getSuccessRequests());
        $this->assertEquals(852, $this->result->getFailedRequests());
    }

    public function testResponseTimeMetricsParsing(): void
    {
        $this->assertEquals('7.388011ms', $this->result->getMinResponseTime());
        $this->assertEquals('18.179649581s', $this->result->getMaxResponseTime());
        $this->assertEquals('3.848391356s', $this->result->getAvgResponseTime());
        $this->assertEquals('8.997304894s', $this->result->getMedianResponseTime());
        $this->assertEquals('16.74641748s', $this->result->getP95ResponseTime());
        $this->assertEquals('17.552319263s', $this->result->getP99ResponseTime());
    }

    public function testEmptyOutput(): void
    {
        $result = new TestResult('');

        $this->assertEquals('0', $result->getDuration());
        $this->assertEquals(0, $result->getTotalRequests());
        $this->assertEquals(0.0, $result->getSuccessRate());
        $this->assertEquals(0.0, $result->getRequestsPerSecond());
        $this->assertEquals(0, $result->getSuccessRequests());
        $this->assertEquals(0, $result->getFailedRequests());
    }

    public function testPartialOutput(): void
    {
        $partialOutput = <<<EOT
Test Metrics Summary:
===================
Duration:     5s
Total Reqs:   100
Success Rate: 95.00%
Failed Requests: 5
EOT;

        $result = new TestResult($partialOutput);

        $this->assertEquals('5s', $result->getDuration());
        $this->assertEquals(100, $result->getTotalRequests());
        $this->assertEquals(95.00, $result->getSuccessRate());
        $this->assertEquals(5, $result->getFailedRequests());
        $this->assertNull($result->getMinResponseTime());
    }

    public function testGetAllMetrics(): void
    {
        $metrics = $this->result->getAllMetrics();

        $this->assertArrayHasKey('duration', $metrics);
        $this->assertArrayHasKey('totalRequests', $metrics);
        $this->assertArrayHasKey('successRate', $metrics);
        $this->assertArrayHasKey('requestsPerSecond', $metrics);
        $this->assertArrayHasKey('responseTime', $metrics);
        $this->assertIsArray($metrics['responseTime']);
    }
}
