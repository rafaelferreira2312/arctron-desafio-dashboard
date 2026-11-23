<?php

declare(strict_types=1);

namespace App\Purchase\Application\Command;

use App\Purchase\Domain\Model\PurchaseSource;

/**
 * Só tipos primitivos, pra poder ser serializado e trafegar por fila se precisar.
 */
final readonly class RegisterPurchase
{
    /**
     * @param list<PurchasedProduct> $products
     */
    public function __construct(
        public string $reference,
        public string $amount,
        public string $discount,
        public string $purchasedAt,
        public string $customerName,
        public string $customerEmail,
        public string $customerCpf,
        public array $products,
        public ?string $notes = null,
        public PurchaseSource $source = PurchaseSource::Api,
    ) {
    }
}
