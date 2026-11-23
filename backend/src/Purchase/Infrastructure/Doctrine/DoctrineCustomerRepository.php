<?php

declare(strict_types=1);

namespace App\Purchase\Infrastructure\Doctrine;

use App\Purchase\Domain\Model\Cpf;
use App\Purchase\Domain\Model\Customer;
use App\Purchase\Domain\Repository\CustomerRepository;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineCustomerRepository implements CustomerRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function findByCpf(Cpf $cpf): ?Customer
    {
        return $this->entityManager->getRepository(Customer::class)->findOneBy(['cpf.number' => $cpf->number()]);
    }
}
