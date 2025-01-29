<?php

namespace VoltTest;
use RuntimeException;

class ProcessManager
{
    private string $binaryPath;
    private $currentProcess = null;
    private bool $debug;
    private int $timeout = 30; // timeout in seconds

    public function __construct(string $binaryPath, bool $debug = false)
    {
        $this->binaryPath = str_replace('/', '\\', $binaryPath);
        $this->debug = $debug;

        if (!file_exists($this->binaryPath)) {
            throw new RuntimeException("Binary not found at: {$this->binaryPath}");
        }

        $this->debugLog("ProcessManager initialized with binary: {$this->binaryPath}");
    }

    private function debugLog(string $message): void
    {
        if ($this->debug) {
            fwrite(STDERR, "[DEBUG] " . date('Y-m-d H:i:s') . " - $message\n");
            flush();
        }
    }

    public function execute(array $config, bool $streamOutput): string
    {
        $this->debugLog("Starting execution");

        // Prepare command
        $cmd = escapeshellarg($this->binaryPath);
        $this->debugLog("Command: $cmd");

        // Prepare config
        $configJson = json_encode($config, JSON_PRETTY_PRINT);
        $this->debugLog("Config prepared: " . substr($configJson, 0, 100) . "...");

        // Start process
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
            throw new RuntimeException("Failed to start process");
        }

        $this->currentProcess = $process;
        $this->debugLog("Process started");

        // Set streams to non-blocking mode
        foreach ($pipes as $pipe) {
            stream_set_blocking($pipe, false);
        }

        try {
            // Write config to stdin
            $this->debugLog("Writing config to process");
            $written = fwrite($pipes[0], $configJson);
            if ($written === false) {
                throw new RuntimeException("Failed to write config to process");
            }
            $this->debugLog("Wrote $written bytes to process");

            // Important: flush and close stdin
            fflush($pipes[0]);
            fclose($pipes[0]);
            $this->debugLog("Closed stdin pipe");

            $output = '';
            $startTime = time();
            $lastDataTime = time();

            while (true) {
                $status = proc_get_status($process);
                if (!$status['running']) {
                    $this->debugLog("Process has finished");
                    break;
                }

                // Check timeout
                if (time() - $startTime > $this->timeout) {
                    throw new RuntimeException("Process timed out after {$this->timeout} seconds");
                }

                // Check for data timeout (no data received)
                if (time() - $lastDataTime > 5) {
                    $this->debugLog("No data received for 5 seconds, checking process status");
                    $lastDataTime = time(); // Reset timer
                }

                $read = [$pipes[1], $pipes[2]];
                $write = null;
                $except = null;

                // Short timeout for select
                if (stream_select($read, $write, $except, 0, 200000)) {
                    foreach ($read as $pipe) {
                        $data = fread($pipe, 8192);
                        if ($data === false) {
                            continue;
                        }
                        if ($data !== '') {
                            $lastDataTime = time();
                            if ($pipe === $pipes[1]) {
                                $output .= $data;
                                if ($streamOutput) {
                                    fwrite(STDOUT, $data);
                                    flush();
                                }
                            } else {
                                fwrite(STDERR, $data);
                                flush();
                            }
                        }
                    }
                }
            }

            // Close remaining pipes
            foreach ([1, 2] as $i) {
                if (isset($pipes[$i]) && is_resource($pipes[$i])) {
                    fclose($pipes[$i]);
                }
            }

            $exitCode = proc_close($process);
            $this->debugLog("Process closed with exit code: $exitCode");

            if ($exitCode !== 0) {
                throw new RuntimeException("Process failed with exit code $exitCode");
            }

            return $output;

        } catch (\Exception $e) {
            $this->debugLog("Error occurred: " . $e->getMessage());

            // Clean up pipes
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }

            // Terminate process if still running
            if (is_resource($process)) {
                $status = proc_get_status($process);
                if ($status['running']) {
                    // Force kill on Windows
                    exec("taskkill /F /T /PID {$status['pid']} 2>&1", $output, $resultCode);
                    $this->debugLog("Taskkill result code: $resultCode");
                }
                proc_close($process);
            }

            throw $e;
        }
    }
}