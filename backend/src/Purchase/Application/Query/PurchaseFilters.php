<?php

declare(strict_types=1);

namespace App\Purchase\Application\Query;

use App\Purchase\Domain\Model\Cpf;
use App\Purchase\Domain\Model\Money;

final readonly class PurchaseFilters
{
    public const DEFAULT_PER_PAGE = 20;
    public const MAX_PER_PAGE = 100;

    private function __construct(
        public ?string $reference,
        public ?string $customerName,
        public ?string $customerEmail,
        public ?string $customerCpf,
        public Period $period,
        public ?int $minAmountCents,
        public ?int $maxAmountCents,
        public int $page,
        public int $perPage,
    ) {
    }

    /**
     * @param array<string, mixed> $query
     */
    public static function fromQuery(array $query, \DateTimeZone $timezone): self
    {
        $min = self::amount($query, 'amount_min');
        $max = self::amount($query, 'amount_max');

        if ($min !== null && $max !== null && $min > $max) {
            throw new InvalidFilter('amount_min não pode ser maior que amount_max.');
        }

        $cpf = self::text($query, 'customer_cpf');
        if ($cpf !== null) {
            $cpf = Cpf::onlyDigits($cpf);
            if ($cpf === '') {
                throw new InvalidFilter('customer_cpf deve conter números.');
            }
        }

        return new self(
            reference: self::text($query, 'reference'),
            customerName: self::text($query, 'customer_name'),
            customerEmail: self::text($query, 'customer_email'),
            customerCpf: $cpf,
            period: Period::fromDays(self::text($query, 'date_from'), self::text($query, 'date_to'), $timezone),
            minAmountCents: $min,
            maxAmountCents: $max,
            page: self::positiveInt($query, 'page', 1),
            perPage: min(self::positiveInt($query, 'per_page', self::DEFAULT_PER_PAGE), self::MAX_PER_PAGE),
        );
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    /**
     * @param array<string, mixed> $query
     */
    private static function text(array $query, string $key): ?string
    {
        $value = $query[$key] ?? null;

        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @param array<string, mixed> $query
     */
    private static function amount(array $query, string $key): ?int
    {
        $value = self::text($query, $key);

        if ($value === null) {
            return null;
        }

        try {
            return Money::fromDecimal(str_replace(',', '.', $value))->cents();
        } catch (\DomainException) {
            throw new InvalidFilter(sprintf('%s inválido: "%s".', $key, $value));
        }
    }

    /**
     * @param array<string, mixed> $query
     */
    private static function positiveInt(array $query, string $key, int $default): int
    {
        $value = self::text($query, $key);

        if ($value === null) {
            return $default;
        }

        if (!ctype_digit($value) || (int) $value < 1) {
            throw new InvalidFilter(sprintf('%s deve ser um inteiro maior que zero.', $key));
        }

        return (int) $value;
    }
}
