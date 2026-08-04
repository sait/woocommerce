<?php
/**
 * Pruebas de caracterizacion de eventos SAIT ejecutadas mediante WP-CLI.
 */

if (!defined('ABSPATH')) {
	throw new RuntimeException('WordPress no esta cargado.');
}

/**
 * @param mixed  $expected Valor esperado.
 * @param mixed  $actual Valor real.
 * @param string $message Contexto del fallo.
 * @return void
 */
function sait_test_assert_same($expected, $actual, $message)
{
	if ($expected !== $actual) {
		throw new RuntimeException(
			$message . ' Esperado: ' . var_export($expected, true)
			. '; recibido: ' . var_export($actual, true)
		);
	}
}

/**
 * @param bool   $condition Condicion esperada.
 * @param string $message Contexto del fallo.
 * @return void
 */
function sait_test_assert_true($condition, $message)
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

/**
 * Envia un fixture al endpoint REST real del plugin.
 *
 * @param string $fixture Nombre del XML.
 * @param string $token Token del request.
 * @return WP_REST_Response
 */
function sait_test_send_event($fixture, $token = 'fixture-access-token')
{
	$path = WP_CONTENT_DIR . '/sait-test-fixtures/events/' . $fixture;
	$body = file_get_contents($path);
	if ($body === false) {
		throw new RuntimeException('No se pudo leer el fixture: ' . $fixture);
	}

	$request = new WP_REST_Request('POST', '/saitplugin/v1/saitevents');
	$request->set_header('x-AccessToken', $token);
	$request->set_header('content-type', 'application/xml');
	$request->set_body($body);

	return rest_do_request($request);
}

/**
 * Envia un XML construido dentro de la prueba.
 *
 * @param SimpleXMLElement $xml Evento.
 * @return WP_REST_Response
 */
function sait_test_send_xml($xml)
{
	$request = new WP_REST_Request('POST', '/saitplugin/v1/saitevents');
	$request->set_header('x-AccessToken', 'fixture-access-token');
	$request->set_header('content-type', 'application/xml');
	$request->set_body($xml->asXML());

	return rest_do_request($request);
}

/**
 * Elimina datos creados por una ejecucion previa.
 *
 * @return void
 */
function sait_test_clean_event_data()
{
	global $wpdb;

	foreach (array('FIX-ART-001', 'FIX-USD-001') as $sku) {
		$product_id = wc_get_product_id_by_sku($sku);
		if ($product_id) {
			$product = wc_get_product($product_id);
			if ($product) {
				$product->delete(true);
			}
		}
	}

	foreach (array('Linea Fixture', 'Familia Fixture', 'Departamento Fixture', 'Categoria Fixture') as $name) {
		$term = get_term_by('name', $name, 'product_cat');
		if ($term && !is_wp_error($term)) {
			wp_delete_term($term->term_id, 'product_cat');
		}
	}

	if (!function_exists('wp_delete_user')) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
	}
	foreach (
		array(
			'cliente.fixture@example.test',
			'existente.fixture@example.test',
			'duplicado.fixture@example.test',
			'mapeado.fixture@example.test',
			'actualizar.fixture@example.test',
			'actualizado.fixture@example.test',
		) as $email
	) {
		$user = get_user_by('email', $email);
		if ($user) {
			wp_delete_user($user->ID);
		}
	}

	$table = $wpdb->prefix . 'sait_claves';
	foreach (
		array(
			array('arts', 'FIX-ART-001'),
			array('arts', 'FIX-USD-001'),
			array('lineas', 'FIX-LIN'),
			array('familia', 'FIX-FAM'),
			array('deptos', 'FIX-DEP'),
			array('catego', 'FIX-CAT'),
			array('clientes', '90001'),
			array('clientes', '90002'),
			array('clientes', '90003'),
			array('clientes', '90004'),
		) as $mapping
	) {
		$wpdb->delete(
			$table,
			array('tabla' => $mapping[0], 'clave' => $mapping[1]),
			array('%s', '%s')
		);
	}

	delete_transient('sait_stock_' . md5('FIX-ART-001'));
}

sait_test_clean_event_data();

$options = get_option('opciones_sait', array());
$options['SAITNube_URL'] = 'https://sait-api.invalid';
$options['SAITNube_APIKey'] = 'fixture-api-key';
$options['SAITNube_AccessToken'] = 'fixture-access-token';
$options['SAITNube_NumAlm'] = '1';
$options['SAITNube_ExistAlm_enabled'] = '0';
$options['SAITNube_ExistAlm'] = '';
$options['SAITNube_PrecioLista'] = '';
$options['SAITNube_TipoCambio'] = '17.0000';
unset($options[SAIT_WOOCOMMERCE_Settings::CATEGORY_SOURCE_KEY]);
update_option('opciones_sait', $options);

