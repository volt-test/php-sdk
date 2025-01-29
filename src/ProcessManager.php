<?php

namespace VoltTest;
use RuntimeException;

class ProcessManager
{
    private string $binaryPath;
    private $currentProcess = null;
    private bool $debug;
    private int $timeout = 30;

    public function __construct(string $binaryPath, bool $debug = true)
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

        // Create temporary config file
        $configFile = tempnam(sys_get_temp_dir(), 'volt_config_');
        $this->debugLog("Created config file: $configFile");

        // Write config to file
        file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
        $this->debugLog("Wrote config to file");

        // Prepare command with config file argument
        $cmd = sprintf(
            '"%s" --config "%s"',
            $this->binaryPath,
            $configFile
        );
        $this->debugLog("Command: $cmd");

        // Start process
        $descriptorspec = [
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w']   // stderr
        ];

        $this->debugLog("Opening process");
        $process = proc_open($cmd, $descriptorspec, $pipes, null, null, [
            'bypass_shell' => false  // Changed to false for Windows command
        ]);

        if (!is_resource($process)) {
            unlink($configFile);
            throw new RuntimeException("Failed to start process");
        }

        $this->currentProcess = $process;
        $this->debugLog("Process started");

        try {
            // Set streams to non-blocking mode
            foreach ($pipes as $pipe) {
                stream_set_blocking($pipe, false);
            }

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

                // Check for data timeout
                if (time() - $lastDataTime > 5) {
                    $this->debugLog("No data received for 5 seconds, checking process");
                    $lastDataTime = time();
                }

                $read = $pipes;
                $write = null;
                $except = null;

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

            // Close pipes
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }

            $exitCode = proc_close($process);
            $this->debugLog("Process closed with exit code: $exitCode");

            // Clean up config file
            if (file_exists($configFile)) {
                unlink($configFile);
                $this->debugLog("Cleaned up config file");
            }

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
                    exec("taskkill /F /T /PID {$status['pid']} 2>&1", $killOutput, $resultCode);
                    $this->debugLog("Taskkill result code: $resultCode");
                }
                proc_close($process);
            }

            // Clean up config file
            if (file_exists($configFile)) {
                unlink($configFile);
            }

            throw $e;
        }
    }
}
