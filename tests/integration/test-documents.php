<?php
/**
 * Caracterizacion de payloads de pedidos y cotizaciones SAIT.
 */

if (!defined('ABSPATH')) {
	throw new RuntimeException('WordPress no esta cargado.');
}

require_once WP_PLUGIN_DIR . '/sait-woocommerce/includes/SAIT_WOOCOMMERCE-orders.php';

function sait_document_assert_same($expected, $actual, $message)
{
	if ($expected !== $actual) {
		throw new RuntimeException(
			$message . ' Esperado: ' . var_export($expected, true)
			. '; recibido: ' . var_export($actual, true)
		);
	}
}

function sait_document_assert_true($condition, $message)
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function sait_document_last_request()
{
	$request = get_option('sait_test_last_request');
	if (!is_array($request) || !isset($request['body'])) {
		throw new RuntimeException('El mock no capturo el documento SAIT.');
	}

	return $request;
}

function sait_document_clean_data()
{
	global $wpdb;

	$order_ids = get_option('sait_test_document_order_ids', array());
	foreach ((array) $order_ids as $order_id) {
		$order = wc_get_order($order_id);
		if ($order) {
			$order->delete(true);
		}
	}
	delete_option('sait_test_document_order_ids');
	delete_option('sait_test_last_request');

	$product_id = wc_get_product_id_by_sku('FIX-ART-001');
	if ($product_id) {
		$product = wc_get_product($product_id);
		if ($product) {
			$product->delete(true);
		}
	}

	if (!function_exists('wp_delete_user')) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
	}
	foreach (array('mapeado.documento@example.test', 'eventual.mapeado@example.test', 'normal.fixture@example.test') as $email) {
		$user = get_user_by('email', $email);
		if ($user) {
			wp_delete_user($user->ID);
		}
	}

	$table = $wpdb->prefix . 'sait_claves';
	$wpdb->delete($table, array('tabla' => 'arts', 'clave' => 'FIX-ART-001'), array('%s', '%s'));
	$wpdb->delete($table, array('tabla' => 'clientes', 'clave' => '123'), array('%s', '%s'));
	$wpdb->delete($table, array('tabla' => 'clientes', 'clave' => '-789'), array('%s', '%s'));
}

function sait_document_create_order($product, $email, $customer_id = 0, $total = 208.8)
{
	$order = wc_create_order(array('customer_id' => $customer_id));
	if (is_wp_error($order)) {
		throw new RuntimeException($order->get_error_message());
	}

	$order->add_product(
		$product,
		2,
		array(
			'subtotal' => 232,
			'total'    => $total,
		)
	);

	$order->set_billing_first_name('Cliente');
	$order->set_billing_last_name('Documento');
	$order->set_billing_email($email);
	$order->set_billing_phone('6620000000');
	$order->set_billing_address_1('Calle Fixture');
	$order->set_billing_address_2('10');
	$order->set_billing_city('Hermosillo');
	$order->set_billing_state('SON');
	$order->set_billing_postcode('83000');
	$order->set_shipping_address_1('Avenida Envio');
	$order->set_shipping_address_2('20');
	$order->set_shipping_city('Hermosillo');
	$order->set_shipping_state('SON');
	$order->set_shipping_postcode('83100');
	$order->set_customer_note('Observacion Fixture');
	$order->save();
	$items = $order->get_items();
	$created_item = reset($items);
	sait_document_assert_same((float) $total, (float) $created_item->get_total(), 'Total del fixture WooCommerce.');

	$order_ids = get_option('sait_test_document_order_ids', array());
	$order_ids[] = $order->get_id();
	update_option('sait_test_document_order_ids', $order_ids, false);

	return $order;
}

