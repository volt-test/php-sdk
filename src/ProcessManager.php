<?php

namespace VoltTest;

use RuntimeException;

class ProcessManager
{
    private string $binaryPath;

    private $currentProcess = null;

    public function __construct(string $binaryPath)
    {
        $this->binaryPath = $binaryPath;
        // Enable async signals
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, [$this, 'handleSignal']);
            pcntl_signal(SIGTERM, [$this, 'handleSignal']);
        }
    }

    private function handleSignal(int $signal): void
    {
        if ($this->currentProcess && is_resource($this->currentProcess)) {
            proc_terminate($this->currentProcess);
            proc_close($this->currentProcess);
        }
        exit(0);
    }

    public function execute(array $config, bool $streamOutput): string
    {
        [$success, $process, $pipes] = $this->openProcess();
        $this->currentProcess = $process;

        if (! $success || ! is_array($pipes)) {
            throw new RuntimeException('Failed to start process of volt test');
        }

        try {
            $this->writeInput($pipes[0], json_encode($config, JSON_PRETTY_PRINT));
            fclose($pipes[0]);

            $output = $this->handleProcess($pipes, $streamOutput);
        } finally {
            // Clean up pipes
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }

            if (is_resource($process)) {
                $exitCode = $this->closeProcess($process);
                $this->currentProcess = null;
                if ($exitCode !== 0) {
                    throw new RuntimeException('Process failed with exit code ' . $exitCode);
                }
            }
        }

        return $output;
    }

    protected function openProcess(): array
    {
        $pipes = [];
        $process = proc_open(
            $this->binaryPath,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            null,
            null,
            ['bypass_shell' => true]
        );

        if (! is_resource($process)) {
            return [false, null, []];
        }

        return [true, $process, $pipes];
    }

    private function handleProcess(array $pipes, bool $streamOutput): string
    {
        $output = '';

        while (true) {
            $read = array_filter($pipes, 'is_resource');
            if (empty($read)) {
                break;
            }

            $write = null;
            $except = null;

            if (stream_select($read, $write, $except, 1) === false) {
                break;
            }

            foreach ($read as $pipe) {
                $type = array_search($pipe, $pipes, true);
                $data = fread($pipe, 4096);

                if ($data === false || $data === '') {
                    if (feof($pipe)) {
                        fclose($pipe);
                        unset($pipes[$type]);

                        continue;
                    }
                }

                if ($type === 1) { // stdout
                    $output .= $data;
                    if ($streamOutput) {
                        echo $data;
                    }
                } elseif ($type === 2 && $streamOutput) { // stderr
                    fwrite(STDERR, $data);
                }
            }
        }

        return $output;
    }

    protected function writeInput($pipe, string $input): void
    {
        if (is_resource($pipe)) {
            fwrite($pipe, $input);
        }
    }

    protected function closeProcess($process): int
    {
        if (! is_resource($process)) {
            return -1;
        }

        $status = proc_get_status($process);
        if ($status['running']) {
            proc_terminate($process);
        }

        return proc_close($process);
    }
}
