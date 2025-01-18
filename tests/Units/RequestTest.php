<?php

namespace Tests\Units;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use VoltTest\Exceptions\InvalidRequestValidationException;
use VoltTest\Request;

class RequestTest extends TestCase
{
    private Request $request;

    protected function setUp(): void
    {
        $this->request = new Request();
    }

    public function testRequestCreation()
    {
        $request = $this->request;
        $request->setMethod('POST')
            ->setUrl('http://example.com')
            ->addHeader('Content-Type', 'application/json')
            ->setBody('{"key": "value"}');


        $expectedArray = [
            'method' => 'POST',
            'url' => 'http://example.com',
            'header' => ['Content-Type' => 'application/json'],
            'body' => '{"key": "value"}',
        ];

        $this->assertEquals($expectedArray, $request->toArray());

        $this->assertTrue($request->validate());
    }

    #[DataProvider('validMethodProvider')]
    public function testValidMethods(string $method): void
    {
        $this->request->setMethod($method);
        $this->assertEquals(strtoupper($method), $this->request->toArray()['method']);
    }

    public static function validMethodProvider(): array
    {
        return [
            ['GET'],
            ['POST'],
            ['PUT'],
            ['DELETE'],
            ['PATCH'],
            ['HEAD'],
            ['OPTIONS'],
            ['get'],
            ['Post'],
        ];
    }

    public function testInvalidMethod(): void
    {
        $this->expectException(InvalidRequestValidationException::class);
        $this->request->setMethod('INVALID');
    }

    #[DataProvider('validUrlProvider')]
    public function testValidUrls(string $url): void
    {
        $this->request->setUrl($url);
        $this->assertEquals($url, $this->request->toArray()['url']);
    }

    public static function validUrlProvider(): array
    {
        return [
            ['https://example.com'],
            ['http://localhost'],
            ['https://api.example.com/v1/users'],
            ['http://192.168.1.1'],
            ['https://subdomain.example.com:8080'],
        ];
    }

    #[DataProvider('invalidUrlProvider')]
    public function testInvalidUrls(string $url): void
    {
        $this->expectException(InvalidRequestValidationException::class);
        $this->request->setUrl($url);
    }

    public static function invalidUrlProvider(): array
    {
        return [
            [''],
            ['not-a-url'],
            ['http://'],
            ['https://'],
            ['ftp://example.com'],
        ];
    }

    #[DataProvider('validHeaderProvider')]
    public function testValidHeaders(string $name, string $value): void
    {
        $this->request->addHeader($name, $value);
        $headers = $this->request->toArray()['header'];
        $this->assertArrayHasKey($name, $headers);
        $this->assertEquals($value, $headers[$name]);
    }

    public static function validHeaderProvider(): array
    {
        return [
            ['Content-Type', 'application/json'],
            ['Authorization', 'Bearer token123'],
            ['X-Custom-Header', 'custom-value'],
            ['Accept', '*/*'],
            ['User-Agent', 'PHPUnit Test'],
        ];
    }

    #[DataProvider('invalidHeaderProvider')]
    public function testInvalidHeaders(string $name, string $value): void
    {
        $this->expectException(InvalidRequestValidationException::class);
        $this->request->addHeader($name, $value);
    }

    public static function invalidHeaderProvider(): array
    {
        return [
            ['', 'value'],
            ['Invalid Header', 'value'],
            ['Content-Type', "value\r\nInjection"],
            ['Header@Invalid', 'value'],
            ['(Invalid)', 'value'],
        ];
    }

    public function testBodyWithGetMethod(): void
    {
        $this->expectException(InvalidRequestValidationException::class);
        $this->request->setMethod('GET')->setBody('{"key": "value"}');
    }

    public function testBodyWithHeadMethod(): void
    {
        $this->expectException(InvalidRequestValidationException::class);
        $this->request->setMethod('HEAD')->setBody('{"key": "value"}');
    }

    public function testValidationWithMissingUrl(): void
    {
        $this->expectException(InvalidRequestValidationException::class);
        $this->request->setMethod('GET')
            ->addHeader('Content-Type', 'application/json')
            ->setBody('{"key": "value"}')
            ->validate();
    }

    public function testValidationWithBodyWithoutContentType(): void
    {
        $this->expectException(InvalidRequestValidationException::class);
        $this->request
            ->setMethod('POST')
            ->setUrl('https://example.com')
            ->setBody('{"key": "value"}')
            ->validate();
    }

    public function testCompleteValidRequest(): void
    {
        $request = $this->request
            ->setMethod('POST')
            ->setUrl('https://example.com')
            ->addHeader('Content-Type', 'application/json')
            ->addHeader('Authorization', 'Bearer token123')
            ->setBody('{"key": "value"}');

        $this->assertTrue($request->validate());
    }
}
