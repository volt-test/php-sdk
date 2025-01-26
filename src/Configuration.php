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

    private bool $httpDebug = false;

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
            'virtual_users' => $this->virtualUsers,
            'target' => $this->target,
            'http_debug' => $this->httpDebug,
        ];
        if (trim($this->rampUp) !== '') {
            $array['ramp_up'] = $this->rampUp;
        }
        if (trim($this->duration) !== '') {
            $array['duration'] = $this->duration;
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

    public function setHttpDebug(bool $httpDebug): self
    {
        $this->httpDebug = $httpDebug;

        return $this;
    }
}
