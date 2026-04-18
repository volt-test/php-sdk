<?php

namespace Tests\Units;

use PHPUnit\Framework\TestCase;
use VoltTest\CloudClient;
use VoltTest\Exceptions\AuthenticationException;

class CloudClientTest extends TestCase
{
    public function testConstructorWithValidApiKey(): void
    {
        $client = new CloudClient('vt_test_key_123');

        $this->assertEquals('vt_test_key_123', $this->getPrivateProperty($client, 'apiKey'));
    }

    public function testConstructorWithCustomBaseUrl(): void
    {
        $client = new CloudClient('vt_test_key_123', 'https://custom.api.com/v1');

        $this->assertEquals('https://custom.api.com/v1', $this->getPrivateProperty($client, 'baseUrl'));
    }

    public function testConstructorDefaultBaseUrl(): void
    {
        $client = new CloudClient('vt_test_key_123');

        $reflection = new \ReflectionClass(CloudClient::class);
        $constant = $reflection->getReflectionConstant('BASE_URL');
        $expectedUrl = $constant->getValue();

        $this->assertEquals($expectedUrl, $this->getPrivateProperty($client, 'baseUrl'));
    }

    public function testConstructorThrowsOnEmptyApiKey(): void
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('API key is required');

        new CloudClient('');
    }

    public function testConstructorThrowsOnInvalidPrefix(): void
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('API key must start with "vt_"');

        new CloudClient('invalid_key_123');
    }

    public function testConstructorThrowsOnWhitespaceKey(): void
    {
        $this->expectException(AuthenticationException::class);

        new CloudClient('   ');
    }

    private function getPrivateProperty(object $object, string $propertyName): mixed
    {
        $reflection = new \ReflectionClass(get_class($object));
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);

        return $property->getValue($object);
    }
}
