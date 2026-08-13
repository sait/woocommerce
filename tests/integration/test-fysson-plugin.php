<?php

if (!defined('ABSPATH')) {
	exit(1);
}

function sait_fysson_assert_same($expected, $actual, $message)
{
	if ($expected !== $actual) {
		throw new RuntimeException(
			$message . ' Esperado: ' . var_export($expected, true) . ' Actual: ' . var_export($actual, true)
		);
	}
}

function sait_fysson_assert_true($condition, $message)
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function sait_fysson_send_modart()
{
	$body = file_get_contents(WP_CONTENT_DIR . '/sait-test-fixtures/events/modart-active.xml');
	if ($body === false) {
		throw new RuntimeException('No se pudo leer el fixture MODART.');
	}

	$request = new WP_REST_Request('POST', '/saitplugin/v1/saitevents');
	$request->set_header('x-AccessToken', 'fixture-access-token');
	$request->set_header('content-type', 'application/xml');
	$request->set_body($body);

	return rest_do_request($request);
}

function sait_fysson_cleanup()
{
	global $wpdb;

	$product_id = wc_get_product_id_by_sku('FIX-ART-001');
	if ($product_id) {
		$product = wc_get_product($product_id);
		if ($product) {
			$product->delete(true);
		}
	}

	$wpdb->delete(
		$wpdb->prefix . 'sait_claves',
		array('tabla' => 'arts', 'clave' => 'FIX-ART-001'),
		array('%s', '%s')
	);
	$wpdb->delete(
		$wpdb->prefix . 'sait_claves',
		array('tabla' => 'lineas', 'clave' => 'FIX-LIN'),
		array('%s', '%s')
	);
	foreach (array('Categoría manual Fysson', 'Línea SAIT Fysson') as $term_name) {
		$term = get_term_by('name', $term_name, 'product_cat');
		if ($term && !is_wp_error($term)) {
			wp_delete_term($term->term_id, 'product_cat');
		}
	}
	delete_transient('sait_stock_' . md5('FIX-ART-001'));
}

sait_fysson_assert_true(class_exists('SAIT_Fysson_Plugin'), 'El complemento Fysson debe cargar su bootstrap.');
sait_fysson_assert_true(
	has_filter('sait_woocommerce_modart_existing_sku_mode') !== false,
	'Fysson debe registrar el tratamiento de SKU preexistentes.'
);
sait_fysson_assert_true(
	has_filter('sait_woocommerce_modart_sync_category', '__return_false') !== false,
	'Fysson debe desactivar la sincronizacion de categorias.'
);
sait_fysson_assert_true(
	has_filter('sait_woocommerce_modart_sync_model', '__return_false') !== false,
	'Fysson debe desactivar la sincronizacion del modelo.'
);

$original_options = SAIT_WOOCOMMERCE()->settings()->all();
$options = $original_options;
$options['SAITNube_URL'] = 'https://sait-api.invalid';
$options['SAITNube_APIKey'] = 'fixture-api-key';
$options['SAITNube_AccessToken'] = 'fixture-access-token';
$options['SAITNube_NumAlm'] = '1';
$options[SAIT_WOOCOMMERCE_Settings::CATEGORY_SOURCE_KEY] = 'linea';
$options[SAIT_WOOCOMMERCE_Settings::SYNC_MODEL_KEY] = '1';
update_option(SAIT_WOOCOMMERCE_Settings::OPTION_NAME, $options);

sait_fysson_cleanup();
$manual_term = wp_insert_term('Categoría manual Fysson', 'product_cat');
if (is_wp_error($manual_term)) {
	throw new RuntimeException($manual_term->get_error_message());
}
$manual_term_id = (int) $manual_term['term_id'];
$sait_term = wp_insert_term('Línea SAIT Fysson', 'product_cat');
if (is_wp_error($sait_term)) {
	throw new RuntimeException($sait_term->get_error_message());
}
$sait_term_id = (int) $sait_term['term_id'];
SAIT_UTILS::SAIT_insertClaves('lineas', 'FIX-LIN', $sait_term_id);

$preexisting = new WC_Product_Simple();
$preexisting->set_name('Producto manual Fysson');
$preexisting->set_sku('FIX-ART-001');
$preexisting->set_short_description('Descripción manual Fysson');
$preexisting->set_category_ids(array($manual_term_id));
$preexisting_id = $preexisting->save();

$ignored = sait_fysson_send_modart();
sait_fysson_assert_same(200, $ignored->get_status(), 'El SKU preexistente ignorado debe responder correctamente.');
sait_fysson_assert_same('ART IGNORADO SKU EXISTENTE', $ignored->get_data(), 'Respuesta del SKU preexistente.');
sait_fysson_assert_true(
	!isset(SAIT_UTILS::SAIT_getClaves('arts', 'FIX-ART-001', null)->wcid),
	'Fysson no debe relacionar automaticamente el SKU preexistente.'
);
$preexisting = wc_get_product($preexisting_id);
sait_fysson_assert_same('Producto manual Fysson', $preexisting->get_name(), 'No debe cambiar el nombre del SKU ignorado.');
sait_fysson_assert_same('Descripción manual Fysson', $preexisting->get_short_description(), 'No debe cambiar su descripcion corta.');
sait_fysson_assert_same(array($manual_term_id), array_map('intval', $preexisting->get_category_ids()), 'No debe cambiar su categoria.');
$preexisting->delete(true);

$created = sait_fysson_send_modart();
sait_fysson_assert_same('ART ADD', $created->get_data(), 'Fysson debe seguir creando articulos nuevos.');
$product_id = wc_get_product_id_by_sku('FIX-ART-001');
sait_fysson_assert_true((bool) $product_id, 'No se creo el articulo nuevo de Fysson.');
$mapping = SAIT_UTILS::SAIT_getClaves('arts', 'FIX-ART-001', null);
sait_fysson_assert_same($product_id, (int) $mapping->wcid, 'El articulo nuevo debe quedar relacionado.');
$product = wc_get_product($product_id);
sait_fysson_assert_true(
	!in_array($sait_term_id, array_map('intval', $product->get_category_ids()), true),
	'El articulo nuevo no debe recibir la categoria mapeada desde SAIT.'
);
sait_fysson_assert_same('', $product->get_short_description(), 'El articulo nuevo no debe recibir el modelo.');
sait_fysson_assert_same('Descripcion de prueba', $product->get_description(), 'Debe conservar la descripcion larga de SAIT.');

$product->set_short_description('Descripción corta conservada');
$product->set_category_ids(array($manual_term_id));
$product->save();
$updated = sait_fysson_send_modart();
sait_fysson_assert_same('ART UPD', $updated->get_data(), 'El articulo relacionado debe seguir actualizandose.');
$product = wc_get_product($product_id);
sait_fysson_assert_same('Articulo de prueba', $product->get_name(), 'El articulo relacionado debe actualizar el nombre.');
sait_fysson_assert_same('Descripción corta conservada', $product->get_short_description(), 'Debe conservar la descripcion corta existente.');
sait_fysson_assert_same(array($manual_term_id), array_map('intval', $product->get_category_ids()), 'Debe conservar la categoria existente.');

sait_fysson_cleanup();
update_option(SAIT_WOOCOMMERCE_Settings::OPTION_NAME, $original_options);

echo "Plugin complementario de Fysson validado correctamente.\n";
