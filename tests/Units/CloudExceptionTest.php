<?php

namespace Tests\Units;

use PHPUnit\Framework\TestCase;
use VoltTest\Exceptions\AuthenticationException;
use VoltTest\Exceptions\CloudConnectionException;
use VoltTest\Exceptions\CloudException;
use VoltTest\Exceptions\CloudTimeoutException;
use VoltTest\Exceptions\PlanLimitException;
use VoltTest\Exceptions\RunFailedException;
use VoltTest\Exceptions\VoltTestException;

class CloudExceptionTest extends TestCase
{
    public function testCloudExceptionExtendsVoltTestException(): void
    {
        $e = new CloudException('cloud error');

        $this->assertInstanceOf(VoltTestException::class, $e);
    }

    public function testAuthenticationExceptionExtendsCloudException(): void
    {
        $e = new AuthenticationException('auth failed');

        $this->assertInstanceOf(CloudException::class, $e);
        $this->assertInstanceOf(VoltTestException::class, $e);
    }

    public function testPlanLimitExceptionExtendsCloudException(): void
    {
        $e = new PlanLimitException('plan limit');

        $this->assertInstanceOf(CloudException::class, $e);
        $this->assertInstanceOf(VoltTestException::class, $e);
    }

    public function testCloudTimeoutExceptionExtendsCloudException(): void
    {
        $e = new CloudTimeoutException('timed out');

        $this->assertInstanceOf(CloudException::class, $e);
        $this->assertInstanceOf(VoltTestException::class, $e);
    }

    public function testCloudConnectionExceptionExtendsCloudException(): void
    {
        $e = new CloudConnectionException('connection failed');

        $this->assertInstanceOf(CloudException::class, $e);
        $this->assertInstanceOf(VoltTestException::class, $e);
    }

    public function testRunFailedExceptionExtendsCloudException(): void
    {
        $e = new RunFailedException('run failed');

        $this->assertInstanceOf(CloudException::class, $e);
        $this->assertInstanceOf(VoltTestException::class, $e);
    }

    public function testExceptionMessagesArePreserved(): void
    {
        $exceptions = [
            new CloudException('cloud msg'),
            new AuthenticationException('auth msg'),
            new PlanLimitException('plan msg'),
            new CloudTimeoutException('timeout msg'),
            new CloudConnectionException('conn msg'),
            new RunFailedException('run msg'),
        ];

        $expectedMessages = ['cloud msg', 'auth msg', 'plan msg', 'timeout msg', 'conn msg', 'run msg'];

        foreach ($exceptions as $i => $exception) {
            $this->assertEquals($expectedMessages[$i], $exception->getMessage());
        }
    }
}
