<?php

if (!defined('ABSPATH')) {
	throw new RuntimeException('WordPress no esta cargado.');
}

$order_id = absint(get_option('sait_test_isolated_order_id'));
$order = $order_id > 0 ? wc_get_order($order_id) : false;
if (!$order) {
	throw new RuntimeException('No se encontro el pedido aislado.');
}

$status = SAIT_WOOCOMMERCE()->order_delivery_state()->status($order);
$attempts = absint($order->get_meta('_sait_delivery_attempts'));
$request_counts = get_option('sait_test_request_counts', array());
$post_count = isset($request_counts['POST /api/v3/pedidos'])
	? absint($request_counts['POST /api/v3/pedidos'])
	: 0;

if ($status !== 'sent' || $attempts !== 1 || $post_count !== 1) {
	throw new RuntimeException(
		'Worker aislado inesperado: estado=' . $status
		. ', intentos=' . $attempts
		. ', posts=' . $post_count
	);
}

$order->delete(true);
delete_option('sait_test_isolated_order_id');
delete_option('sait_test_request_counts');

echo "Worker aislado de Action Scheduler validado correctamente.\n";
