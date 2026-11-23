<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain;

use App\Purchase\Domain\Exception\InvalidPurchase;
use App\Purchase\Domain\Model\Cpf;
use App\Purchase\Domain\Model\Customer;
use App\Purchase\Domain\Model\Money;
use App\Purchase\Domain\Model\Product;
use App\Purchase\Domain\Model\Purchase;
use App\Purchase\Domain\Model\PurchaseLine;
use PHPUnit\Framework\TestCase;

final class PurchaseTest extends TestCase
{
    public function testRegistersPurchaseWithItems(): void
    {
        $purchase = $this->register(lines: [
            new PurchaseLine(Product::named('Teclado'), 2, Money::fromDecimal('100.00')),
            new PurchaseLine(Product::named('Mouse'), 1, Money::fromDecimal('50.00')),
        ], discount: '25.00', amount: '225.00');

        self::assertSame('PED-1', $purchase->reference());
        self::assertCount(2, $purchase->items());
        self::assertSame(25000, $purchase->productsTotal()->cents());
        self::assertSame(2500, $purchase->discount()->cents());
        self::assertSame('Teclado', $purchase->items()[0]->productName());
        self::assertSame(20000, $purchase->items()[0]->total()->cents());
    }

    public function testTrimsReferenceAndEmptyNotes(): void
    {
        $purchase = $this->register(reference: '  PED-1  ', notes: '   ');

        self::assertSame('PED-1', $purchase->reference());
        self::assertNull($purchase->notes());
    }

    public function testRequiresReference(): void
    {
        $this->expectExceptionObject(InvalidPurchase::emptyReference());

        $this->register(reference: ' ');
    }

    public function testRejectsTooLongReference(): void
    {
        $this->expectException(InvalidPurchase::class);

        $this->register(reference: str_repeat('x', Purchase::REFERENCE_MAX_LENGTH + 1));
    }

    public function testRequiresAtLeastOneProduct(): void
    {
        $this->expectExceptionObject(InvalidPurchase::withoutProducts());

        $this->register(lines: []);
    }

    public function testRejectsZeroQuantity(): void
    {
        $this->expectException(InvalidPurchase::class);

        $this->register(lines: [new PurchaseLine(Product::named('Mouse'), 0, Money::fromDecimal('10'))]);
    }

    public function testDiscountCannotExceedProductsTotal(): void
    {
        $this->expectExceptionObject(InvalidPurchase::discountGreaterThanProducts());

        $this->register(
            lines: [new PurchaseLine(Product::named('Mouse'), 1, Money::fromDecimal('50.00'))],
            discount: '50.01',
            amount: '0',
        );
    }

    public function testProductNameIsNormalized(): void
    {
        self::assertSame(Product::named('Camiseta Azul')->normalizedName(), Product::named('  camiseta   AZUL ')->normalizedName());
    }

    public function testCustomerKeepsLatestContactData(): void
    {
        $customer = Customer::register('Ana Silva', 'ANA@Example.com', Cpf::fromString('52998224725'));
        self::assertSame('ana@example.com', $customer->email());

        $customer->updateContact('Ana Souza', 'ana.souza@example.com');

        self::assertSame('Ana Souza', $customer->name());
        self::assertSame('ana.souza@example.com', $customer->email());
    }

    public function testCustomerRequiresValidEmail(): void
    {
        $this->expectException(InvalidPurchase::class);

        Customer::register('Ana', 'ana-sem-arroba', Cpf::fromString('52998224725'));
    }

    /**
     * @param list<PurchaseLine>|null $lines
     */
    private function register(
        string $reference = 'PED-1',
        ?array $lines = null,
        string $discount = '0',
        string $amount = '100.00',
        ?string $notes = 'Produto enviado para o cliente por SEDEX',
    ): Purchase {
        return Purchase::register(
            reference: $reference,
            customer: Customer::register('Ana Silva', 'ana@example.com', Cpf::fromString('52998224725')),
            amount: Money::fromDecimal($amount),
            discount: Money::fromDecimal($discount),
            purchasedAt: new \DateTimeImmutable('2026-11-20 10:00:00'),
            lines: $lines ?? [new PurchaseLine(Product::named('Teclado'), 1, Money::fromDecimal('100.00'))],
            notes: $notes,
        );
    }
}
