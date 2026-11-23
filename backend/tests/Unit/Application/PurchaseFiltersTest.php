<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application;

use App\Purchase\Application\Query\InvalidFilter;
use App\Purchase\Application\Query\PurchaseFilters;
use PHPUnit\Framework\TestCase;

final class PurchaseFiltersTest extends TestCase
{
    private \DateTimeZone $timezone;

    protected function setUp(): void
    {
        $this->timezone = new \DateTimeZone('America/Sao_Paulo');
    }

    public function testParsesAllFilters(): void
    {
        $filters = PurchaseFilters::fromQuery([
            'reference' => ' PED ',
            'customer_name' => 'ana',
            'customer_email' => 'example.com',
            'customer_cpf' => '529.982',
            'date_from' => '2026-11-01',
            'date_to' => '2026-11-30',
            'amount_min' => '100,50',
            'amount_max' => '500',
            'page' => '2',
            'per_page' => '10',
        ], $this->timezone);

        self::assertSame('PED', $filters->reference);
        self::assertSame('529982', $filters->customerCpf);
        self::assertSame(10050, $filters->minAmountCents);
        self::assertSame(50000, $filters->maxAmountCents);
        self::assertSame('2026-11-01T00:00:00-03:00', $filters->period->start?->format(\DateTimeInterface::ATOM));
        // date_to é inclusivo: o limite vira o início do dia seguinte
        self::assertSame('2026-12-01T00:00:00-03:00', $filters->period->end?->format(\DateTimeInterface::ATOM));
        self::assertSame(10, $filters->offset());
    }

    public function testDefaults(): void
    {
        $filters = PurchaseFilters::fromQuery([], $this->timezone);

        self::assertNull($filters->reference);
        self::assertNull($filters->period->start);
        self::assertSame(1, $filters->page);
        self::assertSame(PurchaseFilters::DEFAULT_PER_PAGE, $filters->perPage);
    }

    public function testLimitsPageSize(): void
    {
        self::assertSame(PurchaseFilters::MAX_PER_PAGE, PurchaseFilters::fromQuery(['per_page' => '5000'], $this->timezone)->perPage);
    }

    public function testRejectsInvertedAmountRange(): void
    {
        $this->expectException(InvalidFilter::class);

        PurchaseFilters::fromQuery(['amount_min' => '500', 'amount_max' => '100'], $this->timezone);
    }

    public function testRejectsInvertedDateRange(): void
    {
        $this->expectException(InvalidFilter::class);

        PurchaseFilters::fromQuery(['date_from' => '2026-11-30', 'date_to' => '2026-11-01'], $this->timezone);
    }

    public function testRejectsInvalidDate(): void
    {
        $this->expectException(InvalidFilter::class);

        PurchaseFilters::fromQuery(['date_from' => '30/11/2026'], $this->timezone);
    }

    public function testRejectsInvalidPage(): void
    {
        $this->expectException(InvalidFilter::class);

        PurchaseFilters::fromQuery(['page' => '0'], $this->timezone);
    }
}
