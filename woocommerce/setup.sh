#!/usr/bin/env bash
# Instala o WordPress + WooCommerce, cria produtos, o webhook apontando pra API e alguns pedidos de exemplo.
# Roda uma vez pelo container "woocommerce-setup" (wp-cli).
set -euo pipefail

WP="wp --path=/var/www/html"

echo "Aguardando o banco do WordPress..."
until $WP db check >/dev/null 2>&1; do sleep 3; done

if $WP core is-installed >/dev/null 2>&1; then
    echo "WordPress já instalado, nada a fazer."
    exit 0
fi

$WP core install \
    --url="${WP_URL}" \
    --title="Loja Arctron (dev)" \
    --admin_user=admin \
    --admin_password=admin \
    --admin_email=admin@example.com \
    --skip-email

$WP language core install pt_BR --activate || true
$WP option update timezone_string "America/Sao_Paulo"
$WP rewrite structure '/%postname%/' --hard

$WP plugin install woocommerce --activate

# Loja no Brasil, em reais, com pagamento na entrega habilitado (não precisa de gateway real)
$WP option update woocommerce_default_country "BR:RJ"
$WP option update woocommerce_currency "BRL"
$WP option update woocommerce_price_decimal_sep ","
$WP option update woocommerce_price_thousand_sep "."
$WP option update woocommerce_onboarding_profile '{"skipped":true}' --format=json
$WP option update woocommerce_cod_settings '{"enabled":"yes","title":"Pagamento na entrega"}' --format=json
$WP option update woocommerce_coming_soon "no"

# Checkout clássico (shortcode) pra aparecer o campo de CPF do mu-plugin
CHECKOUT_ID=$($WP option get woocommerce_checkout_page_id)
$WP post update "$CHECKOUT_ID" --post_content='<!-- wp:shortcode -->[woocommerce_checkout]<!-- /wp:shortcode -->'
CART_ID=$($WP option get woocommerce_cart_page_id)
$WP post update "$CART_ID" --post_content='<!-- wp:shortcode -->[woocommerce_cart]<!-- /wp:shortcode -->'

echo "Criando produtos..."
create_product() {
    $WP wc product create --user=admin --name="$1" --sku="$2" --regular_price="$3" --status=publish --porcelain
}
create_product "Teclado Mecânico ABNT2" "TEC-ABNT2" "349.90"
create_product "Mouse Sem Fio" "MOU-SF" "89.90"
create_product "Monitor 27\" IPS" "MON-27" "1499.00"
create_product "Headset USB" "HEAD-USB" "219.90"
create_product "Webcam Full HD" "CAM-FHD" "259.00"

$WP wc shop_coupon create --user=admin --code=black10 --discount_type=percent --amount=10

echo "Registrando webhooks..."
for topic in order.created order.updated; do
    $WP wc webhook create --user=admin \
        --name="Arctron - ${topic}" \
        --topic="${topic}" \
        --delivery_url="${ARCTRON_WEBHOOK_URL}" \
        --secret="${WOOCOMMERCE_WEBHOOK_SECRET}" \
        --status=active
done

echo "Criando pedidos de exemplo (devem aparecer no dashboard)..."
$WP eval-file /setup/seed-orders.php

echo "Pronto! Loja em ${WP_URL} (admin/admin)"
