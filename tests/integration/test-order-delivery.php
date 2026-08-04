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
delete_option('sait_test_request_counts');
SAIT_WOOCOMMERCE_Orders::SAIT_sendOrder($automatic_order->get_id(), '1');
$automatic_order = wc_get_order($automatic_order->get_id());
sait_delivery_assert_same('pending', $state->status($automatic_order), 'Envio automatico queda pendiente.');
sait_delivery_assert_same(0, absint($automatic_order->get_meta('_sait_delivery_attempts')), 'Encolar no cuenta como intento.');
sait_delivery_assert_same(array(), get_option('sait_test_request_counts', array()), 'Encolar no ejecuta HTTP.');

SAIT_WOOCOMMERCE_Orders::SAIT_sendOrder($automatic_order->get_id(), '1');
do_action(SAIT_WOOCOMMERCE_OrderDeliveryScheduler::ACTION, $automatic_order->get_id(), '1');
$automatic_order = wc_get_order($automatic_order->get_id());
sait_delivery_assert_same('sent', $state->status($automatic_order), 'Worker confirma sent con HTTP 201.');
sait_delivery_assert_same(1, absint($automatic_order->get_meta('_sait_delivery_attempts')), 'Un solo intento automatico.');
$request_counts = get_option('sait_test_request_counts', array());
sait_delivery_assert_same(1, $request_counts['POST /api/v3/pedidos'], 'Un solo POST automatico.');

$retry_order = wc_create_order();
$retry_args = array($retry_order->get_id(), '1');
update_option('sait_test_post_responses', array(503, 201), false);
SAIT_WOOCOMMERCE()->order_delivery_scheduler()->enqueue($retry_order->get_id(), '1');
if (function_exists('as_unschedule_all_actions')) {
	as_unschedule_all_actions(
		SAIT_WOOCOMMERCE_OrderDeliveryScheduler::ACTION,
		$retry_args,
		SAIT_WOOCOMMERCE_OrderDeliveryScheduler::GROUP
	);
}
do_action(SAIT_WOOCOMMERCE_OrderDeliveryScheduler::ACTION, $retry_order->get_id(), '1');
$retry_order = wc_get_order($retry_order->get_id());
sait_delivery_assert_same('pending', $state->status($retry_order), 'HTTP 503 programa reintento.');
sait_delivery_assert_same(1, absint($retry_order->get_meta('_sait_delivery_attempts')), 'Primer intento fallido.');
if (function_exists('as_has_scheduled_action')) {
	sait_delivery_assert_same(
		true,
		(bool) as_has_scheduled_action(
			SAIT_WOOCOMMERCE_OrderDeliveryScheduler::ACTION,
			$retry_args,
			SAIT_WOOCOMMERCE_OrderDeliveryScheduler::GROUP
		),
		'Reintento con backoff programado.'
	);
	as_unschedule_all_actions(
		SAIT_WOOCOMMERCE_OrderDeliveryScheduler::ACTION,
		$retry_args,
		SAIT_WOOCOMMERCE_OrderDeliveryScheduler::GROUP
	);
}
do_action(SAIT_WOOCOMMERCE_OrderDeliveryScheduler::ACTION, $retry_order->get_id(), '1');
$retry_order = wc_get_order($retry_order->get_id());
sait_delivery_assert_same('sent', $state->status($retry_order), 'Reintento confirmado.');
sait_delivery_assert_same(2, absint($retry_order->get_meta('_sait_delivery_attempts')), 'Segundo intento exitoso.');

