<?php

declare(strict_types=1);

namespace App\Tests\Functional;

final class StatisticsApiTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ana: 2 compras (300 e 100). Bruno: 1 compra (50).
        $this->postJson('/api/purchases', self::purchasePayload([
            'reference' => 'A-1', 'amount' => 300, 'discount' => 20, 'date' => '2026-11-10',
            'products' => [['name' => 'Teclado', 'quantity' => 2, 'unit_price' => 160]],
        ]));
        $this->postJson('/api/purchases', self::purchasePayload([
            'reference' => 'A-2', 'amount' => 100, 'discount' => 0, 'date' => '2026-11-11',
            'products' => [['name' => 'Mouse', 'quantity' => 1, 'unit_price' => 60], ['name' => 'Teclado', 'quantity' => 1, 'unit_price' => 40]],
        ]));
        $this->postJson('/api/purchases', self::purchasePayload([
            'reference' => 'B-1', 'amount' => 50, 'discount' => 10, 'date' => '2026-10-01',
            'products' => [['name' => 'Mouse', 'quantity' => 1, 'unit_price' => 60]],
            'customer' => ['name' => 'Bruno Costa', 'email' => 'bruno@example.com', 'cpf' => '11144477735'],
        ]));
    }

    public function testSummary(): void
    {
        $summary = $this->getJson('/api/statistics')['summary'];

        self::assertResponseIsSuccessful();
        self::assertSame(3, $summary['purchases_count']);
        self::assertSame(2, $summary['customers_count']);
        self::assertSame('450.00', $summary['total_amount']);
        self::assertSame('150.00', $summary['average_amount']);
        self::assertSame('30.00', $summary['total_discount']);
        self::assertSame('10.00', $summary['average_discount']);
        self::assertSame('480.00', $summary['total_products']);
        self::assertSame('160.00', $summary['average_products']);
        self::assertSame(5, $summary['items_quantity']);
    }

    public function testByCustomer(): void
    {
        $byCustomer = $this->getJson('/api/statistics')['by_customer'];

        self::assertSame('Ana Silva', $byCustomer[0]['name']);
        self::assertSame(2, $byCustomer[0]['purchases_count']);
        self::assertSame('400.00', $byCustomer[0]['total_amount']);
        self::assertSame('200.00', $byCustomer[0]['average_amount']);
        self::assertSame('50.00', $byCustomer[1]['total_amount']);
    }

    public function testByProduct(): void
    {
        $byProduct = $this->getJson('/api/statistics')['by_product'];

        self::assertSame('Teclado', $byProduct[0]['name']);
        self::assertSame(3, $byProduct[0]['quantity']);
        self::assertSame(2, $byProduct[0]['purchases_count']);
        self::assertSame('360.00', $byProduct[0]['total_amount']);
        self::assertSame('180.00', $byProduct[0]['average_amount']);

        self::assertSame('Mouse', $byProduct[1]['name']);
        self::assertSame('120.00', $byProduct[1]['total_amount']);
    }

    public function testFiltersByPeriod(): void
    {
        $body = $this->getJson('/api/statistics', ['date_from' => '2026-11-01', 'date_to' => '2026-11-30']);

        self::assertSame(2, $body['summary']['purchases_count']);
        self::assertSame('400.00', $body['summary']['total_amount']);
        self::assertCount(1, $body['by_customer']);
        self::assertSame(['2026-11-10', '2026-11-11'], array_column($body['daily'], 'day'));
    }

    public function testEmptyPeriodReturnsZeros(): void
    {
        $summary = $this->getJson('/api/statistics', ['date_from' => '2020-01-01', 'date_to' => '2020-01-31'])['summary'];

        self::assertSame(0, $summary['purchases_count']);
        self::assertSame('0.00', $summary['total_amount']);
        self::assertSame('0.00', $summary['average_amount']);
    }
}
