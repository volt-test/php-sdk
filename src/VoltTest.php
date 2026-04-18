<?php

namespace VoltTest;

use VoltTest\Exceptions\CloudTimeoutException;
use VoltTest\Exceptions\ErrorHandler;
use VoltTest\Exceptions\RunFailedException;
use VoltTest\Exceptions\VoltTestException;

class VoltTest
{
    private Configuration $config;

    private array $scenarios = [];

    private ProcessManager $processManager;

    private ?string $cloudApiKey = null;

    private int $cloudTimeout = 1800;

    protected int $pollInterval = 3;

    public function __construct(string $name, string $description = '')
    {
        ErrorHandler::register();
        $this->config = new Configuration($name, $description);
        $this->processManager = new ProcessManager(Platform::getBinaryPath());
    }

    /**
     *  Set the number of virtual users
     * @param int $count
     * @return $this
     * @throws VoltTestException
     */
    public function setVirtualUsers(int $count): self
    {
        if ($count < 1) {
            throw new VoltTestException('Virtual users count must be at least 1');
        }
        if ($this->config->hasStages()) {
            throw new VoltTestException('Cannot use setVirtualUsers with stages. Stages define the full load profile.');
        }
        $this->config->setVirtualUsers($count);

        return $this;
    }

    /**
     * Set the test duration
     * @param string $duration
     * @return $this
     * @throws VoltTestException
     */
    public function setDuration(string $duration): self
    {
        if (! preg_match('/^\d+[smh]$/', $duration)) {
            throw new VoltTestException('Invalid duration format. Use <number>[s|m|h]');
        }
        if ($this->config->hasStages()) {
            throw new VoltTestException('Cannot use setDuration with stages. Stages define the full load profile.');
        }
        $this->config->setDuration($duration);

        return $this;
    }

    /*
     * Set the ramp-up time for every virtual user to start
     * @param string $rampUp
     * @return $this
     * @throws VoltTestException
     * */
    public function setRampUp(string $rampUp): self
    {
        if (! preg_match('/^\d+[smh]$/', $rampUp)) {
            throw new VoltTestException('Invalid ramp-up format. Use <number>[s|m|h]');
        }
        if ($this->config->hasStages()) {
            throw new VoltTestException('Cannot use setRampUp with stages. Stages define the full load profile.');
        }
        $this->config->setRampUp($rampUp);

        return $this;
    }

    /**
     * Add a stage to the load profile.
     * Each stage linearly ramps from the previous target to this target over the given duration.
     * Stages are mutually exclusive with setVirtualUsers/setDuration/setRampUp.
     *
     * @param string $duration Duration of this stage (e.g. "5m", "30s", "1h")
     * @param int $target Target VU count at the end of this stage
     * @return $this
     * @throws VoltTestException
     */
    public function stage(string $duration, int $target): self
    {
        if ($this->config->hasConstantLoad()) {
            throw new VoltTestException('Cannot use stages with setVirtualUsers/setDuration/setRampUp. Use stages to define the full load profile.');
        }
        $this->config->addStage($duration, $target);

        return $this;
    }

    /**
     * Set the HTTP request timeout (per-request)
     * @param string $timeout e.g. "60s", "2m" — default is 30s
     * @return $this
     * @throws VoltTestException
     */
    public function setHttpTimeout(string $timeout): self
    {
        if (! preg_match('/^\d+[smh]$/', $timeout)) {
            throw new VoltTestException('Invalid HTTP timeout format. Use <number>[s|m|h]');
        }
        $this->config->setHttpTimeout($timeout);

        return $this;
    }

    public function setHttpDebug(bool $httpDebug): self
    {
        $this->config->setHttpDebug($httpDebug);

        return $this;
    }

    /**
     * Set the target URL and idle timeout
     * @param string $idleTimeout Default is 30s (30 seconds) example: 1s (1 second), 1m (1 minute), 1h (1 hour)
     * @throws VoltTestException
     */
    public function setTarget(string $idleTimeout): self
    {
        if (! preg_match('/^\d+[smh]$/', $idleTimeout)) {
            throw new VoltTestException('Invalid idle timeout format. Use <number>[s|m|h]');
        }
        $this->config->setTarget($idleTimeout);

        return $this;
    }

    /**
     * Enable cloud execution mode.
     *
     * @param string $apiKey Your VoltTest API key (starts with "vt_")
     * @return $this
     * @throws VoltTestException
     */
    public function cloud(string $apiKey): self
    {
        if (empty($apiKey)) {
            throw new VoltTestException('API key is required. Create one at https://app.volt-test.com/settings');
        }

        if (! str_starts_with($apiKey, 'vt_')) {
            throw new VoltTestException('API key must start with "vt_"');
        }

        $this->cloudApiKey = $apiKey;

        return $this;
    }

    /**
     * Set the cloud execution timeout in seconds (default: 1800 = 30 minutes).
     *
     * @return $this
     */
    public function setCloudTimeout(int $seconds): self
    {
        $this->cloudTimeout = max(60, $seconds);

        return $this;
    }

    public function scenario(string $name, string $description = ''): Scenario
    {
        $scenario = new Scenario($name, $description);
        $this->scenarios[] = $scenario;

        return $scenario;
    }

