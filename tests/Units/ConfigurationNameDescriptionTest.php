<?php

namespace Tests\Units;

use PHPUnit\Framework\TestCase;
use VoltTest\Configuration;

class ConfigurationNameDescriptionTest extends TestCase
{
    private Configuration $config;

    public function setUp(): void
    {
        $this->config = new Configuration('Original Name', 'Original Description');
    }

    public function testSetNameUpdatesName(): void
    {
        $this->config->setName('Updated Name');
        $this->assertEquals('Updated Name', $this->config->toArray()['name']);
    }

    public function testSetDescriptionUpdatesDescription(): void
    {
        $this->config->setDescription('Updated Description');
        $this->assertEquals('Updated Description', $this->config->toArray()['description']);
    }

    public function testSetNameReturnsSelf(): void
    {
        $result = $this->config->setName('New Name');
        $this->assertInstanceOf(Configuration::class, $result);
    }

    public function testSetDescriptionReturnsSelf(): void
    {
        $result = $this->config->setDescription('New Description');
        $this->assertInstanceOf(Configuration::class, $result);
    }

    public function testSetNameOverwritesPreviousValue(): void
    {
        $this->config->setName('First');
        $this->config->setName('Second');
        $this->assertEquals('Second', $this->config->toArray()['name']);
    }

    public function testSetDescriptionOverwritesPreviousValue(): void
    {
        $this->config->setDescription('First');
        $this->config->setDescription('Second');
        $this->assertEquals('Second', $this->config->toArray()['description']);
    }

    public function testSetNameAllowsEmptyString(): void
    {
        $this->config->setName('');
        $this->assertEquals('', $this->config->toArray()['name']);
    }

    public function testSetDescriptionAllowsEmptyString(): void
    {
        $this->config->setDescription('');
        $this->assertEquals('', $this->config->toArray()['description']);
    }

    public function testSetNameAndDescriptionTogether(): void
    {
        $this->config->setName('New Name')->setDescription('New Description');

        $array = $this->config->toArray();
        $this->assertEquals('New Name', $array['name']);
        $this->assertEquals('New Description', $array['description']);
    }

    public function testConstructorValuesUnchangedWithoutSetters(): void
    {
        $array = $this->config->toArray();
        $this->assertEquals('Original Name', $array['name']);
        $this->assertEquals('Original Description', $array['description']);
    }
}
