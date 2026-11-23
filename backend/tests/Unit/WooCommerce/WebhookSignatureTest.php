<?php

declare(strict_types=1);

namespace App\Tests\Unit\WooCommerce;

use App\Purchase\Infrastructure\WooCommerce\WebhookSignature;
use PHPUnit\Framework\TestCase;

final class WebhookSignatureTest extends TestCase
{
    public function testValidatesSignatureLikeWooCommerce(): void
    {
        $payload = '{"id":1}';
        // Mesmo cálculo que o WooCommerce faz em WC_Webhook::generate_signature()
        $signature = base64_encode(hash_hmac('sha256', $payload, 'segredo', true));

        self::assertTrue((new WebhookSignature('segredo'))->isValid($payload, $signature));
    }

    public function testRejectsWrongSignature(): void
    {
        $signature = new WebhookSignature('segredo');

        self::assertFalse($signature->isValid('{"id":1}', (new WebhookSignature('outro'))->sign('{"id":1}')));
        self::assertFalse($signature->isValid('{"id":1}', null));
        self::assertFalse($signature->isValid('{"id":2}', $signature->sign('{"id":1}')));
    }

    public function testRejectsEverythingWithoutSecretConfigured(): void
    {
        $signature = new WebhookSignature('');

        self::assertFalse($signature->isValid('{}', $signature->sign('{}')));
    }
}
