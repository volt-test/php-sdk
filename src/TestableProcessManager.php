<?php

namespace VoltTest;

class TestableProcessManager extends ProcessManager
{
    private string $mockOutput = '';

    private string $mockStderr = '';

    private int $mockExitCode = 0;

    private bool $failProcessStart = false;

    private bool $processStarted = false;

    private bool $processClosed = false;

    private string $writtenInput = '';

    private bool $isRunning = true;

    private bool $processCompleted = false;

    private bool $resourcedCleaned = false;

    public function setMockOutput(string $output): void
    {
        $this->mockOutput = $output;
    }

    public function setMockStderr(string $stderr): void
    {
        $this->mockStderr = $stderr;
    }

    public function setMockExitCode(int $exitCode): void
    {
        $this->mockExitCode = $exitCode;
    }

    public function setFailProcessStart(bool $fail): void
    {
        $this->failProcessStart = $fail;
    }

    public function wasProcessStarted(): bool
    {
        return $this->processStarted;
    }

    public function wasProcessClosed(): bool
    {
        return $this->processClosed;
    }

    public function wasProcessCompleted(): bool
    {
        return $this->processCompleted;
    }

    public function getWritternInput(): string
    {
        return $this->writtenInput;
    }

    public function wereResourcesCleaned(): bool
    {
        return $this->resourcedCleaned;
    }

    protected function openProcess(): array
    {
        if ($this->failProcessStart) {
            return [false, null, []];
        }

        $this->processStarted = true;

        $pipes = [
            0 => fopen('php://temp', 'r+'), // stdin
            1 => fopen('php://temp', 'w+'), // stdout
            2 => fopen('php://temp', 'w+'), // stderr
        ];

        if (in_array(false, $pipes, true)) {
            return [false, null, []];
        }

        // Write mock output and error to pipes
        fwrite($pipes[1], $this->mockOutput);
        fseek($pipes[1], 0);

        fwrite($pipes[2], $this->mockStderr);
        fseek($pipes[2], 0);

        // Mock a process resource
        $process = tmpfile();

        return [true, $process, $pipes];
    }

    protected function writeInput($pipe, string $input): void
    {
        $this->writtenInput = $input;
        parent::writeInput($pipe, $input);
    }

    protected function closeProcess($process): int
    {
        $this->processClosed = true;
        $this->resourcedCleaned = true;
        $this->processCompleted = true;

        return $this->mockExitCode;
    }

    protected function getProcessStatus($process): array
    {
        if ($this->isRunning) {
            $this->isRunning = false;

            return ['running' => true];
        }

        $this->processCompleted = true;

        return ['running' => false];
    }

    public function getWrittenInput(): string
    {
        return $this->writtenInput;
    }
}
