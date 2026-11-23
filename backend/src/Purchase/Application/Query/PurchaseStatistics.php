<?php

declare(strict_types=1);

namespace App\Purchase\Application\Query;

interface PurchaseStatistics
{
    /**
     * @return array<string, mixed>
     */
    public function summary(Period $period): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function byCustomer(Period $period, int $limit): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function byProduct(Period $period, int $limit): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function daily(Period $period): array;
}
