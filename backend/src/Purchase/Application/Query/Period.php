<?php

declare(strict_types=1);

namespace App\Purchase\Application\Query;

/**
 * Intervalo de dias fechado nas duas pontas (from e to entram), no fuso da operação.
 * Internamente vira [início do from, início do dia seguinte ao to).
 */
final readonly class Period
{
    private function __construct(
        public ?\DateTimeImmutable $start,
        public ?\DateTimeImmutable $end,
    ) {
    }

    public static function unbounded(): self
    {
        return new self(null, null);
    }

    public static function fromDays(?string $from, ?string $to, \DateTimeZone $timezone): self
    {
        $start = self::day($from, 'date_from', $timezone);
        $end = self::day($to, 'date_to', $timezone)?->modify('+1 day');

        if ($start !== null && $end !== null && $start >= $end) {
            throw new InvalidFilter('date_from deve ser anterior ou igual a date_to.');
        }

        return new self($start, $end);
    }

    private static function day(?string $value, string $field, \DateTimeZone $timezone): ?\DateTimeImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', trim($value), $timezone);

        if ($date === false || $date->format('Y-m-d') !== trim($value)) {
            throw new InvalidFilter(sprintf('%s inválido: "%s". Use o formato AAAA-MM-DD.', $field, $value));
        }

        return $date;
    }
}
