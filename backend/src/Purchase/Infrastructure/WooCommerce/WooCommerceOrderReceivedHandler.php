<?php

declare(strict_types=1);

namespace App\Purchase\Infrastructure\WooCommerce;

use App\Purchase\Application\CommandBus;
use App\Purchase\Application\Command\RegistrationResult;
use App\Purchase\Domain\Exception\InvalidPurchase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
final class WooCommerceOrderReceivedHandler
{
    public function __construct(
        private readonly OrderMapper $mapper,
        private readonly CommandBus $commandBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(WooCommerceOrderReceived $message): void
    {
        $order = $message->order;

        if (!$this->mapper->supports($order)) {
            $this->logger->info('Pedido do WooCommerce ignorado pelo status', [
                'order_id' => $order['id'] ?? null,
                'status' => $order['status'] ?? null,
                'topic' => $message->topic,
            ]);

            return;
        }

        try {
            /** @var RegistrationResult $result */
            $result = $this->commandBus->handle($this->mapper->map($order));
        } catch (InvalidWooCommerceOrder|InvalidPurchase $e) {
            // Dado inválido não melhora tentando de novo: vai direto pro transporte "failed"
            throw new UnrecoverableMessageHandlingException($e->getMessage(), 0, $e);
        }

        $this->logger->info('Pedido do WooCommerce processado', [
            'order_id' => $order['id'],
            'reference' => $result->reference,
            'registered' => $result->registered,
        ]);
    }
}