    public function run(bool $streamOutput = false): TestResult|CloudRun
    {
        $config = $this->prepareConfig();

        if ($this->cloudApiKey !== null) {
            return $this->runCloud($config);
        }

        $output = $this->processManager->execute($config, $streamOutput);

        return new TestResult($output);
    }

    protected function createCloudClient(): CloudClient
    {
        return new CloudClient($this->cloudApiKey);
    }

    private function runCloud(array $config): CloudRun
    {
        $client = $this->createCloudClient();

        /** @var string|null $runId */
        $runId = null;
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, function () use ($client, &$runId) {
                if ($runId !== null) {
                    echo "\n  Stopping cloud run...\n";

                    try {
                        $client->stopRun($runId);
                    } catch (\Exception $e) {
                    }
                }
                exit(130);
            });
        }

        $targetUrl = $config['target']['url'] ?? '';
        $virtualUsers = $config['virtual_users'] ?? 1;
        $durationSeconds = 0;

        if (isset($config['stages']) && is_array($config['stages'])) {
            foreach ($config['stages'] as $stage) {
                $durationSeconds += $this->parseDurationToSeconds($stage['duration'] ?? '0s');
            }
            $targets = array_column($config['stages'], 'target');
            if (! empty($targets)) {
                $virtualUsers = max($targets);
            }
        } elseif (isset($config['duration'])) {
            $durationSeconds = $this->parseDurationToSeconds($config['duration']);
        }

        $testConfig = $config;
        unset($testConfig['weights']);

        $testData = [
            'name' => $config['name'] ?? 'Unnamed Test',
            'description' => $config['description'] ?? '',
            'target_url' => $targetUrl,
            'virtual_users' => $virtualUsers,
            'duration_seconds' => $durationSeconds,
            'test_config' => json_encode($testConfig),
        ];

        $test = $client->createTest($testData);
        $run = $client->startRun($test['id']);
        $runId = $run['id'];

        echo "\n";
        echo "  Cloud test submitted (run: {$runId})\n";
        echo "  Waiting for cloud infrastructure...\n";
        echo "\n";

        $elapsed = 0;
        $interval = $this->pollInterval;
        $currentStatus = 'pending';
        $status = [];
        $spinnerFrames = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
        $frame = 0;
        $lastStatus = '';

        while ($elapsed < $this->cloudTimeout) {
            sleep($interval);
            $elapsed += $interval;

            $status = $client->getRunStatus($runId);
            $currentStatus = $status['status'];

            if (in_array($currentStatus, ['completed', 'failed', 'stopped'])) {
                echo "\r\033[K";

                break;
            }

            $spinner = $spinnerFrames[$frame % count($spinnerFrames)];
            $frame++;

            if (in_array($currentStatus, ['pending', 'provisioning', 'starting'])) {
                $label = ucfirst($currentStatus) . '...';
                echo "\r\033[K  {$spinner} {$label}";
                $lastStatus = $currentStatus;
            } elseif ($currentStatus === 'running' && isset($status['progress'])) {
                $pct = $status['progress']['percentage'] ?? 0;
                $elapsedSec = $status['progress']['elapsed_seconds'] ?? 0;
                $totalSec = $status['progress']['total_seconds'] ?? $durationSeconds;

                $barWidth = 20;
                $filled = (int) round($barWidth * $pct / 100);
                $bar = str_repeat('▓', $filled) . str_repeat('░', $barWidth - $filled);

                echo "\r\033[K  {$bar} {$pct}% ({$elapsedSec}s / {$totalSec}s)";
            }
        }

        echo "\n";

        if ($elapsed >= $this->cloudTimeout) {
            throw new CloudTimeoutException(
                "Cloud run timed out after {$this->cloudTimeout} seconds. Run ID: {$runId}"
            );
        }

        $cloudRun = new CloudRun($runId, $test['id'], $currentStatus);

        if ($currentStatus === 'failed') {
            $errorMsg = $status['error_message'] ?? 'Unknown error';
            echo "  ✗ Test failed: {$errorMsg}\n\n";
            echo "  View details → {$cloudRun->getDashboardUrl()}\n\n";

            throw new RunFailedException("Cloud run failed: {$errorMsg}. Run ID: {$runId}");
        }

        if ($currentStatus === 'stopped') {
            echo "  ⊘ Test was stopped\n\n";
            echo "  View details → {$cloudRun->getDashboardUrl()}\n\n";

            throw new RunFailedException("Cloud run was stopped. Run ID: {$runId}");
        }

        echo "  ✓ Test completed\n\n";
        echo "  View results → {$cloudRun->getDashboardUrl()}\n\n";

        return $cloudRun;
    }

    private function parseDurationToSeconds(string $duration): int
    {
        if (preg_match('/^(\d+)(s|m|h)$/', $duration, $matches)) {
            return match ($matches[2]) {
                's' => (int) $matches[1],
                'm' => (int) $matches[1] * 60,
                'h' => (int) $matches[1] * 3600,
            };
        }

        return 0;
    }

    private function prepareConfig(): array
    {
        $config = $this->config->toArray();
        $config['scenarios'] = array_map(function (Scenario $scenario) {
            return $scenario->toArray();
        }, $this->scenarios);

        $config['weights'] = array_map(function (Scenario $scenario) {
            return $scenario->getWeight();
        }, $this->scenarios);

        return $config;
    }
}