$exhausted_order = wc_create_order();
update_option('sait_test_post_responses', array(503, 503, 503), false);
SAIT_WOOCOMMERCE()->order_delivery_scheduler()->enqueue($exhausted_order->get_id(), '1');
if (function_exists('as_unschedule_all_actions')) {
	as_unschedule_all_actions(
		SAIT_WOOCOMMERCE_OrderDeliveryScheduler::ACTION,
		array($exhausted_order->get_id(), '1'),
		SAIT_WOOCOMMERCE_OrderDeliveryScheduler::GROUP
	);
}
do_action(SAIT_WOOCOMMERCE_OrderDeliveryScheduler::ACTION, $exhausted_order->get_id(), '1');
if (function_exists('as_unschedule_all_actions')) {
	as_unschedule_all_actions(SAIT_WOOCOMMERCE_OrderDeliveryScheduler::ACTION, array($exhausted_order->get_id(), '1'), SAIT_WOOCOMMERCE_OrderDeliveryScheduler::GROUP);
}
do_action(SAIT_WOOCOMMERCE_OrderDeliveryScheduler::ACTION, $exhausted_order->get_id(), '1');
if (function_exists('as_unschedule_all_actions')) {
	as_unschedule_all_actions(SAIT_WOOCOMMERCE_OrderDeliveryScheduler::ACTION, array($exhausted_order->get_id(), '1'), SAIT_WOOCOMMERCE_OrderDeliveryScheduler::GROUP);
}
do_action(SAIT_WOOCOMMERCE_OrderDeliveryScheduler::ACTION, $exhausted_order->get_id(), '1');
$exhausted_order = wc_get_order($exhausted_order->get_id());
sait_delivery_assert_same('failed', $state->status($exhausted_order), 'Tres fallos agotan reintentos.');
sait_delivery_assert_same(3, absint($exhausted_order->get_meta('_sait_delivery_attempts')), 'Limite de tres intentos.');
delete_option('sait_test_post_responses');

$duplicate_order = wc_create_order();
SAIT_WOOCOMMERCE()->send_order_payment($duplicate_order->get_id());
SAIT_WOOCOMMERCE()->send_order_thankyou($duplicate_order->get_id());
$duplicate_order = wc_get_order($duplicate_order->get_id());
sait_delivery_assert_same('pending', $state->status($duplicate_order), 'Hooks dejan una entrega pendiente.');
sait_delivery_assert_same('1', $duplicate_order->get_meta('_sait_delivery_payment_method'), 'Primer hook conserva forma de pago.');
if (function_exists('as_has_scheduled_action')) {
	sait_delivery_assert_same(
		true,
		(bool) as_has_scheduled_action(
			SAIT_WOOCOMMERCE_OrderDeliveryScheduler::ACTION,
			array($duplicate_order->get_id(), '1'),
			SAIT_WOOCOMMERCE_OrderDeliveryScheduler::GROUP
		),
		'Accion unica programada.'
	);
}

if (function_exists('as_unschedule_all_actions')) {
	as_unschedule_all_actions(
		SAIT_WOOCOMMERCE_OrderDeliveryScheduler::ACTION,
		array($automatic_order->get_id(), '1'),
		SAIT_WOOCOMMERCE_OrderDeliveryScheduler::GROUP
	);
	as_unschedule_all_actions(
		SAIT_WOOCOMMERCE_OrderDeliveryScheduler::ACTION,
		array($duplicate_order->get_id(), '1'),
		SAIT_WOOCOMMERCE_OrderDeliveryScheduler::GROUP
	);
	as_unschedule_all_actions(
		SAIT_WOOCOMMERCE_OrderDeliveryScheduler::ACTION,
		$retry_args,
		SAIT_WOOCOMMERCE_OrderDeliveryScheduler::GROUP
	);
	as_unschedule_all_actions(
		SAIT_WOOCOMMERCE_OrderDeliveryScheduler::ACTION,
		array($exhausted_order->get_id(), '1'),
		SAIT_WOOCOMMERCE_OrderDeliveryScheduler::GROUP
	);
}

$order->delete(true);
$automatic_order->delete(true);
$duplicate_order->delete(true);
$retry_order->delete(true);
$exhausted_order->delete(true);

echo "Estados de entrega de documentos SAIT validados correctamente.\n";
