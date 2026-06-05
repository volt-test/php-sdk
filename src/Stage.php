<?php

namespace VoltTest;

use VoltTest\Exceptions\VoltTestException;

class Stage
{
    private string $duration;

    private int $target;

    /**
     * @throws VoltTestException
     */
    public function __construct(string $duration, int $target)
    {
        if (! preg_match('/^\d+[smh]$/', $duration)) {
            throw new VoltTestException('Invalid stage duration format. Use <number>[s|m|h]');
        }
        if ($target < 0) {
            throw new VoltTestException('Stage target must be non-negative');
        }
        $this->duration = $duration;
        $this->target = $target;
    }

    public function getDuration(): string
    {
        return $this->duration;
    }

    public function getTarget(): int
    {
        return $this->target;
    }

    public function toArray(): array
    {
        return [
            'duration' => $this->duration,
            'target' => $this->target,
        ];
    }
}
