<?php

declare(strict_types=1);

namespace App\Purchase\UI\Http;

use App\Purchase\Application\Query\InvalidFilter;
use App\Purchase\Domain\Exception\InvalidPurchase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Padroniza os erros da API em JSON: {"error": "...", "message": "...", "violations": [...]}
 */
#[AsEventListener]
final class ApiExceptionSubscriber
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $path = $event->getRequest()->getPathInfo();

        if (!str_starts_with($path, '/api') && !str_starts_with($path, '/webhooks')) {
            return;
        }

        $event->setResponse($this->toResponse($event->getThrowable()));
    }

    private function toResponse(\Throwable $exception): JsonResponse
    {
        if ($exception instanceof InvalidPurchase) {
            return $this->error(Response::HTTP_UNPROCESSABLE_ENTITY, 'invalid_purchase', $exception->getMessage());
        }

        if ($exception instanceof InvalidFilter) {
            return $this->error(Response::HTTP_BAD_REQUEST, 'invalid_filter', $exception->getMessage());
        }

        $validation = $exception->getPrevious();
        if ($exception instanceof HttpExceptionInterface && $validation instanceof ValidationFailedException) {
            $violations = [];
            /** @var ConstraintViolationInterface $violation */
            foreach ($validation->getViolations() as $violation) {
                $violations[] = [
                    'field' => $violation->getPropertyPath(),
                    'message' => $violation->getMessage(),
                ];
            }

            return $this->error(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'validation_failed',
                'Os dados enviados são inválidos.',
                ['violations' => $violations],
            );
        }

        if ($exception instanceof HttpExceptionInterface) {
            $status = $exception->getStatusCode();

            return $this->error($status, 'http_error', Response::$statusTexts[$status] ?? 'Erro', [], $exception->getHeaders());
        }

        $this->logger->error('Erro inesperado na API', ['exception' => $exception]);

        return $this->error(Response::HTTP_INTERNAL_SERVER_ERROR, 'internal_error', 'Erro interno. Tente novamente.');
    }

    /**
     * @param array<string, mixed>  $extra
     * @param array<string, string> $headers
     */
    private function error(int $status, string $code, string $message, array $extra = [], array $headers = []): JsonResponse
    {
        return new JsonResponse(['error' => $code, 'message' => $message] + $extra, $status, $headers);
    }
}