$bad_token = sait_test_send_event('acttc.xml', 'incorrecto');
sait_test_assert_same(401, $bad_token->get_status(), 'El token invalido debe responder 401.');
sait_test_assert_same('Bad token', $bad_token->get_data(), 'Mensaje de token invalido.');

$invalid_request = new WP_REST_Request('POST', '/saitplugin/v1/saitevents');
$invalid_request->set_header('x-AccessToken', 'fixture-access-token');
$invalid_request->set_body('<event');
$invalid_xml = rest_do_request($invalid_request);
sait_test_assert_same(500, $invalid_xml->get_status(), 'El XML invalido debe responder 500.');

$unknown_xml = simplexml_load_string('<event type="DESCONOCIDO" version="2" />');
$unknown_response = sait_test_send_xml($unknown_xml);
sait_test_assert_same('OK', $unknown_response->get_data(), 'Un evento desconocido debe responder OK.');

$category_cases = array(
	array('modlinea.xml', 'lineas', 'FIX-LIN'),
	array('modfamilia.xml', 'familia', 'FIX-FAM'),
	array('moddepto.xml', 'deptos', 'FIX-DEP'),
	array('modcategoria.xml', 'catego', 'FIX-CAT'),
);
foreach ($category_cases as $case) {
	$response = sait_test_send_event($case[0]);
	sait_test_assert_same(200, $response->get_status(), 'Status de categoria ' . $case[0]);
	$mapping = SAIT_UTILS::SAIT_getClaves($case[1], $case[2], null);
	sait_test_assert_true(isset($mapping->wcid), 'No se creo el mapeo de ' . $case[0]);
	$term = get_term($mapping->wcid, 'product_cat');
	sait_test_assert_true($term && !is_wp_error($term), 'No se creo el termino de ' . $case[0]);
}

$preexisting = new WC_Product_Simple();
$preexisting->set_name('Producto preexistente Fixture');
$preexisting->set_sku('FIX-ART-001');
$preexisting_id = $preexisting->save();
$duplicate_sku_error = null;
try {
	sait_test_send_event('modart-active.xml');
} catch (Throwable $error) {
	$duplicate_sku_error = $error;
}
sait_test_assert_true(
	$duplicate_sku_error instanceof WC_Data_Exception,
	'La version 1.2.3 debe conservar como brecha el error para un SKU preexistente sin mapeo.'
);
wc_get_product($preexisting_id)->delete(true);

$article_add = sait_test_send_event('modart-active.xml');
sait_test_assert_same(200, $article_add->get_status(), 'Status de alta MODART.');
sait_test_assert_same('ART ADD', $article_add->get_data(), 'Respuesta de alta MODART.');
$product_id = wc_get_product_id_by_sku('FIX-ART-001');
sait_test_assert_true((bool) $product_id, 'MODART no creo el producto.');
$product = wc_get_product($product_id);
sait_test_assert_same('draft', $product->get_status(), 'El producto nuevo debe ser borrador.');
sait_test_assert_same(7.0, (float) $product->get_stock_quantity(), 'Stock inicial normalizado por WooCommerce.');
$line_mapping = SAIT_UTILS::SAIT_getClaves('lineas', 'FIX-LIN', null);
sait_test_assert_true(
	in_array((int) $line_mapping->wcid, array_map('intval', $product->get_category_ids()), true),
	'MODART debe usar linea cuando la opcion no existe.'
);

$article_category_cases = array(
	array('linea', 'lineas', 'FIX-LIN'),
	array('familia', 'familia', 'FIX-FAM'),
	array('categoria', 'catego', 'FIX-CAT'),
	array('departamento', 'deptos', 'FIX-DEP'),
);
foreach ($article_category_cases as $case) {
	SAIT_WOOCOMMERCE()->settings()->set(SAIT_WOOCOMMERCE_Settings::CATEGORY_SOURCE_KEY, $case[0]);
	$response = sait_test_send_event('modart-active.xml');
	sait_test_assert_same('ART UPD', $response->get_data(), 'Actualizacion MODART con fuente ' . $case[0] . '.');
	$mapping = SAIT_UTILS::SAIT_getClaves($case[1], $case[2], null);
	$assigned_ids = array_map('intval', wc_get_product($product_id)->get_category_ids());
	sait_test_assert_same(
		array((int) $mapping->wcid),
		$assigned_ids,
		'MODART debe asignar la fuente ' . $case[0] . '.'
	);
}

