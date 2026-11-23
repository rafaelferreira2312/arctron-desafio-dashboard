<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Purchase\Domain\Model\Product;
use App\Purchase\Domain\Repository\ProductRepository;

final class InMemoryProductRepository implements ProductRepository
{
    /** @var array<string, Product> */
    public array $products = [];

    public function add(Product $product): void
    {
        $this->products[$product->normalizedName()] = $product;
    }

    public function findByName(string $name): ?Product
    {
        return $this->products[Product::normalize($name)] ?? null;
    }
}
