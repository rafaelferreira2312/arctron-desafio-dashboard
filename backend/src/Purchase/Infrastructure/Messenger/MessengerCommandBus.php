<?php

declare(strict_types=1);

namespace App\Purchase\Infrastructure\Messenger;

use App\Purchase\Application\CommandBus;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final class MessengerCommandBus implements CommandBus
{
    public function __construct(private readonly MessageBusInterface $messageBus)
    {
    }

    public function handle(object $command): mixed
    {
        try {
            $envelope = $this->messageBus->dispatch($command);
        } catch (HandlerFailedException $e) {
            // Devolve a exceção original pra quem chamou (ex: InvalidPurchase vira 422 na API)
            throw $e->getPrevious() ?? $e;
        }

        $handled = $envelope->all(HandledStamp::class);

        if (count($handled) !== 1) {
            throw new \LogicException(sprintf(
                'O comando %s deveria ter exatamente um handler, mas teve %d.',
                $command::class,
                count($handled),
            ));
        }

        return $handled[0]->getResult();
    }
}
