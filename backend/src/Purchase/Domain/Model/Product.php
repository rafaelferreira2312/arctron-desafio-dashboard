<?php

declare(strict_types=1);

namespace App\Purchase\Domain\Model;

use App\Purchase\Domain\Exception\InvalidPurchase;

/**
 * O payload só traz o nome do produto, então ele é a identidade.
 * "Camiseta Azul" e "camiseta  azul" são o mesmo produto.
 */
class Product
{
    private ?int $id = null;
    private string $name;
    private string $normalizedName;
    private \DateTimeImmutable $createdAt;

    private function __construct(string $name)
    {
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');

        if ($name === '') {
            throw InvalidPurchase::emptyProductName();
        }

        $this->name = $name;
        $this->normalizedName = self::normalize($name);
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function named(string $name): self
    {
        return new self($name);
    }

    public static function normalize(string $name): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $name) ?? ''));
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function normalizedName(): string
    {
        return $this->normalizedName;
    }
}
