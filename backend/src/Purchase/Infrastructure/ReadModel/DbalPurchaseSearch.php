<?php

declare(strict_types=1);

namespace App\Purchase\Infrastructure\ReadModel;

use App\Purchase\Application\Query\PurchaseFilters;
use App\Purchase\Application\Query\PurchaseSearch;
use App\Purchase\Domain\Model\Cpf;
use App\Purchase\Domain\Model\Money;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Leitura direto no SQL: a listagem não precisa hidratar entidades,
 * e assim dá pra controlar exatamente as queries que vão pro banco.
 */
final class DbalPurchaseSearch implements PurchaseSearch
{
    private \DateTimeZone $timezone;

    public function __construct(
        private readonly Connection $connection,
        #[Autowire('%env(APP_TIMEZONE)%')]
        string $timezone = 'America/Sao_Paulo',
    ) {
        $this->timezone = new \DateTimeZone($timezone);
    }

    public function search(PurchaseFilters $filters): array
    {
        [$where, $params] = $this->conditions($filters);

        $total = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM purchases p JOIN customers c ON c.id = p.customer_id {$where}",
            $params,
        );

        $rows = $this->connection->fetchAllAssociative(
            <<<SQL
                SELECT p.id, p.reference, p.amount_cents, p.discount_cents, p.purchased_at, p.notes, p.source,
                       c.name AS customer_name, c.email AS customer_email, c.cpf AS customer_cpf
                FROM purchases p
                JOIN customers c ON c.id = p.customer_id
                {$where}
                ORDER BY p.purchased_at DESC, p.id DESC
                LIMIT :limit OFFSET :offset
                SQL,
            $params + ['limit' => $filters->perPage, 'offset' => $filters->offset()],
        );

        return [
            'data' => $this->present($rows),
            'meta' => [
                'page' => $filters->page,
                'per_page' => $filters->perPage,
                'total' => $total,
                'total_pages' => (int) ceil($total / $filters->perPage),
            ],
        ];
    }

    public function findByReference(string $reference): ?array
    {
        $row = $this->connection->fetchAssociative(
            <<<'SQL'
                SELECT p.id, p.reference, p.amount_cents, p.discount_cents, p.purchased_at, p.notes, p.source,
                       c.name AS customer_name, c.email AS customer_email, c.cpf AS customer_cpf
                FROM purchases p
                JOIN customers c ON c.id = p.customer_id
                WHERE p.reference = :reference
                SQL,
            ['reference' => $reference],
        );

        return $row === false ? null : $this->present([$row])[0];
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function conditions(PurchaseFilters $filters): array
    {
        $conditions = [];
        $params = [];

        if ($filters->reference !== null) {
            $conditions[] = 'p.reference ILIKE :reference';
            $params['reference'] = '%'.$this->escapeLike($filters->reference).'%';
        }

        if ($filters->customerName !== null) {
            $conditions[] = 'c.name ILIKE :customer_name';
            $params['customer_name'] = '%'.$this->escapeLike($filters->customerName).'%';
        }

        if ($filters->customerEmail !== null) {
            $conditions[] = 'c.email ILIKE :customer_email';
            $params['customer_email'] = '%'.$this->escapeLike($filters->customerEmail).'%';
        }

        if ($filters->customerCpf !== null) {
            // CPF completo busca exato, pedaço do CPF busca por prefixo
            $conditions[] = strlen($filters->customerCpf) === 11 ? 'c.cpf = :customer_cpf' : 'c.cpf LIKE :customer_cpf';
            $params['customer_cpf'] = strlen($filters->customerCpf) === 11 ? $filters->customerCpf : $filters->customerCpf.'%';
        }

        if ($filters->period->start !== null) {
            $conditions[] = 'p.purchased_at >= :date_from';
            $params['date_from'] = $filters->period->start->format(\DateTimeInterface::ATOM);
        }

        if ($filters->period->end !== null) {
            $conditions[] = 'p.purchased_at < :date_to';
            $params['date_to'] = $filters->period->end->format(\DateTimeInterface::ATOM);
        }

        if ($filters->minAmountCents !== null) {
            $conditions[] = 'p.amount_cents >= :amount_min';
            $params['amount_min'] = $filters->minAmountCents;
        }

        if ($filters->maxAmountCents !== null) {
            $conditions[] = 'p.amount_cents <= :amount_max';
            $params['amount_max'] = $filters->maxAmountCents;
        }

        return [$conditions === [] ? '' : 'WHERE '.implode(' AND ', $conditions), $params];
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    private function present(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $itemsByPurchase = $this->itemsFor(array_map(static fn (array $row): int => (int) $row['id'], $rows));

        return array_map(function (array $row) use ($itemsByPurchase): array {
            $items = $itemsByPurchase[(int) $row['id']] ?? [];
            $productsTotal = array_sum(array_map(
                static fn (array $item): int => (int) $item['quantity'] * (int) $item['unit_price_cents'],
                $items,
            ));

            return [
                'reference' => $row['reference'],
                'amount' => Money::format((int) $row['amount_cents']),
                'discount' => Money::format((int) $row['discount_cents']),
                'products_total' => Money::format($productsTotal),
                'purchased_at' => (new \DateTimeImmutable((string) $row['purchased_at']))
                    ->setTimezone($this->timezone)
                    ->format(\DateTimeInterface::ATOM),
                'notes' => $row['notes'],
                'source' => $row['source'],
                'customer' => [
                    'name' => $row['customer_name'],
                    'email' => $row['customer_email'],
                    'cpf' => Cpf::format((string) $row['customer_cpf']),
                ],
                'products' => array_map(static fn (array $item): array => [
                    'name' => $item['product_name'],
                    'quantity' => (int) $item['quantity'],
                    'unit_price' => Money::format((int) $item['unit_price_cents']),
                    'total' => Money::format((int) $item['quantity'] * (int) $item['unit_price_cents']),
                ], $items),
            ];
        }, $rows);
    }

    /**
     * Uma query só pros itens da página inteira, sem N+1.
     *
     * @param list<int> $purchaseIds
     *
     * @return array<int, list<array<string, mixed>>>
     */
    private function itemsFor(array $purchaseIds): array
    {
        $items = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT purchase_id, product_name, quantity, unit_price_cents
                FROM purchase_items
                WHERE purchase_id = ANY(CAST(:ids AS INT[]))
                ORDER BY id
                SQL,
            ['ids' => '{'.implode(',', $purchaseIds).'}'],
        );

        $grouped = [];
        foreach ($items as $item) {
            $grouped[(int) $item['purchase_id']][] = $item;
        }

        return $grouped;
    }
}
