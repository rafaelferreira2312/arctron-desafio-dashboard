<?php

declare(strict_types=1);

namespace App\Purchase\Application\Command;

final readonly class PurchasedProduct
{
    public function __construct(
        public string $name,
        public int $quantity,
        public string $unitPrice,
    ) {
    }
}
