<?php

namespace VoltTest;
use RuntimeException;

class ProcessManager
{
    private string $binaryPath;
    private $currentProcess = null;
    private $isWindows;

    public function __construct(string $binaryPath)
    {
        $this->binaryPath = $binaryPath;
        $this->isWindows = PHP_OS_FAMILY === 'Windows';

        // Only register signal handlers on non-Windows systems
        if (!$this->isWindows && function_exists('pcntl_async_signals')) {
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

        if (!$success || !is_array($pipes)) {
            throw new RuntimeException('Failed to start process of volt test');
        }

        try {
            $this->writeInput($pipes[0], json_encode($config, JSON_PRETTY_PRINT));
            fclose($pipes[0]);
            return $this->handleProcess($pipes, $streamOutput);
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
    }

    protected function openProcess(): array
    {
        $descriptorspec = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w']   // stderr
        ];

        // Windows-specific process options
        $options = $this->isWindows ? [
            'bypass_shell' => true,
            'create_process_group' => true  // Important for Windows process management
        ] : [
            'bypass_shell' => true
        ];

        $process = proc_open(
            $this->binaryPath,
            $descriptorspec,
            $pipes,
            null,
            null,
            $options
        );

        if (!is_resource($process)) {
            return [false, null, []];
        }

        return [true, $process, $pipes];
    }

    private function handleProcess(array $pipes, bool $streamOutput): string
    {
        $output = '';
        $running = true;

        // Set streams to non-blocking mode
        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                stream_set_blocking($pipe, false);
            }
        }

        while ($running) {
            $read = array_filter($pipes, 'is_resource');
            if (empty($read)) {
                break;
            }

            // Windows-specific: Check process status
            if ($this->isWindows) {
                $status = proc_get_status($this->currentProcess);
                if (!$status['running']) {
                    $running = false;
                }
            }

            $write = null;
            $except = null;

            // Use a shorter timeout on Windows
            $timeout = $this->isWindows ? 0.1 : 1;

            if (stream_select($read, $write, $except, 0, $timeout * 1000000) === false) {
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
                        if ($this->isWindows) {
                            flush(); // Ensure output is displayed immediately on Windows
                        }
                    }
                } elseif ($type === 2 && $streamOutput) { // stderr
                    fwrite(STDERR, $data);
                    if ($this->isWindows) {
                        flush();
                    }
                }
            }
        }

        return $output;
    }

    protected function writeInput($pipe, string $input): void
    {
        if (is_resource($pipe)) {
            fwrite($pipe, $input);
            if ($this->isWindows) {
                fflush($pipe); // Ensure data is written immediately on Windows
            }
        }
    }

    protected function closeProcess($process): int
    {
        if (!is_resource($process)) {
            return -1;
        }

        $status = proc_get_status($process);
        if ($status['running']) {
            // Windows-specific process termination
            if ($this->isWindows) {
                exec('taskkill /F /T /PID ' . $status['pid']);
            } else {
                proc_terminate($process);
            }
        }

        return proc_close($process);
    }
}