<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Purchase\Infrastructure\WooCommerce\WebhookSignature;
use App\Purchase\Infrastructure\WooCommerce\WooCommerceOrderReceived;
use App\Purchase\Infrastructure\WooCommerce\WooCommerceOrderReceivedHandler;
use App\Tests\Unit\WooCommerce\OrderMapperTest;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class WooCommerceWebhookTest extends ApiTestCase
{
    public function testAnswersPingWhenWebhookIsCreated(): void
    {
        $this->client->request('POST', '/webhooks/woocommerce', content: 'webhook_id=7');

        self::assertResponseIsSuccessful();
    }

    public function testRejectsInvalidSignature(): void
    {
        $this->sendWebhook(json_encode(OrderMapperTest::order()), signature: 'assinatura-falsa');

        self::assertResponseStatusCodeSame(401);
        self::assertCount(0, $this->transport()->getSent());
    }

    public function testQueuesOrderAndWorkerRegistersPurchase(): void
    {
        $this->sendWebhook(json_encode(OrderMapperTest::order()));

        self::assertResponseStatusCodeSame(202);
        $sent = $this->transport()->getSent();
        self::assertCount(1, $sent);

        // Faz o papel do worker (messenger:consume)
        $this->handler()($sent[0]->getMessage());

        $purchase = $this->getJson('/api/purchases/WC-4821')['data'];
        self::assertSame('394.80', $purchase['amount']);
        self::assertSame('30.00', $purchase['discount']);
        self::assertSame('woocommerce', $purchase['source']);
        self::assertSame('Marina Albuquerque', $purchase['customer']['name']);
    }

    public function testSameOrderDeliveredTwiceCreatesOnePurchase(): void
    {
        $message = new WooCommerceOrderReceived('order.updated', OrderMapperTest::order());

        $this->handler()($message);
        $this->handler()($message);

        self::assertSame(1, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM purchases'));
    }

    public function testPendingOrderIsNotRegistered(): void
    {
        $this->handler()(new WooCommerceOrderReceived('order.created', ['status' => 'pending'] + OrderMapperTest::order()));

        self::assertSame(0, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM purchases'));
    }

    public function testInvalidOrderGoesStraightToFailedQueue(): void
    {
        $order = OrderMapperTest::order();
        $order['meta_data'] = [];

        $this->expectException(UnrecoverableMessageHandlingException::class);

        $this->handler()(new WooCommerceOrderReceived('order.created', $order));
    }

    private function sendWebhook(string $payload, ?string $signature = null, string $topic = 'order.created'): void
    {
        $signature ??= static::getContainer()->get(WebhookSignature::class)->sign($payload);

        $this->client->request('POST', '/webhooks/woocommerce', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WC_WEBHOOK_TOPIC' => $topic,
            'HTTP_X_WC_WEBHOOK_SIGNATURE' => $signature,
        ], content: $payload);
    }

    private function transport(): InMemoryTransport
    {
        return static::getContainer()->get('messenger.transport.async');
    }

    private function handler(): WooCommerceOrderReceivedHandler
    {
        return static::getContainer()->get(WooCommerceOrderReceivedHandler::class);
    }
}
