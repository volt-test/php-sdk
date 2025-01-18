<?php

namespace Tests\Units;

use PHPUnit\Framework\TestCase;
use VoltTest\DataSourceConfiguration;
use VoltTest\Exceptions\VoltTestException;

class DataSourceConfigurationTest extends TestCase
{
    public function testValidConfigurationToArray()
    {
        $filePath = __DIR__ . '/files/test.csv';
        file_put_contents($filePath, "header1,header2\nvalue1,value2");

        $config = new DataSourceConfiguration($filePath, 'random', true);

        $result = $config->toArray();

        $this->assertEquals([
            'data_source' => realpath($filePath),
            'data_format' => 'csv',
            'has_header' => true,
            'mode' => 'random',
        ], $result);

        unlink($filePath);
    }

    public function testInvalidFileThrowsException()
    {
        $filePath = __DIR__ . '/files/nonexistent.csv';
        $this->expectException(VoltTestException::class);
        $this->expectExceptionMessage("Data source file '{$filePath}' does not exist");

        $config = new DataSourceConfiguration($filePath, 'random', true);
        $config->toArray();
    }

    public function testInvalidModeThrowsException()
    {
        $filePath = __DIR__ . '/files/test.csv';
        file_put_contents($filePath, "header1,header2\nvalue1,value2");

        $this->expectException(VoltTestException::class);
        $this->expectExceptionMessage('Invalid data source mode. Use "sequential", "random" or "unique"');

        $config = new DataSourceConfiguration($filePath, 'invalid_mode', true);
        $config->toArray();


        unlink($filePath);

    }

    public function testToArrayWithNoHeader()
    {
        $filePath = __DIR__ . '/files/test_no_header.csv';
        file_put_contents($filePath, "value1,value2");

        $config = new DataSourceConfiguration($filePath, 'sequential', false);


        $result = $config->toArray();

        $this->assertEquals([
            'data_source' => realpath($filePath),
            'data_format' => 'csv',
            'has_header' => false,
            'mode' => 'sequential',
        ], $result);

        unlink($filePath);
    }
}
