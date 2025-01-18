<?php

namespace Tests\Units;

use PHPUnit\Framework\TestCase;
use VoltTest\DataSourceConfiguration;
use VoltTest\Exceptions\VoltTestException;
use VoltTest\Scenario;
use VoltTest\Step;

class ScenarioTest extends TestCase
{
    private Scenario $scenario;

    public function setUp(): void
    {
        $this->scenario = new Scenario('test-scenario', 'Test Description');
    }

    public function testScenarioCreation(): void
    {
        $scenarioArray = $this->scenario->toArray();

        $this->assertEquals('test-scenario', $scenarioArray['name']);
        $this->assertEquals('Test Description', $scenarioArray['description']);
        $this->assertEquals(100, $scenarioArray['weight']);
        $this->assertFalse($scenarioArray['auto_handle_cookies']);
        $this->assertIsArray($scenarioArray['steps']);
        $this->assertEmpty($scenarioArray['steps']);
    }

    public function testSetWeight(): void
    {
        $this->scenario->setWeight(50);
        $this->assertEquals(50, $this->scenario->getWeight());
        $this->assertEquals(50, $this->scenario->toArray()['weight']);
    }

    public function testSetThinkTime(): void
    {
        $this->scenario->setThinkTime('2s');
        $this->assertEquals('2s', $this->scenario->toArray()['think_time']);
    }

    public function testAutoHandleCookies(): void
    {
        $this->scenario->autoHandleCookies();
        $this->assertTrue($this->scenario->toArray()['auto_handle_cookies']);
    }

    public function testAddStep(): void
    {
        $step = $this->scenario->step('login');

        $this->assertInstanceOf(Step::class, $step);
        $this->assertCount(1, $this->scenario->toArray()['steps']);
    }

    public function testMultipleSteps(): void
    {
        $this->scenario
            ->step('login')
            ->post('http://example.com/login')
            ->validateStatus('success', 200);

        $this->scenario
            ->step('get-profile')
            ->get('http://example.com/profile')
            ->validateStatus('success', 200);

        $scenarioArray = $this->scenario->toArray();
        $this->assertCount(2, $scenarioArray['steps']);

        // Verify first step
        $this->assertEquals('login', $scenarioArray['steps'][0]['name']);
        $this->assertEquals('POST', $scenarioArray['steps'][0]['request']['method']);

        // Verify second step
        $this->assertEquals('get-profile', $scenarioArray['steps'][1]['name']);
        $this->assertEquals('GET', $scenarioArray['steps'][1]['request']['method']);
    }

    public function testFluentInterface(): void
    {
        $result = $this->scenario
            ->setWeight(75)
            ->setThinkTime('3s')
            ->autoHandleCookies();

        $this->assertInstanceOf(Scenario::class, $result);

        $scenarioArray = $result->toArray();
        $this->assertEquals(75, $scenarioArray['weight']);
        $this->assertEquals('3s', $scenarioArray['think_time']);
        $this->assertTrue($scenarioArray['auto_handle_cookies']);
    }

    public function testComplexScenario(): void
    {
        $scenario = $this->scenario
            ->setWeight(60)
            ->setThinkTime('2s')
            ->autoHandleCookies();
        $scenario
            ->step('login')
            ->post('http://example.com/login')
            ->header('Content-Type', 'application/json')
            ->validateStatus('login-success', 200);
        $scenario
            ->step('get-profile')
            ->get('http://example.com/profile')
            ->validateStatus('profile-success', 200);
        $scenarioArray = $this->scenario->toArray();

        $this->assertEquals(60, $scenarioArray['weight']);
        $this->assertEquals('2s', $scenarioArray['think_time']);
        $this->assertTrue($scenarioArray['auto_handle_cookies']);
        $this->assertCount(2, $scenarioArray['steps']);
    }

    public function testInvalidThinkTimeThrowsException(): void
    {
        $this->expectException(VoltTestException::class);
        $this->scenario->setThinkTime('invalid');
    }

    public function testSetDataSourceConfigurationWithNoHeader(): void
    {
        $filePath = __DIR__ . '/files/test_no_header.csv';
        file_put_contents($filePath, "value1,value2");
        $this->scenario->setDataSourceConfiguration(new DataSourceConfiguration($filePath, 'sequential', false));
        $scenarioArray = $this->scenario->toArray();
        $this->assertArrayHasKey('data_config', $scenarioArray);
        $this->assertIsArray($scenarioArray['data_config']);
        $dataConfig = $scenarioArray['data_config'];
        $this->assertArrayHasKey('data_source', $dataConfig);
        $this->assertArrayHasKey('data_format', $dataConfig);
        $this->assertArrayHasKey('has_header', $dataConfig);
        $this->assertArrayHasKey('mode', $dataConfig);
        unlink($filePath);
    }

    public function testSetDataSourceConfigurationWithNoDataSource(): void
    {
        $this->expectException(VoltTestException::class);
        $this->expectExceptionMessage("Data source file '' does not exist");
        $this->scenario->setDataSourceConfiguration(new DataSourceConfiguration('', 'sequential', false));
        $this->scenario->toArray();
    }

    public function testSetDataSourceConfigurationWithInvalidMode(): void
    {
        $filePath = __DIR__ . '/files/test.csv';
        file_put_contents($filePath, "header1,header2\nvalue1,value2");
        $this->expectException(VoltTestException::class);
        $this->expectExceptionMessage('Invalid data source mode. Use "sequential", "random" or "unique');
        $this->scenario->setDataSourceConfiguration(new DataSourceConfiguration($filePath, 'invalid_mode', true));
        $this->scenario->toArray();
        unlink($filePath);
    }

    public function testSetDataSourceConfiguration(): void
    {
        $filePath = __DIR__ . '/files/test.csv';
        file_put_contents($filePath, "header1,header2\nvalue1,value2");
        $this->scenario->setDataSourceConfiguration(new DataSourceConfiguration($filePath, 'random', true));
        $scenarioArray = $this->scenario->toArray();
        $this->assertArrayHasKey('data_config', $scenarioArray);
        $this->assertIsArray($scenarioArray['data_config']);
        $dataConfig = $scenarioArray['data_config'];
        $this->assertArrayHasKey('data_source', $dataConfig);
        $this->assertArrayHasKey('data_format', $dataConfig);
        $this->assertArrayHasKey('has_header', $dataConfig);
        $this->assertArrayHasKey('mode', $dataConfig);
        unlink($filePath);
    }
}
