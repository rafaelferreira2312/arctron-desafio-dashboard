<?php

declare(strict_types=1);

namespace App\Purchase\Domain\Repository;

use App\Purchase\Domain\Model\Cpf;
use App\Purchase\Domain\Model\Customer;

interface CustomerRepository
{
    public function findByCpf(Cpf $cpf): ?Customer;
}
