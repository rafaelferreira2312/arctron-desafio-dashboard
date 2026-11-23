<?php

declare(strict_types=1);

namespace App\Purchase\Infrastructure\WooCommerce;

/**
 * Pedido cru recebido pelo webhook. Vai pra fila do RabbitMQ e é processado pelo worker,
 * assim o webhook responde rápido e uma falha aqui não perde o pedido.
 */
final readonly class WooCommerceOrderReceived
{
    /**
     * @param array<string, mixed> $order
     */
    public function __construct(
        public string $topic,
        public array $order,
        public ?string $deliveryId = null,
    ) {
    }
}
