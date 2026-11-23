<?php

declare(strict_types=1);

namespace App\Tests\Functional;

final class PurchaseApiTest extends ApiTestCase
{
    public function testRegistersPurchase(): void
    {
        $body = $this->postJson('/api/purchases', self::purchasePayload());

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('Location', '/api/purchases/PED-1001');
        self::assertSame('created', $body['status']);
        self::assertSame('PED-1001', $body['data']['reference']);
        self::assertSame('299.80', $body['data']['amount']);
        self::assertSame('20.00', $body['data']['discount']);
        self::assertSame('319.80', $body['data']['products_total']);
        self::assertSame('2026-11-20T14:30:00-03:00', $body['data']['purchased_at']);
        self::assertSame('529.982.247-25', $body['data']['customer']['cpf']);
        self::assertCount(2, $body['data']['products']);
        self::assertSame('api', $body['data']['source']);
    }

    public function testSecondPurchaseWithSameReferenceIsIgnored(): void
    {
        $this->postJson('/api/purchases', self::purchasePayload());
        $body = $this->postJson('/api/purchases', self::purchasePayload(['amount' => 1.00]));

        self::assertResponseStatusCodeSame(200);
        self::assertSame('ignored', $body['status']);
        self::assertSame('299.80', $body['data']['amount']);
        self::assertSame(1, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM purchases'));
    }

    public function testSameCustomerAndProductsAreReused(): void
    {
        $this->postJson('/api/purchases', self::purchasePayload());
        $this->postJson('/api/purchases', self::purchasePayload([
            'reference' => 'PED-1002',
            'customer' => ['name' => 'Ana S.', 'email' => 'ana.nova@example.com', 'cpf' => '52998224725'],
            'products' => [['name' => 'mouse', 'quantity' => 3, 'unit_price' => 89.90]],
            'amount' => 269.70,
            'discount' => 0,
        ]));

        self::assertResponseStatusCodeSame(201);
        self::assertSame(1, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM customers'));
        self::assertSame(2, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM products'));
        self::assertSame('ana.nova@example.com', $this->connection()->fetchOne('SELECT email FROM customers'));
    }

    public function testValidatesPayloadFormat(): void
    {
        $body = $this->postJson('/api/purchases', ['reference' => '', 'products' => []]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('validation_failed', $body['error']);

        $fields = array_column($body['violations'], 'field');
        self::assertContains('reference', $fields);
        self::assertContains('products', $fields);
        self::assertContains('customer', $fields);
    }

    public function testRejectsBusinessRuleViolation(): void
    {
        $body = $this->postJson('/api/purchases', self::purchasePayload(['discount' => 500]));

        self::assertResponseStatusCodeSame(422);
        self::assertSame('invalid_purchase', $body['error']);
    }

    public function testRejectsInvalidCpf(): void
    {
        $payload = self::purchasePayload();
        $payload['customer']['cpf'] = '123.456.789-00';

        $body = $this->postJson('/api/purchases', $payload);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('CPF', $body['message']);
    }

    public function testShowsPurchaseByReference(): void
    {
        $this->postJson('/api/purchases', self::purchasePayload());

        $body = $this->getJson('/api/purchases/PED-1001');
        self::assertResponseIsSuccessful();
        self::assertSame('PED-1001', $body['data']['reference']);

        $this->getJson('/api/purchases/NAO-EXISTE');
        self::assertResponseStatusCodeSame(404);
    }

    public function testSearchesWithFilters(): void
    {
        $this->postJson('/api/purchases', self::purchasePayload());
        $this->postJson('/api/purchases', self::purchasePayload([
            'reference' => 'PED-2001',
            'amount' => 50.00,
            'discount' => 0,
            'date' => '2026-10-05',
            'products' => [['name' => 'Cabo HDMI', 'quantity' => 1, 'unit_price' => 50.00]],
            'customer' => ['name' => 'Bruno Costa', 'email' => 'bruno@empresa.com.br', 'cpf' => '111.444.777-35'],
        ]));

        self::assertSame(2, $this->getJson('/api/purchases')['meta']['total']);

        $cases = [
            [['reference' => 'ped-2'], 'PED-2001'],
            [['customer_name' => 'ana'], 'PED-1001'],
            [['customer_email' => 'empresa.com'], 'PED-2001'],
            [['customer_cpf' => '529.982.247-25'], 'PED-1001'],
            [['customer_cpf' => '111'], 'PED-2001'],
            [['date_from' => '2026-11-01', 'date_to' => '2026-11-30'], 'PED-1001'],
            [['amount_min' => '100'], 'PED-1001'],
            [['amount_max' => '50.00'], 'PED-2001'],
        ];

        foreach ($cases as [$query, $expectedReference]) {
            $body = $this->getJson('/api/purchases', $query);

            self::assertResponseIsSuccessful();
            self::assertSame(1, $body['meta']['total'], 'Filtro: '.json_encode($query));
            self::assertSame($expectedReference, $body['data'][0]['reference'], 'Filtro: '.json_encode($query));
        }
    }

    public function testPaginates(): void
    {
        for ($i = 1; $i <= 3; ++$i) {
            $this->postJson('/api/purchases', self::purchasePayload(['reference' => "PED-{$i}"]));
        }

        $body = $this->getJson('/api/purchases', ['per_page' => 2, 'page' => 2]);

        self::assertCount(1, $body['data']);
        self::assertSame(['page' => 2, 'per_page' => 2, 'total' => 3, 'total_pages' => 2], $body['meta']);
    }

    public function testRejectsInvalidFilter(): void
    {
        $body = $this->getJson('/api/purchases', ['date_from' => '20/11/2026']);

        self::assertResponseStatusCodeSame(400);
        self::assertSame('invalid_filter', $body['error']);
    }
}
