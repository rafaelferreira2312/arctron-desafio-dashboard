<?php

declare(strict_types=1);

namespace App\Purchase\Domain\Model;

use App\Purchase\Domain\Exception\InvalidPurchase;

/**
 * Valor monetário em centavos. Nesse domínio não existe valor negativo.
 */
final class Money
{
    private int $cents;

    private function __construct(int $cents)
    {
        if ($cents < 0) {
            throw InvalidPurchase::negativeValue();
        }

        $this->cents = $cents;
    }

    public static function fromCents(int $cents): self
    {
        return new self($cents);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    /**
     * Aceita "150", "150.9", "150.90" ou um número vindo do JSON.
     */
    public static function fromDecimal(string|float|int $value): self
    {
        if (is_float($value) || is_int($value)) {
            $value = number_format((float) $value, 2, '.', '');
        }

        $value = trim($value);

        if (!preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $value, $matches)) {
            throw InvalidPurchase::invalidMoney($value);
        }

        $fraction = str_pad($matches[2] ?? '0', 2, '0');

        return new self(((int) $matches[1]) * 100 + (int) $fraction);
    }

    public static function format(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }

    public function cents(): int
    {
        return $this->cents;
    }

    public function add(self $other): self
    {
        return new self($this->cents + $other->cents);
    }

    public function multiply(int $times): self
    {
        return new self($this->cents * $times);
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->cents > $other->cents;
    }

    public function equals(self $other): bool
    {
        return $this->cents === $other->cents;
    }

    public function toDecimal(): string
    {
        return self::format($this->cents);
    }
}
