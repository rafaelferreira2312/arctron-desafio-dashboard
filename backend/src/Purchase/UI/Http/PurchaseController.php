<?php

declare(strict_types=1);

namespace App\Purchase\UI\Http;

use App\Purchase\Application\CommandBus;
use App\Purchase\Application\Command\RegistrationResult;
use App\Purchase\Application\Query\PurchaseFilters;
use App\Purchase\Application\Query\PurchaseSearch;
use App\Purchase\UI\Http\Request\RegisterPurchasePayload;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/purchases')]
final class PurchaseController
{
    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly PurchaseSearch $search,
        #[Autowire('%env(APP_TIMEZONE)%')]
        private readonly string $timezone,
    ) {
    }

    #[Route('', name: 'purchases_register', methods: ['POST'])]
    public function register(#[MapRequestPayload] RegisterPurchasePayload $payload): JsonResponse
    {
        /** @var RegistrationResult $result */
        $result = $this->commandBus->handle($payload->toCommand());

        // Reenvio da mesma referência não é erro: responde 200 e não grava de novo
        if (!$result->registered) {
            return new JsonResponse([
                'status' => 'ignored',
                'message' => 'Compra com essa referência já foi processada.',
                'data' => $this->search->findByReference($result->reference),
            ], Response::HTTP_OK);
        }

        return new JsonResponse([
            'status' => 'created',
            'data' => $this->search->findByReference($result->reference),
        ], Response::HTTP_CREATED, [
            'Location' => '/api/purchases/'.rawurlencode($result->reference),
        ]);
    }

    #[Route('', name: 'purchases_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $filters = PurchaseFilters::fromQuery($request->query->all(), new \DateTimeZone($this->timezone));

        return new JsonResponse($this->search->search($filters));
    }

    #[Route('/{reference}', name: 'purchases_show', methods: ['GET'], requirements: ['reference' => '.+'])]
    public function show(string $reference): JsonResponse
    {
        $purchase = $this->search->findByReference($reference);

        if ($purchase === null) {
            return new JsonResponse(
                ['error' => 'not_found', 'message' => 'Compra não encontrada.'],
                Response::HTTP_NOT_FOUND,
            );
        }

        return new JsonResponse(['data' => $purchase]);
    }
}
