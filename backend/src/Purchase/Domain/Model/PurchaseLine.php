<?php

declare(strict_types=1);

namespace App\Purchase\Domain\Model;

/**
 * Uma linha da compra antes de virar PurchaseItem.
 */
final readonly class PurchaseLine
{
    public function __construct(
        public Product $product,
        public int $quantity,
        public Money $unitPrice,
    ) {
    }
}
