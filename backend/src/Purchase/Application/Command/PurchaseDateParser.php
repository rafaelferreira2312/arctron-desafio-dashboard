<?php

declare(strict_types=1);

namespace App\Purchase\Application\Command;

use App\Purchase\Domain\Exception\InvalidPurchase;

/**
 * Aceita "2026-11-20", "2026-11-20 14:30" e ISO 8601 completo ("2026-11-20T14:30:00-03:00").
 * Sem fuso informado, usa o fuso da operação (APP_TIMEZONE).
 */
final class PurchaseDateParser
{
    private const PATTERN = '/^(\d{4})-(\d{2})-(\d{2})(?:[T ]\d{2}:\d{2}(?::\d{2})?(?:\.\d+)?(?:Z|[+-]\d{2}:?\d{2})?)?$/';

    public static function parse(string $value, \DateTimeZone $timezone): \DateTimeImmutable
    {
        $value = trim($value);

        if (!preg_match(self::PATTERN, $value, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            throw InvalidPurchase::invalidDate($value);
        }

        try {
            return new \DateTimeImmutable($value, $timezone);
        } catch (\Exception) {
            throw InvalidPurchase::invalidDate($value);
        }
    }
}
