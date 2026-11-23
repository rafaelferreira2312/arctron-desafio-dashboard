<?php

declare(strict_types=1);

namespace App\Tests\Unit\WooCommerce;

use App\Purchase\Domain\Model\PurchaseSource;
use App\Purchase\Infrastructure\WooCommerce\InvalidWooCommerceOrder;
use App\Purchase\Infrastructure\WooCommerce\OrderMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OrderMapperTest extends TestCase
{
    private OrderMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new OrderMapper();
    }

    public function testMapsWooCommerceOrderToCommand(): void
    {
        $command = $this->mapper->map(self::order());

        self::assertSame('WC-4821', $command->reference);
        self::assertSame('394.80', $command->amount);
        self::assertSame('30.00', $command->discount);
        self::assertSame('2026-11-20T17:30:00Z', $command->purchasedAt);
        self::assertSame('Marina Albuquerque', $command->customerName);
        self::assertSame('marina.albuquerque@example.com', $command->customerEmail);
        self::assertSame('390.533.447-05', $command->customerCpf);
        self::assertSame('Entregar no período da tarde | Envio: SEDEX', $command->notes);
        self::assertSame(PurchaseSource::WooCommerce, $command->source);

        self::assertCount(2, $command->products);
        self::assertSame('Teclado Mecânico ABNT2', $command->products[0]->name);
        self::assertSame('249.90', $command->products[0]->unitPrice);
        // Preço unitário sai do subtotal (antes do cupom): 149.90 / 2
        self::assertSame(2, $command->products[1]->quantity);
        self::assertSame('74.95', $command->products[1]->unitPrice);
    }

    public function testReadsCpfFromBillingWhenAvailable(): void
    {
        $order = self::order();
        $order['meta_data'] = [];
        $order['billing']['cpf'] = '52998224725';

        self::assertSame('52998224725', $this->mapper->map($order)->customerCpf);
    }

    public function testFailsWithoutCpf(): void
    {
        $order = self::order();
        $order['meta_data'] = [];

        $this->expectException(InvalidWooCommerceOrder::class);

        $this->mapper->map($order);
    }

    #[DataProvider('statuses')]
    public function testOnlySupportsPaidOrders(string $status, bool $supported): void
    {
        self::assertSame($supported, $this->mapper->supports(['status' => $status] + self::order()));
    }

    public static function statuses(): iterable
    {
        yield ['processing', true];
        yield ['completed', true];
        yield ['pending', false];
        yield ['on-hold', false];
        yield ['cancelled', false];
        yield ['failed', false];
        yield ['refunded', false];
    }

    /**
     * @return array<string, mixed>
     */
    public static function order(): array
    {
        return json_decode(
            (string) file_get_contents(__DIR__.'/../../Fixtures/woocommerce-order.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }
}
