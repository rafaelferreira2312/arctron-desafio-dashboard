<?php

declare(strict_types=1);

namespace App\Purchase\UI\Http;

use App\Purchase\Application\Query\InvalidFilter;
use App\Purchase\Application\Query\Period;
use App\Purchase\Application\Query\PurchaseStatistics;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class StatisticsController
{
    private const DEFAULT_RANKING_LIMIT = 10;
    private const MAX_RANKING_LIMIT = 50;

    public function __construct(
        private readonly PurchaseStatistics $statistics,
        #[Autowire('%env(APP_TIMEZONE)%')]
        private readonly string $timezone,
    ) {
    }

    #[Route('/api/statistics', name: 'statistics', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        $period = Period::fromDays(
            $request->query->getString('date_from') ?: null,
            $request->query->getString('date_to') ?: null,
            new \DateTimeZone($this->timezone),
        );

        $limit = $request->query->getString('limit', (string) self::DEFAULT_RANKING_LIMIT);
        if (!ctype_digit($limit) || (int) $limit < 1) {
            throw new InvalidFilter('limit deve ser um inteiro maior que zero.');
        }
        $limit = min((int) $limit, self::MAX_RANKING_LIMIT);

        return new JsonResponse([
            'period' => [
                'date_from' => $period->start?->format('Y-m-d'),
                'date_to' => $period->end?->modify('-1 day')->format('Y-m-d'),
            ],
            'summary' => $this->statistics->summary($period),
            'by_customer' => $this->statistics->byCustomer($period, $limit),
            'by_product' => $this->statistics->byProduct($period, $limit),
            'daily' => $this->statistics->daily($period),
        ]);
    }
}
