<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Testes contra o PostgreSQL de teste (banco com sufixo _test).
 * Antes de rodar: bin/console doctrine:database:create --env=test && bin/console doctrine:migrations:migrate -n --env=test
 */
abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->connection()->executeStatement(
            'TRUNCATE purchase_items, purchases, products, customers RESTART IDENTITY CASCADE',
        );
    }

    protected function connection(): Connection
    {
        return static::getContainer()->get(Connection::class);
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    protected function postJson(string $uri, array $body): array
    {
        $this->client->request('POST', $uri, server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($body, JSON_THROW_ON_ERROR));

        return $this->json();
    }

    /**
     * @param array<string, scalar> $query
     *
     * @return array<string, mixed>
     */
    protected function getJson(string $uri, array $query = []): array
    {
        $this->client->request('GET', $uri.($query === [] ? '' : '?'.http_build_query($query)));

        return $this->json();
    }

    /**
     * @return array<string, mixed>
     */
    protected function json(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    protected static function purchasePayload(array $overrides = []): array
    {
        return array_replace([
            'reference' => 'PED-1001',
            'amount' => 299.80,
            'discount' => 20.00,
            'date' => '2026-11-20T14:30:00-03:00',
            'products' => [
                ['name' => 'Teclado Mecânico', 'quantity' => 1, 'unit_price' => 219.90],
                ['name' => 'Mouse', 'quantity' => 1, 'unit_price' => '99.90'],
            ],
            'customer' => ['name' => 'Ana Silva', 'email' => 'ana@example.com', 'cpf' => '529.982.247-25'],
            'notes' => 'Produto enviado para o cliente por SEDEX',
        ], $overrides);
    }
}
