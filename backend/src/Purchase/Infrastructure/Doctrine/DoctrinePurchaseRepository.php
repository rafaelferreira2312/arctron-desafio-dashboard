<?php

declare(strict_types=1);

namespace App\Purchase\Infrastructure\Doctrine;

use App\Purchase\Domain\Exception\PurchaseAlreadyRegistered;
use App\Purchase\Domain\Model\Purchase;
use App\Purchase\Domain\Repository\PurchaseRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrinePurchaseRepository implements PurchaseRepository
{
    private const REFERENCE_CONSTRAINT = 'uniq_purchases_reference';

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function existsWithReference(string $reference): bool
    {
        return $this->entityManager->getConnection()
            ->fetchOne('SELECT 1 FROM purchases WHERE reference = ?', [$reference]) !== false;
    }

    public function save(Purchase $purchase): void
    {
        $this->entityManager->persist($purchase);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $e) {
            if (str_contains($e->getMessage(), self::REFERENCE_CONSTRAINT)) {
                throw PurchaseAlreadyRegistered::withReference($purchase->reference());
            }

            throw $e;
        }
    }
}
