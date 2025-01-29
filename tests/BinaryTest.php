<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use VoltTest\Platform;

Class BinaryTest extends TestCase
{
    private string $binaryPath;

    protected function setUp(): void
    {
        $this->binaryPath = Platform::getBinaryPath();
        $this->assertTrue(file_exists($this->binaryPath), "Binary does not exist at: {$this->binaryPath}");
        $this->assertTrue(is_executable($this->binaryPath), "Binary is not executable");
    }

    private function runCommand(string $cmd): array
    {
        $output = [];
        $returnVar = -1;
        exec($cmd . " 2>&1", $output, $returnVar);
        return ['output' => $output, 'code' => $returnVar];
    }

    public function testBinaryBasicExecution(): void
    {
        $result = $this->runCommand(escapeshellarg($this->binaryPath));
        $this->assertNotEmpty($result['output'], "Binary should produce some output");
    }

    public function testBinaryHelp(): void
    {
        $result = $this->runCommand(escapeshellarg($this->binaryPath) . " -h");
        $this->assertNotEmpty($result['output'], "Help command should produce output");
        $this->assertStringContainsString("Usage", implode("\n", $result['output']), "Help output should contain usage information");
    }

    public function testBinaryVersion(): void
    {
        $result = $this->runCommand(escapeshellarg($this->binaryPath) . " -v");
        $this->assertNotEmpty($result['output'], "Version command should produce output");
    }

    public function testBinaryWithConfig(): void
    {
        // Create test configuration
        $config = [
            'name' => 'test',
            'description' => 'test',
            'virtual_users' => 1,
            'duration' => '5s',
            'target' => [
                'url' => 'http://example.com',
                'idle_timeout' => '30s'
            ],
            'scenarios' => [[
                'name' => 'test',
                'steps' => [[
                    'name' => 'test',
                    'request' => [
                        'method' => 'GET',
                        'url' => 'http://example.com'
                    ]
                ]]
            ]]
        ];

        // Create temporary config file
        $configFile = tempnam(sys_get_temp_dir(), 'volt_test_');
        $this->assertNotFalse($configFile, "Failed to create temporary config file");

        file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));

        try {
            // Test different ways of passing config
            $testCommands = [
                'standard' => escapeshellarg($this->binaryPath) . " -config " . escapeshellarg($configFile),
                'short' => escapeshellarg($this->binaryPath) . " -f " . escapeshellarg($configFile),
                'input' => escapeshellarg($this->binaryPath) . " -input " . escapeshellarg($configFile),
                'stdin' => "type " . escapeshellarg($configFile) . " | " . escapeshellarg($this->binaryPath)
            ];

            foreach ($testCommands as $type => $cmd) {
                $result = $this->runCommand($cmd);
                $output = implode("\n", $result['output']);

                $this->assertNotEmpty($output, "Command $type should produce output");
                $this->assertStringNotContainsString("panic:", $output, "Command $type should not panic");
                $this->assertStringNotContainsString("fatal error:", $output, "Command $type should not have fatal errors");
            }
        } finally {
            // Cleanup
            if (file_exists($configFile)) {
                unlink($configFile);
            }
        }
    }

    public function testBinaryEnvironment(): void
    {
        // Get environment information
        $this->assertNotFalse(getenv('PATH'), "PATH environment variable should be set");
        $this->assertNotFalse(getenv('TEMP'), "TEMP environment variable should be set");
        $this->assertDirectoryIsWritable(sys_get_temp_dir(), "Temp directory should be writable");

        // Check binary metadata
        $this->assertGreaterThan(0, filesize($this->binaryPath), "Binary file should not be empty");

        // Verify working directory permissions
        $cwd = getcwd();
        $this->assertNotFalse($cwd, "Should be able to get current working directory");
        $this->assertDirectoryIsWritable($cwd, "Working directory should be writable");
    }

    public function testBinaryProcessHandling(): void
    {
        // Start binary with minimal config
        $config = [
            'name' => 'process_test',
            'virtual_users' => 1,
            'duration' => '1s',
            'target' => ['url' => 'http://example.com', 'idle_timeout' => '5s'],
            'scenarios' => [[
                'name' => 'test',
                'steps' => [[
                    'name' => 'test',
                    'request' => ['method' => 'GET', 'url' => 'http://example.com']
                ]]
            ]]
        ];

        $configFile = tempnam(sys_get_temp_dir(), 'volt_proc_');
        $this->assertNotFalse($configFile, "Failed to create temporary config file");

        file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));

        try {
            $cmd = escapeshellarg($this->binaryPath) . " -config " . escapeshellarg($configFile);
            $result = $this->runCommand($cmd);

            $output = implode("\n", $result['output']);
            $this->assertNotEmpty($output, "Process should produce output");
            $this->assertNotEquals(-1, $result['code'], "Process should exit normally");
        } finally {
            if (file_exists($configFile)) {
                unlink($configFile);
            }
        }
    }
}