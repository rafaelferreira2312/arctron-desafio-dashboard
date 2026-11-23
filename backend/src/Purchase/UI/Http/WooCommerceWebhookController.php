<?php

declare(strict_types=1);

namespace App\Purchase\UI\Http;

use App\Purchase\Infrastructure\WooCommerce\WebhookSignature;
use App\Purchase\Infrastructure\WooCommerce\WooCommerceOrderReceived;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

final class WooCommerceWebhookController
{
    private const ORDER_TOPICS = ['order.created', 'order.updated'];

    public function __construct(
        private readonly WebhookSignature $signature,
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/webhooks/woocommerce', name: 'webhook_woocommerce', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $topic = (string) $request->headers->get('X-WC-Webhook-Topic', '');

        // Ao salvar o webhook o WooCommerce manda um "ping" (webhook_id=N) sem tópico. Só confirmar.
        if ($topic === '' && str_starts_with($payload, 'webhook_id=')) {
            return new JsonResponse(['status' => 'pong']);
        }

        if (!$this->signature->isValid($payload, $request->headers->get('X-WC-Webhook-Signature'))) {
            $this->logger->warning('Webhook do WooCommerce com assinatura inválida', ['topic' => $topic]);

            return new JsonResponse(['error' => 'invalid_signature'], Response::HTTP_UNAUTHORIZED);
        }

        if (!in_array($topic, self::ORDER_TOPICS, true)) {
            return new JsonResponse(['status' => 'ignored', 'reason' => 'topic']);
        }

        try {
            $order = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new JsonResponse(['error' => 'invalid_json'], Response::HTTP_BAD_REQUEST);
        }

        if (!is_array($order)) {
            return new JsonResponse(['error' => 'invalid_payload'], Response::HTTP_BAD_REQUEST);
        }

        $this->bus->dispatch(new WooCommerceOrderReceived(
            topic: $topic,
            order: $order,
            deliveryId: $request->headers->get('X-WC-Webhook-Delivery-ID'),
        ));

        return new JsonResponse(['status' => 'queued'], Response::HTTP_ACCEPTED);
    }
}
