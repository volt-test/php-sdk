<?php

namespace VoltTest;

use VoltTest\Exceptions\VoltTestException;

class DataSourceConfiguration
{
    private string $dataSource;

    private string $mode;

    private bool $hasHeader;

    public function __construct(string $dataSource, string $mode, bool $hasHeader)
    {
        $this->dataSource = $dataSource;
        $this->mode = $mode;
        $this->hasHeader = $hasHeader;
    }

    /**
     * @throws VoltTestException
     */
    public function toArray(): array
    {
        $this->validate();

        return [
            'data_source' => realpath($this->dataSource),
            'data_format' => 'csv',
            'has_header' => $this->hasHeader,
            'mode' => $this->mode,
        ];
    }

    public function validate(): void
    {
        if (! file_exists($this->dataSource)) {
            throw new VoltTestException("Data source file '{$this->dataSource}' does not exist");
        }

        if (! in_array($this->mode, ['sequential', 'random','unique'])) {
            throw new VoltTestException('Invalid data source mode. Use "sequential", "random" or "unique"');
        }
    }
}
