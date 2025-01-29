<?php

namespace VoltTest;
use RuntimeException;

class ProcessManager
{
    private string $binaryPath;
    private $currentProcess = null;
    private bool $debug;

    public function __construct(string $binaryPath, bool $debug = true)
    {
        $this->binaryPath = $binaryPath;
        $this->debug = $debug;
        $this->debugLog("ProcessManager initialized with binary: $binaryPath");
    }

    private function debugLog(string $message): void
    {
        if ($this->debug) {
            echo "[DEBUG] " . date('Y-m-d H:i:s') . " - $message\n";
            flush();
        }
    }

    public function execute(array $config, bool $streamOutput): string
    {
        $this->debugLog("Starting execution");

        // For Windows, ensure the path is properly quoted
        $cmd = PHP_OS_FAMILY === 'Windows'
            ? '"' . str_replace('/', '\\', $this->binaryPath) . '"'
            : $this->binaryPath;

        $this->debugLog("Command to execute: $cmd");

        // Create temporary file for input
        $tmpfname = tempnam(sys_get_temp_dir(), 'volt_');
        $this->debugLog("Created temporary file: $tmpfname");

        file_put_contents($tmpfname, json_encode($config, JSON_PRETTY_PRINT));
        $this->debugLog("Written config to temporary file");

        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];

        $this->debugLog("Opening process");

        $process = proc_open($cmd, $descriptorspec, $pipes, null, null, [
            'bypass_shell' => true,
            'create_process_group' => true
        ]);

        if (!is_resource($process)) {
            unlink($tmpfname);
            throw new RuntimeException('Failed to start process');
        }

        $this->currentProcess = $process;
        $this->debugLog("Process started successfully");

        try {
            // Write config to stdin
            $this->debugLog("Writing config to process");
            fwrite($pipes[0], file_get_contents($tmpfname));
            fclose($pipes[0]);
            unlink($tmpfname);

            $this->debugLog("Starting to read output");
            $output = '';

            // Set streams to non-blocking
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);

            while (true) {
                $status = proc_get_status($process);

                if (!$status['running']) {
                    $this->debugLog("Process has finished running");
                    break;
                }

                $read = [$pipes[1], $pipes[2]];
                $write = null;
                $except = null;

                if (stream_select($read, $write, $except, 0, 100000)) {
                    foreach ($read as $pipe) {
                        $data = fread($pipe, 4096);
                        if ($data === false || $data === '') {
                            continue;
                        }

                        if ($pipe === $pipes[1]) {
                            $output .= $data;
                            if ($streamOutput) {
                                echo $data;
                                flush();
                            }
                        } else {
                            fwrite(STDERR, $data);
                            flush();
                        }
                    }
                }
            }

            $this->debugLog("Finished reading output");

            // Close remaining pipes
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }

            // Get exit code
            $exitCode = proc_close($process);
            $this->debugLog("Process closed with exit code: $exitCode");

            if ($exitCode !== 0) {
                throw new RuntimeException("Process failed with exit code $exitCode");
            }

            return $output;

        } catch (\Exception $e) {
            $this->debugLog("Error occurred: " . $e->getMessage());

            // Clean up
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }

            if (is_resource($process)) {
                $status = proc_get_status($process);
                if ($status['running']) {
                    // Force kill on Windows
                    if (PHP_OS_FAMILY === 'Windows') {
                        exec('taskkill /F /T /PID ' . $status['pid']);
                    } else {
                        proc_terminate($process);
                    }
                }
                proc_close($process);
            }

            throw $e;
        }
    }
}