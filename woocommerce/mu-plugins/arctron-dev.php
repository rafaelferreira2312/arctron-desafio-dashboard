<?php
/**
 * Plugin Name: Arctron - ajustes do ambiente de desenvolvimento
 * Description: Campo de CPF no checkout e liberação do webhook para a API local (rede do Docker).
 */

if (!defined('ABSPATH')) {
    exit;
}

/*
 * CPF no checkout clássico. O WooCommerce grava campos de billing como meta "_billing_<campo>",
 * então o valor vai para "_billing_cpf" no pedido.
 */
add_filter('woocommerce_billing_fields', function (array $fields): array {
    $fields['billing_cpf'] = [
        'label' => 'CPF',
        'required' => true,
        'class' => ['form-row-wide'],
        'priority' => 25,
    ];

    return $fields;
});

add_action('woocommerce_after_checkout_validation', function (array $data, WP_Error $errors): void {
    $cpf = preg_replace('/\D/', '', $data['billing_cpf'] ?? '');

    if (strlen($cpf) !== 11) {
        $errors->add('billing_cpf', 'Informe um CPF válido.');
    }
}, 10, 2);

// Também expõe o CPF dentro de "billing" no JSON da API REST (que é o mesmo payload do webhook)
add_filter('woocommerce_rest_prepare_shop_order_object', function (WP_REST_Response $response, WC_Order $order): WP_REST_Response {
    $data = $response->get_data();
    $data['billing']['cpf'] = (string) $order->get_meta('_billing_cpf');
    $response->set_data($data);

    return $response;
}, 10, 2);

/*
 * O WooCommerce entrega webhooks com wp_safe_remote_post, que bloqueia hosts internos.
 * Aqui a API roda na rede do Docker com o hostname "api", então ele precisa ser liberado.
 */
add_filter('http_request_host_is_external', function (bool $external, string $host): bool {
    return $external || $host === getenv('ARCTRON_API_HOST');
}, 10, 2);

// Em desenvolvimento entrega na hora, sem depender do WP-Cron / Action Scheduler
add_filter('woocommerce_webhook_deliver_async', '__return_false');
