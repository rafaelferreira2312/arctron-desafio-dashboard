<?php

declare(strict_types=1);

namespace App\Purchase\Domain\Model;

use App\Purchase\Domain\Exception\InvalidPurchase;

final class Cpf
{
    private string $number;

    private function __construct(string $number)
    {
        $this->number = $number;
    }

    public static function fromString(string $value): self
    {
        $digits = self::onlyDigits($value);

        if (!self::isValid($digits)) {
            throw InvalidPurchase::invalidCpf($value);
        }

        return new self($digits);
    }

    public static function onlyDigits(string $value): string
    {
        return preg_replace('/\D/', '', $value) ?? '';
    }

    public static function isValid(string $digits): bool
    {
        if (strlen($digits) !== 11 || preg_match('/^(\d)\1{10}$/', $digits)) {
            return false;
        }

        for ($position = 9; $position < 11; ++$position) {
            $sum = 0;
            for ($i = 0; $i < $position; ++$i) {
                $sum += (int) $digits[$i] * (($position + 1) - $i);
            }

            $checkDigit = ((10 * $sum) % 11) % 10;

            if ((int) $digits[$position] !== $checkDigit) {
                return false;
            }
        }

        return true;
    }

    public function number(): string
    {
        return $this->number;
    }

    public function formatted(): string
    {
        return self::format($this->number);
    }

    public static function format(string $digits): string
    {
        return sprintf(
            '%s.%s.%s-%s',
            substr($digits, 0, 3),
            substr($digits, 3, 3),
            substr($digits, 6, 3),
            substr($digits, 9, 2),
        );
    }

    public function equals(self $other): bool
    {
        return $this->number === $other->number;
    }
}
