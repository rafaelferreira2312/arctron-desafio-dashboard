<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Purchase\Domain\Model\Cpf;
use App\Purchase\Domain\Model\Customer;
use App\Purchase\Domain\Repository\CustomerRepository;

final class InMemoryCustomerRepository implements CustomerRepository
{
    /** @var array<string, Customer> */
    public array $customers = [];

    public function add(Customer $customer): void
    {
        $this->customers[$customer->cpf()->number()] = $customer;
    }

    public function findByCpf(Cpf $cpf): ?Customer
    {
        return $this->customers[$cpf->number()] ?? null;
    }
}
