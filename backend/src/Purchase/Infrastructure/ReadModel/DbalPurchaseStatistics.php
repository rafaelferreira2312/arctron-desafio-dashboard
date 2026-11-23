<?php

declare(strict_types=1);

namespace App\Purchase\Infrastructure\ReadModel;

use App\Purchase\Application\Query\Period;
use App\Purchase\Application\Query\PurchaseStatistics;
use App\Purchase\Domain\Model\Cpf;
use App\Purchase\Domain\Model\Money;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Todos os agregados são calculados no PostgreSQL. Valores em centavos no banco,
 * convertidos pra decimal só na saída.
 */
final class DbalPurchaseStatistics implements PurchaseStatistics
{
    public function __construct(
        private readonly Connection $connection,
        #[Autowire('%env(APP_TIMEZONE)%')]
        private readonly string $timezone = 'America/Sao_Paulo',
    ) {
    }

    public function summary(Period $period): array
    {
        [$where, $params] = $this->periodCondition($period);

        $row = $this->connection->fetchAssociative(
            <<<SQL
                WITH filtered AS (
                    SELECT id, customer_id, amount_cents, discount_cents
                    FROM purchases p
                    {$where}
                ),
                products_per_purchase AS (
                    SELECT i.purchase_id,
                           SUM(i.quantity * i.unit_price_cents) AS products_cents,
                           SUM(i.quantity) AS quantity
                    FROM purchase_items i
                    JOIN filtered f ON f.id = i.purchase_id
                    GROUP BY i.purchase_id
                )
                SELECT COUNT(f.id) AS purchases_count,
                       COUNT(DISTINCT f.customer_id) AS customers_count,
                       COALESCE(SUM(f.amount_cents), 0) AS total_amount,
                       COALESCE(ROUND(AVG(f.amount_cents)), 0) AS average_amount,
                       COALESCE(SUM(f.discount_cents), 0) AS total_discount,
                       COALESCE(ROUND(AVG(f.discount_cents)), 0) AS average_discount,
                       COALESCE(SUM(pp.products_cents), 0) AS total_products,
                       COALESCE(ROUND(AVG(pp.products_cents)), 0) AS average_products,
                       COALESCE(SUM(pp.quantity), 0) AS items_quantity
                FROM filtered f
                LEFT JOIN products_per_purchase pp ON pp.purchase_id = f.id
                SQL,
            $params,
        ) ?: [];

        return [
            'purchases_count' => (int) ($row['purchases_count'] ?? 0),
            'customers_count' => (int) ($row['customers_count'] ?? 0),
            'items_quantity' => (int) ($row['items_quantity'] ?? 0),
            'total_amount' => Money::format((int) ($row['total_amount'] ?? 0)),
            'average_amount' => Money::format((int) ($row['average_amount'] ?? 0)),
            'total_discount' => Money::format((int) ($row['total_discount'] ?? 0)),
            'average_discount' => Money::format((int) ($row['average_discount'] ?? 0)),
            'total_products' => Money::format((int) ($row['total_products'] ?? 0)),
            'average_products' => Money::format((int) ($row['average_products'] ?? 0)),
        ];
    }

    public function byCustomer(Period $period, int $limit): array
    {
        [$where, $params] = $this->periodCondition($period);

        $rows = $this->connection->fetchAllAssociative(
            <<<SQL
                SELECT c.name, c.email, c.cpf,
                       COUNT(p.id) AS purchases_count,
                       SUM(p.amount_cents) AS total_amount,
                       ROUND(AVG(p.amount_cents)) AS average_amount
                FROM purchases p
                JOIN customers c ON c.id = p.customer_id
                {$where}
                GROUP BY c.id, c.name, c.email, c.cpf
                ORDER BY total_amount DESC, c.name
                LIMIT :limit
                SQL,
            $params + ['limit' => $limit],
        );

        return array_map(static fn (array $row): array => [
            'name' => $row['name'],
            'email' => $row['email'],
            'cpf' => Cpf::format((string) $row['cpf']),
            'purchases_count' => (int) $row['purchases_count'],
            'total_amount' => Money::format((int) $row['total_amount']),
            'average_amount' => Money::format((int) $row['average_amount']),
        ], $rows);
    }

    /**
     * Total do produto = soma de quantidade x preço unitário nas compras.
     * Média = valor médio que o produto representa em cada compra em que aparece.
     */
    public function byProduct(Period $period, int $limit): array
    {
        [$where, $params] = $this->periodCondition($period);

        $rows = $this->connection->fetchAllAssociative(
            <<<SQL
                SELECT pr.name,
                       COUNT(DISTINCT i.purchase_id) AS purchases_count,
                       SUM(i.quantity) AS quantity,
                       SUM(i.quantity * i.unit_price_cents) AS total_amount,
                       ROUND(CAST(SUM(i.quantity * i.unit_price_cents) AS NUMERIC) / COUNT(DISTINCT i.purchase_id)) AS average_amount,
                       ROUND(AVG(i.unit_price_cents)) AS average_unit_price
                FROM purchase_items i
                JOIN purchases p ON p.id = i.purchase_id
                JOIN products pr ON pr.id = i.product_id
                {$where}
                GROUP BY pr.id, pr.name
                ORDER BY total_amount DESC, pr.name
                LIMIT :limit
                SQL,
            $params + ['limit' => $limit],
        );

        return array_map(static fn (array $row): array => [
            'name' => $row['name'],
            'purchases_count' => (int) $row['purchases_count'],
            'quantity' => (int) $row['quantity'],
            'total_amount' => Money::format((int) $row['total_amount']),
            'average_amount' => Money::format((int) $row['average_amount']),
            'average_unit_price' => Money::format((int) $row['average_unit_price']),
        ], $rows);
    }

    public function daily(Period $period): array
    {
        [$where, $params] = $this->periodCondition($period);

        $rows = $this->connection->fetchAllAssociative(
            <<<SQL
                SELECT TO_CHAR(p.purchased_at AT TIME ZONE :timezone, 'YYYY-MM-DD') AS day,
                       COUNT(*) AS purchases_count,
                       SUM(p.amount_cents) AS total_amount
                FROM purchases p
                {$where}
                GROUP BY 1
                ORDER BY 1
                SQL,
            $params + ['timezone' => $this->timezone],
        );

        return array_map(static fn (array $row): array => [
            'day' => $row['day'],
            'purchases_count' => (int) $row['purchases_count'],
            'total_amount' => Money::format((int) $row['total_amount']),
        ], $rows);
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private function periodCondition(Period $period): array
    {
        $conditions = [];
        $params = [];

        if ($period->start !== null) {
            $conditions[] = 'p.purchased_at >= :date_from';
            $params['date_from'] = $period->start->format(\DateTimeInterface::ATOM);
        }

        if ($period->end !== null) {
            $conditions[] = 'p.purchased_at < :date_to';
            $params['date_to'] = $period->end->format(\DateTimeInterface::ATOM);
        }

        return [$conditions === [] ? '' : 'WHERE '.implode(' AND ', $conditions), $params];
    }
}
