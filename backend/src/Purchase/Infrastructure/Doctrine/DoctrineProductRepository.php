<?php

declare(strict_types=1);

namespace App\Purchase\Infrastructure\Doctrine;

use App\Purchase\Domain\Model\Product;
use App\Purchase\Domain\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineProductRepository implements ProductRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function findByName(string $name): ?Product
    {
        return $this->entityManager->getRepository(Product::class)
            ->findOneBy(['normalizedName' => Product::normalize($name)]);
    }
}
