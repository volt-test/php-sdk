<?php

namespace VoltTest;

use RuntimeException;

class ProcessManager
{
    private string $binaryPath;

    private $currentProcess = null;
    private mixed $pipes;

    public function __construct(string $binaryPath)
    {
        $this->binaryPath = $binaryPath;
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, [$this, 'handleSignal']);
            pcntl_signal(SIGTERM, [$this, 'handleSignal']);
        }
    }

    public function handleSignal(int $signal): void
    {
        if ($this->currentProcess && is_resource($this->currentProcess)) {
            // Send SIGTERM to the process
            proc_terminate($this->currentProcess, SIGTERM);

            // Wait a bit for it to exit gracefully
            sleep(1);

            // Capture final output before closing the process
            $output = '';
            if ($this->pipes && isset($this->pipes[1]) && is_resource($this->pipes[1])) {
                stream_set_blocking($this->pipes[1], false); // Ensure non-blocking mode
                $output = stream_get_contents($this->pipes[1]);
                fclose($this->pipes[1]);
            }

            // Ensure the process is closed
            proc_close($this->currentProcess);
            $this->currentProcess = null;

            // Print the final output
            if (!empty($output)) {
                echo "\n$output\n";
            }
        }

        exit(0);
    }

    public function execute(array $config, bool $streamOutput): string
    {
        [$success, $process, $pipes] = $this->openProcess();
        $this->currentProcess = $process;
        $this->pipes = $pipes;

        if (! $success || ! is_array($pipes)) {
            throw new RuntimeException('Failed to start process of volt test');
        }

        try {
            $this->writeInput($pipes[0], json_encode($config, JSON_PRETTY_PRINT));
            fclose($pipes[0]);

            $output = $this->handleProcess($pipes, $streamOutput);

            // Store stderr content before closing
            $stderrContent = '';
            if (isset($pipes[2]) && is_resource($pipes[2])) {
                rewind($pipes[2]);
                $stderrContent = stream_get_contents($pipes[2]);
            }

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
                    echo "\nError: " . trim($stderrContent) . "\n";

                    return '';
                }
            }

            return $output;
        } finally {
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            if (is_resource($process)) {
                $this->closeProcess($process);
                $this->currentProcess = null;
            }
        }
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

        // Set non-blocking mode for stdout and stderr
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

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
