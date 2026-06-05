<?php

namespace Tests\Units;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use VoltTest\Exceptions\VoltTestException;
use VoltTest\Stage;

class StageTest extends TestCase
{
    public function testConstructorWithValidParams(): void
    {
        $stage = new Stage('5m', 100);

        $this->assertEquals('5m', $stage->getDuration());
        $this->assertEquals(100, $stage->getTarget());
    }

    public function testZeroTargetIsAllowed(): void
    {
        $stage = new Stage('1m', 0);

        $this->assertEquals(0, $stage->getTarget());
    }

    #[DataProvider('validDurationProvider')]
    public function testValidDurations(string $duration): void
    {
        $stage = new Stage($duration, 10);

        $this->assertEquals($duration, $stage->getDuration());
    }

    public static function validDurationProvider(): array
    {
        return [
            'seconds' => ['30s'],
            'minutes' => ['5m'],
            'hours' => ['1h'],
            'zero seconds' => ['0s'],
            'large number' => ['999m'],
        ];
    }

    #[DataProvider('invalidDurationProvider')]
    public function testInvalidDurationThrows(string $duration): void
    {
        $this->expectException(VoltTestException::class);
        $this->expectExceptionMessage('Invalid stage duration format. Use <number>[s|m|h]');

        new Stage($duration, 10);
    }

    public static function invalidDurationProvider(): array
    {
        return [
            'empty' => [''],
            'no unit' => ['10'],
            'only unit' => ['s'],
            'invalid unit' => ['5x'],
            'word format' => ['30min'],
            'negative' => ['-1s'],
            'decimal' => ['1.5m'],
            'space' => ['5 m'],
        ];
    }

    public function testNegativeTargetThrows(): void
    {
        $this->expectException(VoltTestException::class);
        $this->expectExceptionMessage('Stage target must be non-negative');

        new Stage('5m', -1);
    }

    public function testNegativeLargeTargetThrows(): void
    {
        $this->expectException(VoltTestException::class);
        $this->expectExceptionMessage('Stage target must be non-negative');

        new Stage('5m', -100);
    }

    public function testToArray(): void
    {
        $stage = new Stage('5m', 100);

        $this->assertEquals([
            'duration' => '5m',
            'target' => 100,
        ], $stage->toArray());
    }

    public function testToArrayWithZeroTarget(): void
    {
        $stage = new Stage('2m', 0);

        $this->assertEquals([
            'duration' => '2m',
            'target' => 0,
        ], $stage->toArray());
    }
}
