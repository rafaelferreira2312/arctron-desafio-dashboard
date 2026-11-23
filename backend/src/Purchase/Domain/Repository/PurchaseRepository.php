<?php

declare(strict_types=1);

namespace App\Purchase\Domain\Repository;

use App\Purchase\Domain\Exception\PurchaseAlreadyRegistered;
use App\Purchase\Domain\Model\Purchase;

interface PurchaseRepository
{
    public function existsWithReference(string $reference): bool;

    /**
     * @throws PurchaseAlreadyRegistered quando outra requisição gravou a mesma referência antes
     */
    public function save(Purchase $purchase): void;
}
