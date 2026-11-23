<?php

declare(strict_types=1);

namespace App\Purchase\Infrastructure\WooCommerce;

use App\Purchase\Application\Command\PurchasedProduct;
use App\Purchase\Application\Command\RegisterPurchase;
use App\Purchase\Domain\Model\PurchaseSource;

/**
 * Converte o JSON de pedido da API REST do WooCommerce (v3) no comando de registrar compra.
 */
final class OrderMapper
{
    // Só pedidos pagos/em andamento viram compra. "pending", "cancelled", "failed" etc ficam de fora.
    public const PROCESSABLE_STATUSES = ['processing', 'completed'];

    private const CPF_META_KEYS = ['_billing_cpf', 'billing_cpf', '_cpf', 'cpf'];

    public function supports(array $order): bool
    {
        return isset($order['id'], $order['status']) && in_array($order['status'], self::PROCESSABLE_STATUSES, true);
    }

    /**
     * @param array<string, mixed> $order
     *
     * @throws InvalidWooCommerceOrder
     */
    public function map(array $order): RegisterPurchase
    {
        $billing = is_array($order['billing'] ?? null) ? $order['billing'] : [];
        $lineItems = is_array($order['line_items'] ?? null) ? $order['line_items'] : [];

        if ($lineItems === []) {
            throw new InvalidWooCommerceOrder(sprintf('Pedido %s sem itens.', $order['id'] ?? '?'));
        }

        return new RegisterPurchase(
            reference: $this->reference($order),
            amount: (string) ($order['total'] ?? '0'),
            discount: (string) ($order['discount_total'] ?? '0'),
            purchasedAt: $this->date($order),
            customerName: trim(($billing['first_name'] ?? '').' '.($billing['last_name'] ?? '')),
            customerEmail: (string) ($billing['email'] ?? ''),
            customerCpf: $this->cpf($order),
            products: array_map(fn (array $item): PurchasedProduct => $this->product($item), array_values($lineItems)),
            notes: $this->notes($order),
            source: PurchaseSource::WooCommerce,
        );
    }

    private function reference(array $order): string
    {
        return 'WC-'.($order['number'] ?? $order['id']);
    }

    private function date(array $order): string
    {
        // date_created_gmt vem sem fuso ("2026-11-20T17:30:00"), por isso o Z
        if (!empty($order['date_created_gmt'])) {
            return $order['date_created_gmt'].'Z';
        }

        if (!empty($order['date_created'])) {
            return (string) $order['date_created'];
        }

        throw new InvalidWooCommerceOrder(sprintf('Pedido %s sem data de criação.', $order['id'] ?? '?'));
    }

    /**
     * O WooCommerce não tem CPF nativo. Plugins brasileiros (ex: Brazilian Market on WooCommerce)
     * gravam em billing.cpf ou no meta_data do pedido.
     */
    private function cpf(array $order): string
    {
        if (!empty($order['billing']['cpf'])) {
            return (string) $order['billing']['cpf'];
        }

        foreach ($order['meta_data'] ?? [] as $meta) {
            if (in_array($meta['key'] ?? null, self::CPF_META_KEYS, true) && !empty($meta['value'])) {
                return (string) $meta['value'];
            }
        }

        throw new InvalidWooCommerceOrder(sprintf('Pedido %s sem CPF do cliente.', $order['id'] ?? '?'));
    }

    /**
     * O "price" do line item já vem com o cupom aplicado. Como o desconto é guardado à parte,
     * o preço unitário sai do subtotal (antes do desconto).
     */
    private function product(array $item): PurchasedProduct
    {
        $quantity = (int) ($item['quantity'] ?? 0);
        $subtotal = (float) ($item['subtotal'] ?? 0);

        $unitPrice = $quantity > 0 ? $subtotal / $quantity : (float) ($item['price'] ?? 0);

        return new PurchasedProduct(
            name: (string) ($item['name'] ?? ''),
            quantity: $quantity,
            unitPrice: number_format($unitPrice, 2, '.', ''),
        );
    }

    private function notes(array $order): ?string
    {
        $notes = [];

        if (!empty($order['customer_note'])) {
            $notes[] = trim((string) $order['customer_note']);
        }

        foreach ($order['shipping_lines'] ?? [] as $shipping) {
            if (!empty($shipping['method_title'])) {
                $notes[] = sprintf('Envio: %s', $shipping['method_title']);
            }
        }

        return $notes === [] ? null : implode(' | ', $notes);
    }
}
