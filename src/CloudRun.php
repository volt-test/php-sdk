<?php

namespace VoltTest;

class CloudRun
{
    private const DASHBOARD_BASE_URL = 'https://app.volt-test.com';

    private string $runId;

    private string $testId;

    private string $status;

    public function __construct(string $runId, string $testId, string $status)
    {
        $this->runId = $runId;
        $this->testId = $testId;
        $this->status = $status;
    }

    public function getRunId(): string
    {
        return $this->runId;
    }

    public function getTestId(): string
    {
        return $this->testId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getDashboardUrl(): string
    {
        return self::DASHBOARD_BASE_URL . '/runs/' . $this->runId;
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'completed';
    }
}
