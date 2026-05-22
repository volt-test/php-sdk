<?php

namespace VoltTest;

use VoltTest\Exceptions\VoltTestException;

class Configuration
{
    private string $name;

    private string $description;

    private int $virtualUsers;

    private string $duration;

    private string $rampUp;

    private array $target;

    private string $httpTimeout = '';

    private bool $httpDebug = false;

    /** @var Stage[] */
    private array $stages = [];

    /** @var array<string, int> */
    private array $regionConfig = [];

    public function __construct(string $name, string $description = '')
    {
        $this->name = $name;
        $this->description = $description;
        $this->virtualUsers = 1;
        $this->duration = '';
        $this->rampUp = '';
        $this->target = [
            'url' => 'https://example.com',
            'idle_timeout' => '30s',
        ];
    }

    public function toArray(): array
    {
        $array = [
            'name' => $this->name,
            'description' => $this->description,
            'target' => $this->target,
            'http_debug' => $this->httpDebug,
        ];

        if (count($this->stages) > 0) {
            // Stages mode: omit virtual_users, duration, ramp_up
            $array['stages'] = array_map(fn (Stage $s) => $s->toArray(), $this->stages);
        } else {
            // Constant mode
            $array['virtual_users'] = $this->virtualUsers;
            if (trim($this->rampUp) !== '') {
                $array['ramp_up'] = $this->rampUp;
            }
            if (trim($this->duration) !== '') {
                $array['duration'] = $this->duration;
            }
        }

        if (trim($this->httpTimeout) !== '') {
            $array['http_timeout'] = $this->httpTimeout;
        }

        if (count($this->regionConfig) > 0) {
            $array['region_config'] = array_map(
                fn (string $region, int $weight) => ['region' => $region, 'weight' => $weight],
                array_keys($this->regionConfig),
                array_values($this->regionConfig)
            );
        }

        return $array;
    }

    public function setVirtualUsers(int $count): self
    {
        if ($count < 1) {
            throw new VoltTestException('Virtual users count must be at least 1');
        }
        $this->virtualUsers = $count;

        return $this;
    }

    public function setDuration(string $duration): self
    {
        if (! preg_match('/^\d+[smh]$/', $duration)) {
            throw new VoltTestException('Invalid duration format. Use <number>[s|m|h]');
        }
        $this->duration = $duration;

        return $this;
    }

    public function setRampUp(string $rampUp): self
    {
        if (! preg_match('/^\d+[smh]$/', $rampUp)) {
            throw new VoltTestException('Invalid ramp-up format. Use <number>[s|m|h]');
        }
        $this->rampUp = $rampUp;

        return $this;
    }

    public function setTarget(string $idleTimeout = '30s'): self
    {
        if (! preg_match('/^\d+[smh]$/', $idleTimeout)) {
            throw new VoltTestException('Invalid idle timeout format. Use <number>[s|m|h]');
        }
        $this->target['idle_timeout'] = $idleTimeout;

        return $this;
    }

    public function setHttpTimeout(string $httpTimeout): self
    {
        if (! preg_match('/^\d+[smh]$/', $httpTimeout)) {
            throw new VoltTestException('Invalid HTTP timeout format. Use <number>[s|m|h]');
        }
        $this->httpTimeout = $httpTimeout;

        return $this;
    }

    public function setHttpDebug(bool $httpDebug): self
    {
        $this->httpDebug = $httpDebug;

        return $this;
    }

    /**
     * @throws VoltTestException
     */
    public function addStage(string $duration, int $target): self
    {
        $this->stages[] = new Stage($duration, $target);

        return $this;
    }

    public function hasStages(): bool
    {
        return count($this->stages) > 0;
    }

    public function hasConstantLoad(): bool
    {
        return $this->virtualUsers > 1 || trim($this->duration) !== '' || trim($this->rampUp) !== '';
    }

    public function clearConstantLoad(): self
    {
        $this->virtualUsers = 1;
        $this->duration = '';
        $this->rampUp = '';

        return $this;
    }

    /**
     * @param array $regions Region code => weight (e.g., ['us-east-1' => 60, 'eu-west-1' => 40])
     *
     * @throws VoltTestException
     */
    public function setRegions(array $regions): self
    {
        if (empty($regions)) {
            throw new VoltTestException('Region distribution cannot be empty');
        }

        foreach ($regions as $region => $weight) {
            if (! is_string($region) || trim($region) === '') {
                throw new VoltTestException('Region code must be a non-empty string');
            }

            if (! is_int($weight)) {
                throw new VoltTestException('Region weight must be an integer');
            }

            if ($weight <= 0) {
                throw new VoltTestException('Region weight must be greater than 0');
            }
        }

        $sum = array_sum($regions);
        if ($sum !== 100) {
            throw new VoltTestException("Region weights must sum to 100, got {$sum}");
        }

        $this->regionConfig = $regions;

        return $this;
    }

    public function hasRegions(): bool
    {
        return count($this->regionConfig) > 0;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }
}
