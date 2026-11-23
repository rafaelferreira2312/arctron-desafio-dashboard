<?php

declare(strict_types=1);

namespace App\Purchase\Infrastructure\Messenger;

use App\Purchase\Application\CommandBus;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

final class MessengerCommandBus implements CommandBus
{
    use HandleTrait;

    public function __construct(MessageBusInterface $messageBus)
    {
        $this->messageBus = $messageBus;
    }

    public function handle(object $command): mixed
    {
        try {
            return $this->handleMessage($command);
        } catch (HandlerFailedException $e) {
            // Devolve a exceção original pra quem chamou (ex: InvalidPurchase vira 422 na API)
            throw $e->getPrevious() ?? $e;
        }
    }
}
