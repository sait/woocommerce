<?php
/**
 * Caracterizacion del contrato REST publico de SAIT WooCommerce 1.2.3.
 */

if (!defined('ABSPATH')) {
	throw new RuntimeException('WordPress no esta cargado.');
}

function sait_rest_assert_same($expected, $actual, $message)
{
	if ($expected !== $actual) {
		throw new RuntimeException(
			$message . ' Esperado: ' . var_export($expected, true)
			. '; recibido: ' . var_export($actual, true)
		);
	}
}

function sait_rest_assert_true($condition, $message)
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function sait_rest_request($method, $route, $body = null, $token = null)
{
	$request = new WP_REST_Request($method, $route);
	if ($body !== null) {
		$request->set_body($body);
	}
	if ($token !== null) {
		$request->set_header('x-AccessToken', $token);
	}

	return rest_do_request($request);
}

$options = get_option('opciones_sait', array());
$options['SAITNube_AccessToken'] = 'fixture-access-token';
$options['SAITNube_TipoDoc'] = 'P';
update_option('opciones_sait', $options);
wp_set_current_user(0);

$server = rest_get_server();
$routes = $server->get_routes();
$public_patterns = array(
	'/saitplugin/v1/hello' => 'GET',
	'/saitplugin/v1/saitevents' => 'POST',
);

foreach ($public_patterns as $pattern => $method) {
	sait_rest_assert_true(isset($routes[$pattern]), 'Falta la ruta REST ' . $pattern);
	$endpoint = $routes[$pattern][0];
	sait_rest_assert_true(!empty($endpoint['methods'][$method]), 'Metodo incorrecto para ' . $pattern);
	sait_rest_assert_same('__return_true', $endpoint['permission_callback'], 'Permiso publico actual de ' . $pattern);
}

$protected_patterns = array(
	'/saitplugin/v1/reenviar-pedido-sait/(?P<idpedido>\d+)' => 'POST',
	'/saitplugin/v1/testpedido/(?P<idpedido>\d+)' => 'GET',
);
foreach ($protected_patterns as $pattern => $method) {
	sait_rest_assert_true(isset($routes[$pattern]), 'Falta la ruta REST ' . $pattern);
	$endpoint = $routes[$pattern][0];
	sait_rest_assert_true(!empty($endpoint['methods'][$method]), 'Metodo incorrecto para ' . $pattern);
	sait_rest_assert_true(is_callable($endpoint['permission_callback']), 'Falta callback de permisos en ' . $pattern);
	sait_rest_assert_true(isset($endpoint['args']['idpedido']), 'Falta esquema idpedido en ' . $pattern);
}

$namespaces = $server->get_namespaces();
sait_rest_assert_true(in_array('saitplugin/v1', $namespaces, true), 'El namespace no aparece en discovery.');

$hello = sait_rest_request('GET', '/saitplugin/v1/hello');
sait_rest_assert_same(200, $hello->get_status(), 'Status de hello.');
sait_rest_assert_same('hello world!', $hello->get_data(), 'Cuerpo de hello.');

$hello_wrong_method = sait_rest_request('POST', '/saitplugin/v1/hello');
sait_rest_assert_same(404, $hello_wrong_method->get_status(), 'POST no permitido en hello.');

$event_body = '<event type="DESCONOCIDO" version="2" />';
$missing_token = sait_rest_request('POST', '/saitplugin/v1/saitevents', $event_body);
sait_rest_assert_same(401, $missing_token->get_status(), 'Webhook sin token.');
sait_rest_assert_same('Bad token', $missing_token->get_data(), 'Mensaje sin token.');

$invalid_token = sait_rest_request('POST', '/saitplugin/v1/saitevents', $event_body, 'incorrecto');
sait_rest_assert_same(401, $invalid_token->get_status(), 'Webhook con token invalido.');

$malformed_xml = sait_rest_request('POST', '/saitplugin/v1/saitevents', '<event', 'fixture-access-token');
sait_rest_assert_same(500, $malformed_xml->get_status(), 'XML invalido conserva status 500.');

$unknown_event = sait_rest_request('POST', '/saitplugin/v1/saitevents', $event_body, 'fixture-access-token');
sait_rest_assert_same(200, $unknown_event->get_status(), 'Evento desconocido.');
sait_rest_assert_same('OK', $unknown_event->get_data(), 'Respuesta de evento desconocido.');

$event_wrong_method = sait_rest_request('GET', '/saitplugin/v1/saitevents');
sait_rest_assert_same(404, $event_wrong_method->get_status(), 'GET no permitido en saitevents.');

$resend_anonymous = sait_rest_request('POST', '/saitplugin/v1/reenviar-pedido-sait/999999999');
sait_rest_assert_same(401, $resend_anonymous->get_status(), 'Reenvio anonimo.');

$subscriber_id = username_exists('sait_rest_subscriber');
if (!$subscriber_id) {
	$subscriber_id = wp_create_user('sait_rest_subscriber', 'fixture-password', 'rest.subscriber@example.test');
}
$subscriber = get_user_by('id', $subscriber_id);
$subscriber->set_role('subscriber');
wp_set_current_user($subscriber_id);
$resend_forbidden = sait_rest_request('POST', '/saitplugin/v1/reenviar-pedido-sait/999999999');
sait_rest_assert_same(403, $resend_forbidden->get_status(), 'Reenvio sin capacidad.');

$admin_id = username_exists('sait_rest_admin');
if (!$admin_id) {
	$admin_id = wp_create_user('sait_rest_admin', 'fixture-password', 'rest.admin@example.test');
}
$admin = get_user_by('id', $admin_id);
$admin->set_role('administrator');
wp_set_current_user($admin_id);

$resend = sait_rest_request('POST', '/saitplugin/v1/reenviar-pedido-sait/999999999');
sait_rest_assert_same(404, $resend->get_status(), 'Orden inexistente en reenvio POST.');
sait_rest_assert_same('Pedido no existe', $resend->get_data(), 'Mensaje de orden inexistente.');

$legacy = sait_rest_request('GET', '/saitplugin/v1/testpedido/999999999');
sait_rest_assert_same(404, $legacy->get_status(), 'Orden inexistente en alias legacy.');
sait_rest_assert_same('Pedido no existe', $legacy->get_data(), 'Mensaje del alias legacy.');

$invalid_id = sait_rest_request('POST', '/saitplugin/v1/reenviar-pedido-sait/no-numerico');
sait_rest_assert_same(404, $invalid_id->get_status(), 'ID no numerico no coincide con la ruta.');

$zero_id = sait_rest_request('POST', '/saitplugin/v1/reenviar-pedido-sait/0');
sait_rest_assert_same(400, $zero_id->get_status(), 'ID cero no cumple el esquema.');

$order = wc_create_order();
$order->set_billing_first_name('REST');
$order->set_billing_last_name('Fixture');
$order->set_billing_email('rest.order@example.test');
$order->save();
$success = sait_rest_request('POST', '/saitplugin/v1/reenviar-pedido-sait/' . $order->get_id());
sait_rest_assert_same(201, $success->get_status(), 'Reenvio autorizado.');
sait_rest_assert_same('enviado', $success->get_data()['estado'], 'Estado del reenvio autorizado.');
$order->delete(true);

$options_response = sait_rest_request('OPTIONS', '/saitplugin/v1/reenviar-pedido-sait/1');
sait_rest_assert_same(200, $options_response->get_status(), 'OPTIONS de reenvio.');

echo "Contrato REST seguro validado correctamente.\n";
