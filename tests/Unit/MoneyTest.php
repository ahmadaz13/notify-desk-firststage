<?php

namespace Tests\Unit;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_converts_one_jod_to_one_thousand_fils_exactly(): void
    {
        $money = Money::fromJod('1.000');

        $this->assertSame(1000, $money->minorUnits());
        $this->assertSame('1.000', $money->format());
    }

    public function test_converts_one_fil_exactly(): void
    {
        $money = Money::fromJod('0.001');

        $this->assertSame(1, $money->minorUnits());
        $this->assertSame('0.001', $money->format());
    }

    public function test_converts_decimal_jod_to_fils_exactly(): void
    {
        $money = Money::fromJod('12.375');

        $this->assertSame(12375, $money->minorUnits());
        $this->assertSame('12.375', $money->format());
    }

    public function test_addition_is_exact(): void
    {
        $sum = Money::fromJod('0.001')->add(Money::fromJod('12.374'));

        $this->assertSame(12375, $sum->minorUnits());
        $this->assertSame('12.375', $sum->format());
    }

    public function test_subtraction_is_exact(): void
    {
        $difference = Money::fromJod('12.375')->subtract(Money::fromJod('0.001'));

        $this->assertSame(12374, $difference->minorUnits());
        $this->assertSame('12.374', $difference->format());
    }

    public function test_comparison_is_exact(): void
    {
        $this->assertSame(0, Money::fromJod('12.375')->compare(Money::fromMinorUnits(12375)));
        $this->assertSame(1, Money::fromJod('12.376')->compare(Money::fromJod('12.375')));
        $this->assertSame(-1, Money::fromJod('12.374')->compare(Money::fromJod('12.375')));
    }

    public function test_rejects_four_decimal_input_by_default(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromJod('12.3755');
    }

    public function test_explicit_rounding_accepts_four_decimal_input(): void
    {
        $this->assertSame(12376, Money::roundedFromJod('12.3755')->minorUnits());
    }

    public function test_rejects_float_input(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromJod(12.375);
    }
}
