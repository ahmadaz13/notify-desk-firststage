<?php

namespace App\Support;

use InvalidArgumentException;

final class Money
{
    private const SCALE = 1000;

    private function __construct(private readonly int $minorUnits)
    {
    }

    public static function fromJod(mixed $amount): self
    {
        if (! is_string($amount) && ! is_int($amount)) {
            throw new InvalidArgumentException('Money amount must be provided as a decimal string or integer major unit.');
        }

        return new self(self::parseJod((string) $amount));
    }

    public static function roundedFromJod(mixed $amount): self
    {
        if (! is_string($amount) && ! is_int($amount)) {
            throw new InvalidArgumentException('Money amount must be provided as a decimal string or integer major unit.');
        }

        $value = trim((string) $amount);
        if (! preg_match('/^([+-]?)(\d+)(?:\.(\d+))?$/', $value, $matches)) {
            throw new InvalidArgumentException('Invalid JOD money amount.');
        }

        $fraction = $matches[3] ?? '';
        if (strlen($fraction) <= 3) {
            return self::fromJod($value);
        }

        $roundedFraction = substr($fraction, 0, 3);
        $roundingDigit = (int) $fraction[3];
        $minor = self::parseJod($matches[1].$matches[2].'.'.$roundedFraction);

        if ($roundingDigit >= 5) {
            $minor += $matches[1] === '-' ? -1 : 1;
        }

        return new self($minor);
    }

    public static function fromMinorUnits(int $minorUnits): self
    {
        return new self($minorUnits);
    }

    public function minorUnits(): int
    {
        return $this->minorUnits;
    }

    public function format(): string
    {
        $sign = $this->minorUnits < 0 ? '-' : '';
        $absolute = abs($this->minorUnits);
        $major = intdiv($absolute, self::SCALE);
        $fraction = $absolute % self::SCALE;

        return sprintf('%s%d.%03d', $sign, $major, $fraction);
    }

    public function add(self $other): self
    {
        return new self($this->minorUnits + $other->minorUnits);
    }

    public function subtract(self $other): self
    {
        return new self($this->minorUnits - $other->minorUnits);
    }

    public function compare(self $other): int
    {
        return $this->minorUnits <=> $other->minorUnits;
    }

    public function equals(self $other): bool
    {
        return $this->compare($other) === 0;
    }

    public function __toString(): string
    {
        return $this->format();
    }

    private static function parseJod(string $amount): int
    {
        $value = trim($amount);
        if (! preg_match('/^([+-]?)(\d+)(?:\.(\d{1,3}))?$/', $value, $matches)) {
            throw new InvalidArgumentException('Invalid JOD money amount.');
        }

        $sign = $matches[1] === '-' ? -1 : 1;
        $major = ltrim($matches[2], '0');
        $major = $major === '' ? '0' : $major;
        $fraction = str_pad($matches[3] ?? '', 3, '0');

        $minorString = ltrim($major.$fraction, '0');
        $minorString = $minorString === '' ? '0' : $minorString;

        if (self::isGreaterThanPhpIntMax($minorString)) {
            throw new InvalidArgumentException('Money amount exceeds supported integer range.');
        }

        $minor = (int) $minorString;

        return $minor * $sign;
    }

    private static function isGreaterThanPhpIntMax(string $minorUnits): bool
    {
        $max = (string) PHP_INT_MAX;

        return strlen($minorUnits) > strlen($max)
            || (strlen($minorUnits) === strlen($max) && strcmp($minorUnits, $max) > 0);
    }
}
