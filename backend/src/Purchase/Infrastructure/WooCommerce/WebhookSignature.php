<?php

declare(strict_types=1);

namespace App\Purchase\Infrastructure\WooCommerce;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * O WooCommerce assina o corpo da requisição com HMAC-SHA256 usando o secret do webhook
 * e manda o resultado em base64 no header X-WC-Webhook-Signature.
 */
final class WebhookSignature
{
    public function __construct(
        #[Autowire('%env(WOOCOMMERCE_WEBHOOK_SECRET)%')]
        private readonly string $secret,
    ) {
    }

    public function isValid(string $payload, ?string $signature): bool
    {
        if ($this->secret === '' || $signature === null || $signature === '') {
            return false;
        }

        return hash_equals($this->sign($payload), $signature);
    }

    public function sign(string $payload): string
    {
        return base64_encode(hash_hmac('sha256', $payload, $this->secret, true));
    }
}
