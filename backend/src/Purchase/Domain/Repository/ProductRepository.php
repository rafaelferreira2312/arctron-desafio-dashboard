<?php

declare(strict_types=1);

namespace App\Purchase\Domain\Repository;

use App\Purchase\Domain\Model\Product;

interface ProductRepository
{
    public function findByName(string $name): ?Product;
}
