<?php

namespace VoltTest;

class TestResult
{
    private string $rawOutput;

    private array $parsedResults = [];

    public function __construct(string $output)
    {
        $this->rawOutput = $output;
        $this->parseOutput();
    }

    private function parseOutput(): void
    {
        // Initialize default values
        $this->parsedResults = [
            'duration' => '0',
            'totalRequests' => 0,
            'successRate' => 0.0,
            'requestsPerSecond' => 0.0,
            'successRequests' => 0,
            'failedRequests' => 0,
            'responseTime' => [
                'min' => null,
                'max' => null,
                'avg' => null,
                'median' => null,
                'p95' => null,
                'p99' => null,
            ],
        ];

        if (empty($this->rawOutput)) {
            return;
        }

        // Parse main metrics
        $this->parseMainMetrics();

        // Parse response time metrics if present
        if (strpos($this->rawOutput, 'Response Time:') !== false) {
            $this->parseResponseTimeMetrics();
        }
    }

    private function parseMainMetrics(): void
    {
        // Duration
        if (preg_match('/Duration:\s+([\d.]+(?:ms|s|m|hr))/', $this->rawOutput, $matches)) {
            $this->parsedResults['duration'] = $matches[1];
        }

        // Total Requests
        if (preg_match('/Total Reqs:\s+(\d+)/', $this->rawOutput, $matches)) {
            $this->parsedResults['totalRequests'] = (int)$matches[1];
        }

        // Success Rate
        if (preg_match('/Success Rate:\s+([\d.]+)%/', $this->rawOutput, $matches)) {
            $this->parsedResults['successRate'] = (float)$matches[1];
        }

        // Requests per Second
        if (preg_match('/Req\/sec:\s+([\d.]+)/', $this->rawOutput, $matches)) {
            $this->parsedResults['requestsPerSecond'] = (float)$matches[1];
        }

        // Success Requests
        if (preg_match('/Success Requests:\s+(\d+)/', $this->rawOutput, $matches)) {
            $this->parsedResults['successRequests'] = (int)$matches[1];
        }

        // Failed Requests
        if (preg_match('/Failed Requests:\s+(\d+)/', $this->rawOutput, $matches)) {
            $this->parsedResults['failedRequests'] = (int)$matches[1];
        }
    }

    private function parseResponseTimeMetrics(): void
    {
        $metrics = [
            'min' => 'Min:\s+([^\n]+)',
            'max' => 'Max:\s+([^\n]+)',
            'avg' => 'Avg:\s+([^\n]+)',
            'median' => 'Median:\s+([^\n]+)',
            'p95' => 'P95:\s+([^\n]+)',
            'p99' => 'P99:\s+([^\n]+)',
        ];

        foreach ($metrics as $key => $pattern) {
            if (preg_match('/' . $pattern . '/', $this->rawOutput, $matches)) {
                $this->parsedResults['responseTime'][$key] = trim($matches[1]);
            }
        }
    }

    public function getDuration(): string
    {
        return $this->parsedResults['duration'];
    }

    public function getTotalRequests(): int
    {
        return $this->parsedResults['totalRequests'];
    }

    public function getSuccessRate(): float
    {
        return $this->parsedResults['successRate'];
    }

    public function getRequestsPerSecond(): float
    {
        return $this->parsedResults['requestsPerSecond'];
    }

    public function getSuccessRequests(): int
    {
        return $this->parsedResults['successRequests'];
    }

    public function getFailedRequests(): int
    {
        return $this->parsedResults['failedRequests'];
    }

    public function getMinResponseTime(): ?string
    {
        return $this->parsedResults['responseTime']['min'];
    }

    public function getMaxResponseTime(): ?string
    {
        return $this->parsedResults['responseTime']['max'];
    }

    public function getAvgResponseTime(): ?string
    {
        return $this->parsedResults['responseTime']['avg'];
    }

    public function getMedianResponseTime(): ?string
    {
        return $this->parsedResults['responseTime']['median'];
    }

    public function getP95ResponseTime(): ?string
    {
        return $this->parsedResults['responseTime']['p95'];
    }

    public function getP99ResponseTime(): ?string
    {
        return $this->parsedResults['responseTime']['p99'];
    }

    public function getRawOutput(): string
    {
        return $this->rawOutput;
    }

    /**
     * Returns all parsed metrics as an array
     * @return array
     */
    public function getAllMetrics(): array
    {
        return $this->parsedResults;
    }
}
