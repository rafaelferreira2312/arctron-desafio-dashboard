<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application;

use App\Purchase\Application\Command\PurchasedProduct;
use App\Purchase\Application\Command\RegisterPurchase;
use App\Purchase\Application\Command\RegisterPurchaseHandler;
use App\Purchase\Domain\Exception\InvalidPurchase;
use App\Purchase\Domain\Model\Cpf;
use App\Purchase\Domain\Model\Customer;
use App\Purchase\Domain\Model\Product;
use App\Tests\Support\InMemoryCustomerRepository;
use App\Tests\Support\InMemoryProductRepository;
use App\Tests\Support\InMemoryPurchaseRepository;
use PHPUnit\Framework\TestCase;

final class RegisterPurchaseHandlerTest extends TestCase
{
    private InMemoryPurchaseRepository $purchases;
    private InMemoryCustomerRepository $customers;
    private InMemoryProductRepository $products;
    private RegisterPurchaseHandler $handler;

    protected function setUp(): void
    {
        $this->purchases = new InMemoryPurchaseRepository();
        $this->customers = new InMemoryCustomerRepository();
        $this->products = new InMemoryProductRepository();
        $this->handler = new RegisterPurchaseHandler($this->purchases, $this->customers, $this->products, 'America/Sao_Paulo');
    }

    public function testRegistersNewPurchase(): void
    {
        $result = ($this->handler)($this->command());

        self::assertTrue($result->registered);
        self::assertSame('PED-100', $result->reference);

        $purchase = $this->purchases->purchases['PED-100'];
        self::assertSame(29980, $purchase->amount()->cents());
        self::assertSame(2000, $purchase->discount()->cents());
        self::assertSame('Ana Silva', $purchase->customer()->name());
        self::assertSame('Produto enviado para o cliente por SEDEX', $purchase->notes());
        self::assertSame('2026-11-20T14:30:00-03:00', $purchase->purchasedAt()->format(\DateTimeInterface::ATOM));
    }

    public function testIgnoresSecondPurchaseWithSameReference(): void
    {
        ($this->handler)($this->command());
        $result = ($this->handler)($this->command(amount: '999.00'));

        self::assertFalse($result->registered);
        self::assertCount(1, $this->purchases->purchases);
        self::assertSame(29980, $this->purchases->purchases['PED-100']->amount()->cents());
    }

    public function testTreatsConcurrentDuplicateAsIgnored(): void
    {
        $this->purchases->simulateRaceCondition = true;

        $result = ($this->handler)($this->command());

        self::assertFalse($result->registered);
    }

    public function testReusesExistingCustomerByCpfAndUpdatesContact(): void
    {
        $existing = Customer::register('Ana Antiga', 'antigo@example.com', Cpf::fromString('52998224725'));
        $this->customers->add($existing);

        ($this->handler)($this->command(cpf: '529.982.247-25'));

        $purchase = $this->purchases->purchases['PED-100'];
        self::assertSame($existing, $purchase->customer());
        self::assertSame('Ana Silva', $existing->name());
        self::assertSame('ana@example.com', $existing->email());
    }

    public function testReusesExistingProductByName(): void
    {
        $teclado = Product::named('Teclado Mecânico');
        $this->products->add($teclado);

        ($this->handler)($this->command());

        self::assertSame($teclado, $this->purchases->purchases['PED-100']->items()[0]->product());
    }

    public function testSameNewProductTwiceInPurchaseBecomesOneProduct(): void
    {
        ($this->handler)($this->command(products: [
            new PurchasedProduct('Mouse', 1, '50.00'),
            new PurchasedProduct('mouse', 2, '45.00'),
        ], amount: '140.00', discount: '0'));

        $items = $this->purchases->purchases['PED-100']->items();
        self::assertSame($items[0]->product(), $items[1]->product());
    }

    public function testRejectsInvalidCpf(): void
    {
        $this->expectException(InvalidPurchase::class);

        ($this->handler)($this->command(cpf: '123.456.789-00'));
    }

    public function testRejectsInvalidDate(): void
    {
        $this->expectException(InvalidPurchase::class);

        ($this->handler)($this->command(date: '2026-02-30'));
    }

    public function testDateWithoutTimezoneUsesBusinessTimezone(): void
    {
        ($this->handler)($this->command(date: '2026-11-20'));

        self::assertSame(
            '2026-11-20T00:00:00-03:00',
            $this->purchases->purchases['PED-100']->purchasedAt()->format(\DateTimeInterface::ATOM),
        );
    }

    /**
     * @param list<PurchasedProduct>|null $products
     */
    private function command(
        string $amount = '299.80',
        string $discount = '20.00',
        string $cpf = '52998224725',
        string $date = '2026-11-20T14:30:00-03:00',
        ?array $products = null,
    ): RegisterPurchase {
        return new RegisterPurchase(
            reference: 'PED-100',
            amount: $amount,
            discount: $discount,
            purchasedAt: $date,
            customerName: 'Ana Silva',
            customerEmail: 'ana@example.com',
            customerCpf: $cpf,
            products: $products ?? [
                new PurchasedProduct('Teclado Mecânico', 1, '219.90'),
                new PurchasedProduct('Mouse', 1, '99.90'),
            ],
            notes: 'Produto enviado para o cliente por SEDEX',
        );
    }
}
