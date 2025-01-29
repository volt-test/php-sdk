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

        // Create temporary directory for test files
        $tempDir = rtrim(sys_get_temp_dir(), '/\\') . '\\volt_' . uniqid();
        mkdir($tempDir);
        $this->debugLog("Created temp directory: $tempDir");

        // Create config file
        $configFile = $tempDir . '\\config.json';
        file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
        $this->debugLog("Wrote config to file: $configFile");

        $this->debugLog("config file contain: " . file_get_contents($configFile));

        // Change to temp directory and prepare command
        $currentDir = getcwd();
        chdir($tempDir);

        // Prepare command without any flags - the binary should read config.json by default
        $cmd = sprintf('"%s"', $this->binaryPath);
        $this->debugLog("Command: $cmd");

        // Start process
        $descriptorspec = [
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w']   // stderr
        ];

        $this->debugLog("Opening process in directory: " . getcwd());
        $process = proc_open($cmd, $descriptorspec, $pipes, $tempDir, null, [
            'bypass_shell' => false
        ]);

        if (!is_resource($process)) {
            $this->cleanup($tempDir, $currentDir);
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

            // Restore original directory and cleanup
            $this->cleanup($tempDir, $currentDir);

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

            // Restore directory and cleanup
            $this->cleanup($tempDir, $currentDir);

            throw $e;
        }
    }

    private function cleanup(string $tempDir, string $currentDir): void
    {
        // Restore original directory
        chdir($currentDir);

        // Clean up temp directory
        if (file_exists($tempDir)) {
            $files = glob($tempDir . '/*');
            foreach ($files as $file) {
                unlink($file);
            }
            rmdir($tempDir);
            $this->debugLog("Cleaned up temp directory");
        }
    }
}