function sait_document_assert_common_payload($payload, $order_id, $expected_discount = 10.0)
{
	sait_document_assert_same('WO' . $order_id, $payload['numdoc'], 'Numero de documento.');
	sait_document_assert_same(' 1', $payload['numalm'], 'Almacen del documento.');
	sait_document_assert_same('P', $payload['divisa'], 'Divisa del documento.');
	sait_document_assert_same(1, $payload['tc'], 'Tipo de cambio base.');
	sait_document_assert_same('Observacion Fixture', $payload['obs'], 'Observaciones.');
	sait_document_assert_same(
		'1^WEB^AVENIDA ENVIO^20^^HERMOSILLO^SON^83100^6620000000',
		$payload['direnvio'],
		'Direccion de envio.'
	);
	sait_document_assert_same(1, count($payload['items']), 'Cantidad de partidas.');
	$item = $payload['items'][0];
	sait_document_assert_same(2, $item['cant'], 'Cantidad del articulo.');
	sait_document_assert_same('FIX-ART-001', $item['numart'], 'SKU del articulo.');
	sait_document_assert_same('PZA', $item['unidad'], 'Unidad simulada.');
	sait_document_assert_same(116.0, (float) $item['preciopub'], 'Precio publico.');
	sait_document_assert_same((float) $expected_discount, (float) $item['pjedesc1'], 'Descuento calculado.');
}

sait_document_clean_data();

$options = get_option('opciones_sait', array());
$options['SAITNube_URL'] = 'https://sait-api.invalid';
$options['SAITNube_APIKey'] = 'fixture-api-key';
$options['SAITNube_AccessToken'] = 'fixture-access-token';
$options['SAITNube_TipoDoc'] = 'P';
$options['SAITNube_NumAlm'] = '1';
$options['SAITNube_PedidoObs_enabled'] = '1';
$options['SAITNube_PedidoDirenvio_enabled'] = '1';
$options['SAITNube_FuncionPersonalizadaPedido_enabled'] = '0';
update_option('opciones_sait', $options);

$product = new WC_Product_Simple();
$product->set_name('Articulo Documento Fixture');
$product->set_sku('FIX-ART-001');
$product->set_regular_price(116);
$product_id = $product->save();
SAIT_UTILS::SAIT_insertClaves('arts', 'FIX-ART-001', $product_id);

$mapped_user_id = wc_create_new_customer('mapeado.documento@example.test');
SAIT_UTILS::SAIT_insertClaves('clientes', '123', $mapped_user_id);
$mapped_order = sait_document_create_order($product, 'mapeado.documento@example.test', $mapped_user_id);

delete_option('sait_test_request_counts');
$builder_customer = array('numcli' => '  123', 'numcliev' => '', 'clievent' => null);
$mapped_items = $mapped_order->get_items();
$order_builder = new SAIT_WOOCOMMERCE_OrderBuilder($options, 1763051220);
$built_order = json_decode(wp_json_encode($order_builder->build(
	$mapped_order,
	'1',
	array(key($mapped_items) => 'PZA'),
	$builder_customer
)), true);
sait_document_assert_same('20251113', $built_order['fentrega'], 'Fecha determinista del builder.');
sait_document_assert_same('PZA', $built_order['items'][0]['unidad'], 'Unidad recibida por el builder.');
sait_document_assert_same(array(), get_option('sait_test_request_counts', array()), 'El builder no debe hacer HTTP.');

$mapped_response = SAIT_WOOCOMMERCE_Orders::SAIT_sendPedido($mapped_order, '1', true);
sait_document_assert_same(201, wp_remote_retrieve_response_code($mapped_response), 'HTTP pedido mapeado.');
$mapped_request = sait_document_last_request();
sait_document_assert_same('/api/v3/pedidos', $mapped_request['path'], 'Endpoint de pedido.');
$mapped_payload = $mapped_request['body'];
sait_document_assert_common_payload($mapped_payload, $mapped_order->get_id());
sait_document_assert_same('  123', $mapped_payload['numcli'], 'Cliente mapeado.');
sait_document_assert_same('', $mapped_payload['numcliev'], 'Cliente eventual vacio.');
sait_document_assert_true(!isset($mapped_payload['clievent']), 'Cliente mapeado no debe enviar clievent.');

$mapped_eventual_user_id = wc_create_new_customer('eventual.mapeado@example.test');
SAIT_UTILS::SAIT_insertClaves('clientes', '-789', $mapped_eventual_user_id);
$mapped_eventual_order = sait_document_create_order($product, 'eventual.mapeado@example.test', $mapped_eventual_user_id);
$mapped_eventual_response = SAIT_WOOCOMMERCE_Orders::SAIT_sendPedido($mapped_eventual_order, '1', true);
sait_document_assert_same(201, wp_remote_retrieve_response_code($mapped_eventual_response), 'HTTP eventual mapeado.');
$mapped_eventual_payload = sait_document_last_request()['body'];
sait_document_assert_same('', $mapped_eventual_payload['numcli'], 'Eventual mapeado sin numcli.');
sait_document_assert_same(' -789', $mapped_eventual_payload['numcliev'], 'Eventual mapeado por guion.');
sait_document_assert_true(!isset($mapped_eventual_payload['clievent']), 'Eventual mapeado no debe enviar clievent.');