$categories_before_missing = array_map('intval', wc_get_product($product_id)->get_category_ids());
SAIT_WOOCOMMERCE()->settings()->set(SAIT_WOOCOMMERCE_Settings::CATEGORY_SOURCE_KEY, 'familia');
$missing_attribute_xml = simplexml_load_file(WP_CONTENT_DIR . '/sait-test-fixtures/events/modart-active.xml');
unset($missing_attribute_xml->action[0]->flds[0]['familia']);
sait_test_send_xml($missing_attribute_xml);
sait_test_assert_same(
	$categories_before_missing,
	array_map('intval', wc_get_product($product_id)->get_category_ids()),
	'Un atributo ausente no debe borrar la categoria existente.'
);

SAIT_WOOCOMMERCE()->settings()->set(SAIT_WOOCOMMERCE_Settings::CATEGORY_SOURCE_KEY, 'categoria');
$missing_mapping_xml = simplexml_load_file(WP_CONTENT_DIR . '/sait-test-fixtures/events/modart-active.xml');
$missing_mapping_xml->action[0]->flds[0]['categoria'] = 'FIX-SIN-MAPEO';
sait_test_send_xml($missing_mapping_xml);
sait_test_assert_same(
	$categories_before_missing,
	array_map('intval', wc_get_product($product_id)->get_category_ids()),
	'Un mapeo inexistente no debe borrar la categoria existente.'
);

SAIT_WOOCOMMERCE()->settings()->set(SAIT_WOOCOMMERCE_Settings::CATEGORY_SOURCE_KEY, 'invalida');
sait_test_send_event('modart-active.xml');
sait_test_assert_same(
	array((int) $line_mapping->wcid),
	array_map('intval', wc_get_product($product_id)->get_category_ids()),
	'Una opcion persistida invalida debe usar linea.'
);

$article_update = sait_test_send_event('modart-active.xml');
sait_test_assert_same('ART UPD', $article_update->get_data(), 'Respuesta de actualizacion MODART.');
sait_test_assert_same($product_id, wc_get_product_id_by_sku('FIX-ART-001'), 'MODART duplico el SKU mapeado.');

$price = sait_test_send_event('actprecio.xml');
sait_test_assert_same('PRICE UPD', $price->get_data(), 'Respuesta ACTPRECIO.');
$product = wc_get_product($product_id);
sait_test_assert_same(116.0, (float) $product->get_regular_price(), 'Precio publico ACTPRECIO.');

$volume = sait_test_send_event('actprecio-volume-only.xml');
sait_test_assert_same('IGNORADO (ppubv*)', $volume->get_data(), 'Precio por volumen actual.');
sait_test_assert_same(116.0, (float) wc_get_product($product_id)->get_regular_price(), 'Volumen no debe cambiar precio.');

$single_stock = sait_test_send_event('actexist.xml');
sait_test_assert_same('STOCK UPD ACTEXIST', $single_stock->get_data(), 'Respuesta ACTEXIST simple.');
sait_test_assert_same(7.0, (float) wc_get_product($product_id)->get_stock_quantity(), 'Existencia simple normalizada.');

$options = get_option('opciones_sait');
$options['SAITNube_ExistAlm_enabled'] = '1';
$options['SAITNube_ExistAlm'] = '1,2';
update_option('opciones_sait', $options);
delete_transient('sait_stock_' . md5('FIX-ART-001'));
$multiple_stock = sait_test_send_event('actexist.xml');
sait_test_assert_same('STOCK UPD ACTEXIST', $multiple_stock->get_data(), 'Respuesta ACTEXIST multiple.');
sait_test_assert_same(9.0, (float) wc_get_product($product_id)->get_stock_quantity(), 'Existencia multi-almacen normalizada.');

$options = get_option('opciones_sait');
$options['SAITNube_NumAlm'] = '1';
update_option('opciones_sait', $options);
$global_rejected = sait_test_send_event('actexisgbl.xml');
sait_test_assert_same('STOCK ERR ACTEXISGBL', $global_rejected->get_data(), 'ACTEXISGBL con almacen configurado.');
$options['SAITNube_NumAlm'] = null;
update_option('opciones_sait', $options);
$global_stock = sait_test_send_event('actexisgbl.xml');
sait_test_assert_same('STOCK UPD', $global_stock->get_data(), 'Respuesta ACTEXISGBL.');
sait_test_assert_same(13.0, (float) wc_get_product($product_id)->get_stock_quantity(), 'Existencia global normalizada.');
$options['SAITNube_NumAlm'] = '1';
update_option('opciones_sait', $options);

