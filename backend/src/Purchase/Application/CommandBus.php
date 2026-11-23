<?php

declare(strict_types=1);

namespace App\Purchase\Application;

interface CommandBus
{
    public function handle(object $command): mixed;
}
