<?php

declare(strict_types=1);

namespace App\Purchase\Domain\Model;

use App\Purchase\Domain\Exception\InvalidPurchase;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class Purchase
{
    public const REFERENCE_MAX_LENGTH = 100;

    private ?int $id = null;
    private string $reference;
    private Customer $customer;
    private Money $amount;
    private Money $discount;
    private \DateTimeImmutable $purchasedAt;
    private ?string $notes;
    private PurchaseSource $source;
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, PurchaseItem> */
    private Collection $items;

    private function __construct()
    {
        $this->items = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    /**
     * @param list<PurchaseLine> $lines
     */
    public static function register(
        string $reference,
        Customer $customer,
        Money $amount,
        Money $discount,
        \DateTimeImmutable $purchasedAt,
        array $lines,
        ?string $notes = null,
        PurchaseSource $source = PurchaseSource::Api,
    ): self {
        $reference = trim($reference);

        if ($reference === '') {
            throw InvalidPurchase::emptyReference();
        }

        if (mb_strlen($reference) > self::REFERENCE_MAX_LENGTH) {
            throw InvalidPurchase::referenceTooLong(self::REFERENCE_MAX_LENGTH);
        }

        if ($lines === []) {
            throw InvalidPurchase::withoutProducts();
        }

        $purchase = new self();
        $purchase->reference = $reference;
        $purchase->customer = $customer;
        $purchase->amount = $amount;
        $purchase->discount = $discount;
        $purchase->purchasedAt = $purchasedAt;
        $purchase->notes = self::normalizeNotes($notes);
        $purchase->source = $source;

        foreach ($lines as $line) {
            $purchase->items->add(new PurchaseItem($purchase, $line->product, $line->quantity, $line->unitPrice));
        }

        if ($discount->isGreaterThan($purchase->productsTotal())) {
            throw InvalidPurchase::discountGreaterThanProducts();
        }

        return $purchase;
    }

    private static function normalizeNotes(?string $notes): ?string
    {
        $notes = $notes !== null ? trim($notes) : null;

        return $notes === '' ? null : $notes;
    }

    public function productsTotal(): Money
    {
        $total = Money::zero();

        foreach ($this->items as $item) {
            $total = $total->add($item->total());
        }

        return $total;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function reference(): string
    {
        return $this->reference;
    }

    public function customer(): Customer
    {
        return $this->customer;
    }

    public function amount(): Money
    {
        return $this->amount;
    }

    public function discount(): Money
    {
        return $this->discount;
    }

    public function purchasedAt(): \DateTimeImmutable
    {
        return $this->purchasedAt;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }

    public function source(): PurchaseSource
    {
        return $this->source;
    }

    /**
     * @return list<PurchaseItem>
     */
    public function items(): array
    {
        return array_values($this->items->toArray());
    }
}
