<?php

declare(strict_types=1);

namespace App\Purchase\Domain\Exception;

final class InvalidPurchase extends \DomainException
{
    public static function emptyReference(): self
    {
        return new self('O código de referência da compra é obrigatório.');
    }

    public static function referenceTooLong(int $max): self
    {
        return new self(sprintf('O código de referência deve ter no máximo %d caracteres.', $max));
    }

    public static function withoutProducts(): self
    {
        return new self('A compra precisa ter pelo menos um produto.');
    }

    public static function invalidQuantity(int $quantity): self
    {
        return new self(sprintf('Quantidade inválida: %d. A quantidade deve ser maior que zero.', $quantity));
    }

    public static function discountGreaterThanProducts(): self
    {
        return new self('O desconto não pode ser maior que o valor total dos produtos.');
    }

    public static function negativeValue(): self
    {
        return new self('Valores monetários não podem ser negativos.');
    }

    public static function invalidMoney(string $value): self
    {
        return new self(sprintf('Valor monetário inválido: "%s". Use o formato 150.90.', $value));
    }

    public static function invalidCpf(string $value): self
    {
        return new self(sprintf('CPF inválido: "%s".', $value));
    }

    public static function invalidEmail(string $value): self
    {
        return new self(sprintf('E-mail inválido: "%s".', $value));
    }

    public static function emptyCustomerName(): self
    {
        return new self('O nome do cliente é obrigatório.');
    }

    public static function emptyProductName(): self
    {
        return new self('O nome do produto é obrigatório.');
    }

    public static function invalidDate(string $value): self
    {
        return new self(sprintf('Data inválida: "%s". Use o formato ISO 8601, ex: 2026-11-20T14:30:00-03:00.', $value));
    }
}
