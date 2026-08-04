<?php

if (!defined('ABSPATH')) {
	throw new RuntimeException('WordPress no esta cargado.');
}

require_once WP_PLUGIN_DIR . '/sait-woocommerce/includes/SAIT_WOOCOMMERCE-orders.php';

function sait_delivery_assert_same($expected, $actual, $message)
{
	if ($expected !== $actual) {
		throw new RuntimeException(
			$message . ' Esperado: ' . var_export($expected, true)
			. '; recibido: ' . var_export($actual, true)
		);
	}
}

$options = get_option('opciones_sait', array());
$options['SAITNube_URL'] = 'https://sait-api.invalid';
$options['SAITNube_APIKey'] = 'fixture-api-key';
$options['SAITNube_TipoDoc'] = 'P';
$options['SAITNube_NumAlm'] = '1';
$options['SAITNube_PedidoObs_enabled'] = '0';
$options['SAITNube_PedidoDirenvio_enabled'] = '0';
$options['SAITNube_FuncionPersonalizadaPedido_enabled'] = '0';
update_option('opciones_sait', $options);

$state = SAIT_WOOCOMMERCE()->order_delivery_state();
$order = wc_create_order();
$state->mark_pending($order, '1', 'P', 'fixture');
$order = wc_get_order($order->get_id());
sait_delivery_assert_same('pending', $state->status($order), 'Estado pendiente.');
sait_delivery_assert_same(0, absint($order->get_meta('_sait_delivery_attempts')), 'Pendiente no incrementa intentos.');

$state->mark_sending($order, '1', 'P', 'fixture');
$order = wc_get_order($order->get_id());
sait_delivery_assert_same('sending', $state->status($order), 'Estado enviando.');
sait_delivery_assert_same(1, absint($order->get_meta('_sait_delivery_attempts')), 'Primer intento.');

$state->record_response($order, new WP_Error('fixture_error', 'Fallo simulado.'));
$order = wc_get_order($order->get_id());
sait_delivery_assert_same('failed', $state->status($order), 'Estado fallido.');
sait_delivery_assert_same(0, (int) $order->get_meta('_sait_delivery_http_status'), 'Status de transporte.');
sait_delivery_assert_same('Fallo simulado.', $order->get_meta('_sait_delivery_last_error'), 'Ultimo error.');

$state->mark_sending($order, '1', 'P', 'fixture');
$success_response = array(
	'response' => array('code' => 201, 'message' => 'Created'),
	'body' => '{"result":"OK"}',
	'headers' => array(),
	'cookies' => array(),
	'filename' => null,
);
$state->record_response($order, $success_response);
$order = wc_get_order($order->get_id());
sait_delivery_assert_same('sent', $state->status($order), 'Estado enviado confirmado.');
sait_delivery_assert_same(2, absint($order->get_meta('_sait_delivery_attempts')), 'Intentos acumulados.');
sait_delivery_assert_same(201, (int) $order->get_meta('_sait_delivery_http_status'), 'HTTP confirmado.');
sait_delivery_assert_same('', $order->get_meta('_sait_delivery_last_error'), 'Error eliminado al enviar.');

$automatic_order = wc_create_order();
SAIT_WOOCOMMERCE_Orders::SAIT_sendOrder($automatic_order->get_id(), '1');
$automatic_order = wc_get_order($automatic_order->get_id());
sait_delivery_assert_same('sending', $state->status($automatic_order), 'POST no bloqueante no confirma sent.');
sait_delivery_assert_same(1, absint($automatic_order->get_meta('_sait_delivery_attempts')), 'Intento automatico.');

$order->delete(true);
$automatic_order->delete(true);

echo "Estados de entrega de documentos SAIT validados correctamente.\n";
