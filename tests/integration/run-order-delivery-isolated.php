<?php

if (!defined('ABSPATH')) {
	throw new RuntimeException('WordPress no esta cargado.');
}

$order_id = absint(get_option('sait_test_isolated_order_id'));
if ($order_id <= 0) {
	throw new RuntimeException('No existe pedido aislado preparado.');
}
if (!class_exists('SAIT_WOOCOMMERCE_Orders', false)) {
	throw new RuntimeException('SAIT_WOOCOMMERCE_Orders no se cargo en el proceso aislado.');
}

do_action(SAIT_WOOCOMMERCE_OrderDeliveryScheduler::ACTION, $order_id, '1');

echo 'Worker aislado ejecutado para pedido: ' . $order_id . "\n";
