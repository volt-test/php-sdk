<?php

namespace Tests\Units;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use VoltTest\TestableProcessManager;

class ProcessManagerTest extends TestCase
{
    private TestableProcessManager $processManager;

    private string $binaryPath;

    protected function setUp(): void
    {
        $this->binaryPath = 'tmp/mock-binary';
        $this->processManager = new TestableProcessManager($this->binaryPath);
    }

    public function testSuccessfulExecution(): void
    {
        $config = ['name' => 'test', 'duration' => '1s'];
        $expectedOutput = "Test execution completed successfully\n";

        $this->processManager->setMockOutput($expectedOutput);

        $output = $this->processManager->execute($config, false);

        $this->assertEquals($expectedOutput, $output);
        $this->assertTrue($this->processManager->wasProcessStarted());
        $this->assertTrue($this->processManager->wasProcessClosed());

        // Verify config was properly encoded
        $expectedJson = json_encode($config, JSON_PRETTY_PRINT);
        $this->assertEquals($expectedJson, $this->processManager->getWrittenInput());
    }

    #[DataProvider('outputProvider')]
    public function testStreamedOutput(string $output, string $error, bool $expectError): void
    {
        $this->processManager->setMockOutput($output);
        $this->processManager->setMockStderr($error);

        ob_start();
        $result = $this->processManager->execute(['test' => true], true);
        $stdout = ob_get_clean();
        if ($expectError) {
            $this->assertStringContainsString($error, $stdout);
        } else {
            $this->assertEquals($output, $stdout);
            $this->assertEquals($output, $result);
        }
    }

    public static function outputProvider(): array
    {
        return [
            'standard output only' => ['Normal output', '', false],
            'with error output' => ['Output', 'Error occurred', false],
            'multiline output' => ["Line 1\nLine 2\nLine 3", '', false],
            'special characters' => ['Output with спеціальні символи', '', false],
        ];
    }

    public function testLongRunningProcess(): void
    {
        $longOutput = str_repeat("Output line\n", 100);
        $this->processManager->setMockOutput($longOutput);

        $output = $this->processManager->execute(['test' => true], false);

        $this->assertEquals($longOutput, $output);
        $this->assertTrue($this->processManager->wasProcessCompleted());
    }

    public function testProcessCompletionDetection(): void
    {
        $this->processManager->setMockOutput("Process running\nProcess complete");

        $output = $this->processManager->execute(['test' => true], false);

        $this->assertTrue($this->processManager->wasProcessStarted());
        $this->assertTrue($this->processManager->wasProcessCompleted());
        $this->assertTrue($this->processManager->wasProcessClosed());
    }

    #[DataProvider('configProvider')]
    public function testConfigEncoding(array $config, string $expectedJson): void
    {
        $this->processManager->execute($config, false);

        $this->assertEquals(
            $expectedJson,
            $this->processManager->getWrittenInput()
        );
    }

    public static function configProvider(): array
    {
        return [
            'simple config' => [
                ['test' => true],
                json_encode(['test' => true], JSON_PRETTY_PRINT),
            ],
            'nested config' => [
                ['outer' => ['inner' => 'value']],
                json_encode(['outer' => ['inner' => 'value']], JSON_PRETTY_PRINT),
            ],
            'complex config' => [
                [
                    'name' => 'test',
                    'duration' => '1s',
                    'options' => [
                        'retry' => true,
                        'timeout' => 30,
                    ],
                ],
                json_encode([
                    'name' => 'test',
                    'duration' => '1s',
                    'options' => [
                        'retry' => true,
                        'timeout' => 30,
                    ],
                ], JSON_PRETTY_PRINT),
            ],
        ];
    }

    public function testResourceCleanup(): void
    {
        $this->processManager->setMockOutput("Test");
        $this->processManager->execute(['test' => true], false);

        $this->assertTrue($this->processManager->wereResourcesCleaned());
    }

    public function testCleanupOnError(): void
    {
        $this->processManager->setMockExitCode(1);

        try {
            $this->processManager->execute(['test' => true], false);
        } catch (\RuntimeException $e) {
            // Expected exception
        }

        $this->assertTrue($this->processManager->wereResourcesCleaned());
    }
}
