<?php

namespace VoltTest;

use VoltTest\Exceptions\ErrorHandler;
use VoltTest\Exceptions\VoltTestException;

class VoltTest
{
    private Configuration $config;

    private array $scenarios = [];

    private ProcessManager $processManager;

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
        $this->config->setRampUp($rampUp);

        return $this;
    }

    public function setHttpDebug(bool $httpDebug): self
    {
        $this->config->setHttpDebug($httpDebug);

        return $this;
    }

    /**
     * Set the target URL and idle timeout
     * @param string $url The target URL to test
     * @param string $idleTimeout Default is 30s (30 seconds) example: 1s (1 second), 1m (1 minute), 1h (1 hour)
     * @throws VoltTestException
     */
    public function setTarget(string $url, string $idleTimeout = '30s'): self
    {
        if (! preg_match('/^https?:\/\//', $url)) {
            throw new VoltTestException('URL should start with http:// or https://');
        }
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new VoltTestException('Invalid URL');
        }
        if (! preg_match('/^\d+[smh]$/', $idleTimeout)) {
            throw new VoltTestException('Invalid idle timeout format. Use <number>[s|m|h]');
        }
        $this->config->setTarget($url, $idleTimeout);

        return $this;
    }

    public function scenario(string $name, string $description = ''): Scenario
    {
        $scenario = new Scenario($name, $description);
        $this->scenarios[] = $scenario;

        return $scenario;
    }

    public function run(bool $streamOutput = false): TestResult
    {
        $config = $this->prepareConfig();

        $output = $this->processManager->execute($config, $streamOutput);

        return new TestResult($output);
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
