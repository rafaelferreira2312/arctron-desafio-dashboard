<?php

declare(strict_types=1);

namespace App\Purchase\Domain\Model;

use App\Purchase\Domain\Exception\InvalidPurchase;

class PurchaseItem
{
    private ?int $id = null;
    private Purchase $purchase;
    private Product $product;
    // Nome do produto no momento da compra, mesmo que o cadastro mude depois.
    private string $productName;
    private int $quantity;
    private Money $unitPrice;

    public function __construct(Purchase $purchase, Product $product, int $quantity, Money $unitPrice)
    {
        if ($quantity <= 0) {
            throw InvalidPurchase::invalidQuantity($quantity);
        }

        $this->purchase = $purchase;
        $this->product = $product;
        $this->productName = $product->name();
        $this->quantity = $quantity;
        $this->unitPrice = $unitPrice;
    }

    public function product(): Product
    {
        return $this->product;
    }

    public function productName(): string
    {
        return $this->productName;
    }

    public function quantity(): int
    {
        return $this->quantity;
    }

    public function unitPrice(): Money
    {
        return $this->unitPrice;
    }

    public function total(): Money
    {
        return $this->unitPrice->multiply($this->quantity);
    }
}
