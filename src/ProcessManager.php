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
        // Normalize path for Windows
        $this->binaryPath = str_replace('/', '\\', $binaryPath);
        $this->debug = $debug;

        // Verify binary exists and is executable
        if (!file_exists($this->binaryPath)) {
            throw new RuntimeException("Binary not found at: {$this->binaryPath}");
        }

        if (!is_executable($this->binaryPath)) {
            throw new RuntimeException("Binary is not executable: {$this->binaryPath}");
        }

        $this->debugLog("ProcessManager initialized with verified binary: {$this->binaryPath}");
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

        // Create temporary file for input
        $tmpfname = tempnam(sys_get_temp_dir(), 'volt_');
        $this->debugLog("Created temporary file: $tmpfname");

        // Write config to temp file
        file_put_contents($tmpfname, json_encode($config, JSON_PRETTY_PRINT));

        // Build command with proper escaping
        $cmd = escapeshellarg($this->binaryPath);
        $this->debugLog("Executing command: $cmd");

        // Setup process
        $descriptorspec = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w']   // stderr
        ];

        $cwd = dirname($this->binaryPath);
        $env = ['VOLT_TEST_DEBUG' => '1'];

        $this->debugLog("Opening process in directory: $cwd");

        $process = proc_open($cmd, $descriptorspec, $pipes, $cwd, $env, [
            'bypass_shell' => true,
            'create_process_group' => true
        ]);

        if (!is_resource($process)) {
            unlink($tmpfname);
            throw new RuntimeException("Failed to start process: $cmd");
        }

        $this->currentProcess = $process;
        $this->debugLog("Process started successfully");

        try {
            // Write config to process
            $this->debugLog("Writing config to process");
            $configContent = file_get_contents($tmpfname);
            fwrite($pipes[0], $configContent);
            fclose($pipes[0]);
            unlink($tmpfname);

            // Set up non-blocking reads
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);

            $output = '';
            $processRunning = true;

            $this->debugLog("Starting output reading loop");

            while ($processRunning) {
                $status = proc_get_status($process);
                $processRunning = $status['running'];

                $read = array_filter([$pipes[1], $pipes[2]], 'is_resource');
                if (empty($read)) {
                    break;
                }

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
                                fwrite(STDOUT, $data);
                                flush();
                            }
                        } else {
                            fwrite(STDERR, $data);
                            flush();
                        }
                    }
                }

                // Check if process has exited
                if (!$processRunning) {
                    $this->debugLog("Process has finished");
                    break;
                }
            }

            // Close remaining pipes
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }

            // Get exit code and close process
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
                    exec("taskkill /F /T /PID {$status['pid']} 2>&1", $output, $resultCode);
                    $this->debugLog("Taskkill result: " . implode("\n", $output) . " (code: $resultCode)");
                }
                proc_close($process);
            }

            throw $e;
        }
    }
}