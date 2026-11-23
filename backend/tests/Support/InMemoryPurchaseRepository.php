<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Purchase\Domain\Exception\PurchaseAlreadyRegistered;
use App\Purchase\Domain\Model\Purchase;
use App\Purchase\Domain\Repository\PurchaseRepository;

final class InMemoryPurchaseRepository implements PurchaseRepository
{
    /** @var array<string, Purchase> */
    public array $purchases = [];

    // Simula outra requisição gravando a mesma referência entre o "exists" e o "save"
    public bool $simulateRaceCondition = false;

    public function existsWithReference(string $reference): bool
    {
        return isset($this->purchases[$reference]);
    }

    public function save(Purchase $purchase): void
    {
        if ($this->simulateRaceCondition || isset($this->purchases[$purchase->reference()])) {
            throw PurchaseAlreadyRegistered::withReference($purchase->reference());
        }

        $this->purchases[$purchase->reference()] = $purchase;
    }
}