$normal_order = sait_document_create_order($product, 'normal.fixture@example.test');
$normal_response = SAIT_WOOCOMMERCE_Orders::SAIT_sendPedido($normal_order, '2', true);
sait_document_assert_same(201, wp_remote_retrieve_response_code($normal_response), 'HTTP cliente normal.');
$normal_payload = sait_document_last_request()['body'];
sait_document_assert_same('  123', $normal_payload['numcli'], 'Cliente normal encontrado por correo.');
sait_document_assert_same('2', $normal_payload['formapago'], 'Forma de pago thankyou.');

$eventual_order = sait_document_create_order($product, 'eventual.fixture@example.test');
$eventual_response = SAIT_WOOCOMMERCE_Orders::SAIT_sendPedido($eventual_order, '1', true);
sait_document_assert_same(201, wp_remote_retrieve_response_code($eventual_response), 'HTTP eventual existente.');
$eventual_payload = sait_document_last_request()['body'];
sait_document_assert_same('', $eventual_payload['numcli'], 'Eventual sin numcli.');
sait_document_assert_same(' -456', $eventual_payload['numcliev'], 'Eventual reutilizado por numcliev.');
sait_document_assert_true(!isset($eventual_payload['clievent']), 'Eventual existente no debe reenviar clievent.');

$new_order = sait_document_create_order($product, 'nuevo.documento@example.test');
$new_response = SAIT_WOOCOMMERCE_Orders::SAIT_sendPedido($new_order, '2', true);
sait_document_assert_same(201, wp_remote_retrieve_response_code($new_response), 'HTTP cliente nuevo.');
$new_payload = sait_document_last_request()['body'];
sait_document_assert_same('', $new_payload['numcli'], 'Cliente nuevo sin numcli.');
sait_document_assert_same('', $new_payload['numcliev'], 'Cliente nuevo sin numcliev.');
sait_document_assert_same('nuevo.documento@example.test', $new_payload['clievent']['email'], 'Objeto clievent nuevo.');

delete_option('sait_test_request_counts');
$invalid_email_order = sait_document_create_order($product, '');
$invalid_email_response = SAIT_WOOCOMMERCE_Orders::SAIT_sendPedido($invalid_email_order, '2', true);
sait_document_assert_same(201, wp_remote_retrieve_response_code($invalid_email_response), 'HTTP correo invalido.');
$invalid_email_payload = sait_document_last_request()['body'];
sait_document_assert_same('', $invalid_email_payload['numcli'], 'Correo invalido sin numcli.');
sait_document_assert_same('', $invalid_email_payload['numcliev'], 'Correo invalido sin numcliev.');
sait_document_assert_same('', $invalid_email_payload['clievent']['email'], 'Correo vacio conserva clievent.');
$invalid_email_counts = get_option('sait_test_request_counts', array());
sait_document_assert_true(!isset($invalid_email_counts['GET /api/v3/clientes']), 'Correo invalido no debe consultar clientes.');

$quote_order = sait_document_create_order($product, 'cotizacion.documento@example.test', 0, 232.0);
$quote_response = SAIT_WOOCOMMERCE_Orders::SAIT_sendCotizacion($quote_order, '2', true);
sait_document_assert_same(201, wp_remote_retrieve_response_code($quote_response), 'HTTP cotizacion.');
$quote_request = sait_document_last_request();
sait_document_assert_same('/api/v3/cotizaciones', $quote_request['path'], 'Endpoint de cotizacion.');
$quote_payload = $quote_request['body'];
sait_document_assert_common_payload($quote_payload, $quote_order->get_id(), 0.0);
sait_document_assert_true(isset($quote_payload['fecha']), 'Cotizacion debe incluir fecha.');
sait_document_assert_true(isset($quote_payload['hora']), 'Cotizacion debe incluir hora.');

$request_counts = get_option('sait_test_request_counts', array());
sait_document_assert_true(!isset($request_counts['GET /api/v3/clienteseventuales']), 'No debe consultarse /clienteseventuales.');

echo "Documentos SAIT caracterizados correctamente con API simulada.\n";
