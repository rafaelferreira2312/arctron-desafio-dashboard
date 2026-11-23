<?php

declare(strict_types=1);

namespace App\Purchase\Domain\Model;

enum PurchaseSource: string
{
    case Api = 'api';
    case WooCommerce = 'woocommerce';
    case Seed = 'seed';
}