$usd_product = new WC_Product_Simple();
$usd_product->set_name('Articulo USD Fixture');
$usd_product->set_sku('FIX-USD-001');
$usd_product->set_regular_price(0);
$usd_product_id = $usd_product->save();
SAIT_UTILS::SAIT_insertClaves('arts', 'FIX-USD-001', $usd_product_id);
$exchange_rate = sait_test_send_event('acttc.xml');
sait_test_assert_same('Upd TC', $exchange_rate->get_data(), 'Respuesta ACTTC.');
$options = get_option('opciones_sait');
sait_test_assert_same('18.5000', (string) $options['SAITNube_TipoCambio'], 'Tipo de cambio guardado.');
sait_test_assert_same(214.6, (float) wc_get_product($usd_product_id)->get_regular_price(), 'Precio convertido a pesos.');

$customer_add = sait_test_send_event('modcli.xml');
sait_test_assert_same('Cli ADD', $customer_add->get_data(), 'Alta MODCLI.');
$customer = get_user_by('email', 'cliente.fixture@example.test');
sait_test_assert_true((bool) $customer, 'MODCLI no creo el usuario.');
$customer_mapping = SAIT_UTILS::SAIT_getClaves('clientes', '90001', null);
sait_test_assert_same((int) $customer->ID, (int) $customer_mapping->wcid, 'Mapeo del cliente nuevo.');

$customer_repeat = sait_test_send_event('modcli.xml');
sait_test_assert_same('Cliente ya existe', $customer_repeat->get_data(), 'MODCLI repetido.');

$existing_user_id = wc_create_new_customer('existente.fixture@example.test');
$existing_xml = simplexml_load_file(WP_CONTENT_DIR . '/sait-test-fixtures/events/modcli.xml');
$existing_xml->action[0]->keys[0]['numcli'] = '90002';
$existing_xml->action[0]->flds[0]['emailtw'] = 'existente.fixture@example.test';
$existing_response = sait_test_send_xml($existing_xml);
sait_test_assert_same('Cliente ligado a usuario existente', $existing_response->get_data(), 'Usuario preexistente.');
$existing_mapping = SAIT_UTILS::SAIT_getClaves('clientes', '90002', null);
sait_test_assert_same((int) $existing_user_id, (int) $existing_mapping->wcid, 'Mapeo de usuario preexistente.');

$mapped_user_id = wc_create_new_customer('mapeado.fixture@example.test');
$duplicate_user_id = wc_create_new_customer('duplicado.fixture@example.test');
SAIT_UTILS::SAIT_insertClaves('clientes', '90003', $mapped_user_id);
$duplicate_xml = simplexml_load_file(WP_CONTENT_DIR . '/sait-test-fixtures/events/modcli.xml');
$duplicate_xml->action[0]->keys[0]['numcli'] = '90003';
$duplicate_xml->action[0]->flds[0]['emailtw'] = 'duplicado.fixture@example.test';
$duplicate_response = sait_test_send_xml($duplicate_xml);
sait_test_assert_same(
	'Correo ya asignado a otro usuario',
	$duplicate_response->get_data(),
	'Correo duplicado MODCLI.'
);
sait_test_assert_true((bool) $duplicate_user_id, 'No se creo el usuario para la colision.');

$update_user_id = wc_create_new_customer('actualizar.fixture@example.test');
SAIT_UTILS::SAIT_insertClaves('clientes', '90004', $update_user_id);
$update_xml = simplexml_load_file(WP_CONTENT_DIR . '/sait-test-fixtures/events/modcli.xml');
$update_xml->action[0]->keys[0]['numcli'] = '90004';
$update_xml->action[0]->flds[0]['emailtw'] = 'actualizado.fixture@example.test';
$update_response = sait_test_send_xml($update_xml);
sait_test_assert_same('Cliente actualizado', $update_response->get_data(), 'Actualizacion MODCLI.');
$updated_user = get_user_by('id', $update_user_id);
sait_test_assert_same('actualizado.fixture@example.test', $updated_user->user_email, 'Correo actualizado por MODCLI.');

$article_delete = sait_test_send_event('modart-disabled.xml');
sait_test_assert_same('OK', $article_delete->get_data(), 'Baja MODART.');
sait_test_assert_same('trash', get_post_status($product_id), 'MODART debe enviar el producto a papelera.');

echo "Eventos caracterizados correctamente sin usar API SAIT real.\n";
