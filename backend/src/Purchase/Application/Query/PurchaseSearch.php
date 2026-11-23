<?php

declare(strict_types=1);

namespace App\Purchase\Application\Query;

interface PurchaseSearch
{
    /**
     * @return array{data: list<array<string, mixed>>, meta: array{page: int, per_page: int, total: int, total_pages: int}}
     */
    public function search(PurchaseFilters $filters): array;

    /**
     * @return array<string, mixed>|null
     */
    public function findByReference(string $reference): ?array;
}
