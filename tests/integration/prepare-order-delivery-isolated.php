<?php

if (!defined('ABSPATH')) {
	throw new RuntimeException('WordPress no esta cargado.');
}

$order = wc_create_order();
if (is_wp_error($order)) {
	throw new RuntimeException($order->get_error_message());
}

delete_option('sait_test_request_counts');
SAIT_WOOCOMMERCE()->order_delivery_state()->mark_pending($order, '1', 'P', 'isolated_fixture');
update_option('sait_test_isolated_order_id', $order->get_id(), false);

echo 'Pedido aislado preparado: ' . $order->get_id() . "\n";
