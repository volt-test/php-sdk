<?php

namespace VoltTest;

use VoltTest\Exceptions\VoltTestException;

class Scenario
{
    private string $name;

    private string $description;

    private int $weight = 100;

    private string $thinkTime = ''; // Default think time which is default think time after each step

    private bool $autoHandleCookies = false;

    private array $steps = [];

    private ?DataSourceConfiguration $dataSourceConfiguration;

    public function __construct(string $name, string $description = '')
    {
        $this->name = $name;
        $this->description = $description;
        $this->dataSourceConfiguration = null;
    }

    public function step(string $name): Step
    {
        $step = new Step($name);
        $this->steps[] = $step;

        return $step;
    }

    public function setWeight(int $weight): self
    {
        $this->weight = $weight;

        return $this;
    }

    public function setThinkTime(string $thinkTime): self
    {
        if (! preg_match('/^\d+[smh]$/', $thinkTime)) {
            throw new VoltTestException('Invalid think time format. Use <number>[s|m|h]');
        }
        $this->thinkTime = $thinkTime;

        return $this;
    }

    public function getWeight(): int
    {
        return $this->weight;
    }

    public function autoHandleCookies(): self
    {
        $this->autoHandleCookies = true;

        return $this;
    }

    /**
     * @throws VoltTestException
     */
    public function setDataSourceConfiguration(DataSourceConfiguration $dataSourceConfiguration): self
    {
        if (! is_null($this->dataSourceConfiguration)) {
            throw new VoltTestException('Data source configuration already set');
        }
        $this->dataSourceConfiguration = $dataSourceConfiguration;
        $this->dataSourceConfiguration->validate();

        return $this;
    }

    /**
     * @throws VoltTestException
     */
    public function toArray(): array
    {
        $array = [
            'name' => $this->name,
            'description' => $this->description,
            'weight' => $this->weight,
            'steps' => array_map(function (Step $step) {
                return $step->toArray();
            }, $this->steps),
            'auto_handle_cookies' => $this->autoHandleCookies,
        ];
        if (trim($this->thinkTime) !== '') {
            $array['think_time'] = $this->thinkTime;
        }
        if (! is_null($this->dataSourceConfiguration)) {
            $array['data_config'] = $this->dataSourceConfiguration->toArray();
        }

        return $array;
    }
}
