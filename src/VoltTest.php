<?php

namespace VoltTest;

use VoltTest\Exceptions\AuthenticationException;
use VoltTest\Exceptions\CloudConnectionException;
use VoltTest\Exceptions\CloudException;
use VoltTest\Exceptions\CloudTimeoutException;
use VoltTest\Exceptions\ErrorHandler;
use VoltTest\Exceptions\PlanLimitException;
use VoltTest\Exceptions\RunFailedException;
use VoltTest\Exceptions\VoltTestException;

class VoltTest
{
    private Configuration $config;

    private array $scenarios = [];

    private ProcessManager $processManager;

    private ?string $cloudApiKey = null;

    private int $cloudTimeout = 1800;

    /** @var callable|null */
    private $onConflictPrompt = null;

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
     * Set region distribution for cloud execution.
     *
     * @param array<string, int> $regions Region code => weight (e.g., ['us-east-1' => 60, 'eu-west-1' => 40])
     * @return $this
     * @throws VoltTestException
     */
    public function regions(array $regions): self
    {
        $this->config->setRegions($regions);

        return $this;
    }

    public function setName(string $name): self
    {
        $this->config->setName($name);

        return $this;
    }

    public function setDescription(string $description): self
    {
        $this->config->setDescription($description);

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

    public function setOnConflictPrompt(callable $callback): self
    {
        $this->onConflictPrompt = $callback;

        return $this;
    }

    public function scenario(string $name, string $description = ''): Scenario
    {
        $scenario = new Scenario($name, $description);
        $this->scenarios[] = $scenario;

        return $scenario;
    }

    public function run(bool $streamOutput = false): TestResult|CloudRun|null
    {
        if ($this->config->hasRegions() && $this->cloudApiKey === null) {
            throw new VoltTestException('Region distribution requires cloud execution mode. Call cloud() before run().');
        }

        $config = $this->prepareConfig();

        if ($this->cloudApiKey !== null) {
            $output = $this->processManager->executeCloud($config);

            $data = json_decode($output, true);
            if (is_array($data) && isset($data['conflict']) && $data['conflict'] === true) {
                $existingTests = $data['existing_tests'] ?? [];
                $decision = $this->promptForConflict($existingTests);

                if ($decision === 'cancel') {
                    return null;
                }

                if ($decision !== null) {
                    $config['existing_test_id'] = $decision;
                } else {
                    $config['skip_lookup'] = true;
                }

                $output = $this->processManager->executeCloud($config);
            }

            return $this->parseCloudResult($output);
        }

        $output = $this->processManager->execute($config, $streamOutput);

        return new TestResult($output);
    }

    /**
     * @param array[] $existingTests
     * @return string|null Test ID to update, or null to create new
     */
    private function promptForConflict(array $existingTests): ?string
    {
        if (empty($existingTests)) {
            return null;
        }

        if ($this->onConflictPrompt !== null) {
            return ($this->onConflictPrompt)($existingTests);
        }

        if (function_exists('posix_isatty') && posix_isatty(STDIN)) {
            $count = count($existingTests);
            $name = $existingTests[0]['name'] ?? 'Unknown';
            echo "\n{$count} test(s) named '{$name}' already exist:\n";

            foreach ($existingTests as $i => $test) {
                $num = $i + 1;
                $id = substr($test['id'] ?? '', 0, 8);
                $url = $test['target_url'] ?? 'N/A';
                $vus = $test['virtual_users'] ?? '?';
                $updated = $test['updated_at'] ?? '';
                echo "  [{$num}] ID: {$id}...  Target: {$url}  VUs: {$vus}  Updated: {$updated}\n";
            }

            $createOption = $count + 1;
            $cancelOption = $count + 2;
            echo "  [{$createOption}] Create new test\n";
            echo "  [{$cancelOption}] Cancel\n";

            while (true) {
                echo "Choice [1]: ";
                $input = trim((string) fgets(STDIN));

                if ($input === '') {
                    return $existingTests[0]['id'] ?? null;
                }

                $choice = (int) $input;
                if ($choice >= 1 && $choice <= $count) {
                    return $existingTests[$choice - 1]['id'] ?? null;
                }
                if ($choice === $createOption) {
                    return null;
                }
                if ($choice === $cancelOption) {
                    return 'cancel';
                }

                echo "  Invalid choice. Please enter a number between 1 and {$cancelOption}.\n";
            }
        }

        // Non-interactive: default to updating the most recent
        return $existingTests[0]['id'] ?? null;
    }

    private function parseCloudResult(string $output): CloudRun
    {
        $data = json_decode($output, true);

        if (! is_array($data)) {
            throw new CloudException('Failed to parse cloud result: ' . $output);
        }

        if (isset($data['error']) && $data['error'] === true) {
            $this->throwCloudError($data['error_type'] ?? 'cloud_error', $data['message'] ?? 'Unknown error');
        }

        $status = $data['status'] ?? 'unknown';
        $runId = $data['run_id'] ?? '';
        $testId = $data['test_id'] ?? '';
        $errorMessage = $data['error_message'] ?? '';

        if ($status === 'failed') {
            $msg = 'Cloud run failed';
            if ($errorMessage !== '') {
                $msg .= ": {$errorMessage}";
            }

            throw new RunFailedException("{$msg}. Run ID: {$runId}");
        }

        if ($status === 'stopped') {
            throw new RunFailedException("Cloud run was stopped. Run ID: {$runId}");
        }

        return new CloudRun($runId, $testId, $status);
    }

    private function throwCloudError(string $errorType, string $message): void
    {
        match ($errorType) {
            'authentication' => throw new AuthenticationException($message),
            'plan_limit' => throw new PlanLimitException($message),
            'connection' => throw new CloudConnectionException($message),
            'timeout' => throw new CloudTimeoutException($message),
            default => throw new CloudException($message),
        };
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

        if ($this->cloudApiKey !== null) {
            $config['cloud'] = true;
            $config['api_key'] = $this->cloudApiKey;
            $config['cloud_timeout'] = $this->cloudTimeout;
        }

        return $config;
    }
}
