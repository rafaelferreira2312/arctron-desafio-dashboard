<?php
/**
 * Cria pedidos pelo próprio WooCommerce. Cada pedido dispara o webhook, que chega na API,
 * entra na fila do RabbitMQ e é registrado pelo worker. É o fluxo real ponta a ponta.
 *
 * Uso: wp eval-file seed-orders.php
 */

$customers = [
    ['Marina', 'Albuquerque', 'marina.albuquerque@example.com', '390.533.447-05'],
    ['Carlos', 'Menezes', 'carlos.menezes@example.com', '529.982.247-25'],
    ['Fernanda', 'Rocha', 'fernanda.rocha@example.com', '111.444.777-35'],
];

$products = wc_get_products(['limit' => -1, 'status' => 'publish']);

if ($products === []) {
    WP_CLI::error('Nenhum produto encontrado.');
}

foreach ($customers as $i => [$firstName, $lastName, $email, $cpf]) {
    $order = wc_create_order();

    foreach (array_rand($products, 2) as $index) {
        $order->add_product($products[$index], random_int(1, 2));
    }

    $order->set_address([
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => $email,
        'phone' => '(21) 99999-0000',
        'address_1' => 'Rua das Laranjeiras, 120',
        'city' => 'Rio de Janeiro',
        'state' => 'RJ',
        'postcode' => '22240-003',
        'country' => 'BR',
    ], 'billing');
    $order->update_meta_data('_billing_cpf', $cpf);

    $shipping = new WC_Order_Item_Shipping();
    $shipping->set_method_title($i % 2 === 0 ? 'SEDEX' : 'PAC');
    $shipping->set_method_id('flat_rate');
    $shipping->set_total($i % 2 === 0 ? '25.00' : '15.00');
    $order->add_item($shipping);

    if ($i === 0) {
        $order->apply_coupon('black10');
    }

    $order->set_payment_method('cod');
    $order->set_customer_note($i === 2 ? 'Deixar com o porteiro' : '');
    $order->calculate_totals();
    $order->set_status('processing', 'Pedido de exemplo criado pelo setup.');
    $order->save();

    WP_CLI::log(sprintf('Pedido #%d criado (%s %s) - total R$ %s', $order->get_id(), $firstName, $lastName, $order->get_total()));
}
