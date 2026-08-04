<?php

if (!defined('ABSPATH')) {
	exit(1);
}

function sait_price_service_assert_same($expected, $actual, $message)
{
	if ($expected !== $actual) {
		throw new RuntimeException(
			$message . ' Esperado: ' . var_export($expected, true) . ' Actual: ' . var_export($actual, true)
		);
	}
}

function sait_price_service_assert_true($condition, $message)
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

class SAIT_Test_Price_Settings
{
	public function get($key, $default = '')
	{
		return $key === 'SAITNube_NumAlm' ? '1' : $default;
	}
}

class SAIT_Test_Price_Branch
{
	public function get_selected_branch()
	{
		return '';
	}
}

class SAIT_Test_Price_Client implements SAIT_WOOCOMMERCE_SaitClientInterface
{
	public $requests = array();

	public function get($uri, $retry = true)
	{
		$this->requests[] = $uri;
		if (strpos($uri, '/api/v3/articulos/') === 0) {
			return array('ok' => true, 'result' => array('unidad' => 'PZA'));
		}

		return array(
			'ok'     => true,
			'result' => array('preciopub' => 116, 'pjedesc' => 10),
		);
	}

	public function get_legacy($uri, $retry = true)
	{
		$response = $this->get($uri, $retry);
		return array('result' => $response['result']);
	}

	public function post($uri, $body, $wait = false)
	{
		return array();
	}
}

$settings = new SAIT_Test_Price_Settings();
$branch = new SAIT_Test_Price_Branch();
$client = new SAIT_Test_Price_Client();
$service = new SAIT_WOOCOMMERCE_PriceService($settings, $client, $branch);
$product = new WC_Product_Simple();
$product->set_sku('FIX-PRICE-CACHE');
$product->set_regular_price('116');

wp_set_current_user(0);
$service->invalidate_sku('FIX-PRICE-CACHE');
$first = $service->get_price($product, 1);
$second = $service->get_price($product, 1);
sait_price_service_assert_same($first, $second, 'La misma consulta debe reutilizarse dentro del request.');
sait_price_service_assert_same(2, count($client->requests), 'Artículo y precio deben consultarse una sola vez.');
sait_price_service_assert_same('    0', $first['numcli'], 'El cliente público debe conservar padding de cinco.');
sait_price_service_assert_same(' 1', $first['numalm'], 'El almacén debe conservar padding de dos.');

$quantity_price = $service->get_price($product, 2);
sait_price_service_assert_same(3, count($client->requests), 'Otra cantidad sólo debe recalcular el precio.');
sait_price_service_assert_same(2.0, $quantity_price['cantidad'], 'La cantidad debe formar parte del contexto.');
$last_url = end($client->requests);
$query = array();
parse_str((string) wp_parse_url($last_url, PHP_URL_QUERY), $query);
sait_price_service_assert_same('    0', $query['numcli'], 'La URL debe entregar numcli público con padding.');
sait_price_service_assert_same(' 1', $query['numalm'], 'La URL debe entregar numalm con padding.');
sait_price_service_assert_same('2', $query['cant'], 'La URL debe entregar la cantidad solicitada.');

$cached_client = new SAIT_Test_Price_Client();
$cached_service = new SAIT_WOOCOMMERCE_PriceService($settings, $cached_client, $branch);
$cached_service->get_price($product, 1);
sait_price_service_assert_same(0, count($cached_client->requests), 'Un nuevo request debe reutilizar transients vigentes.');

$version_before = (int) get_option(SAIT_WOOCOMMERCE_PriceService::CACHE_VERSION_OPTION, 1);
$cached_service->invalidate_sku('FIX-PRICE-CACHE');
sait_price_service_assert_same(
	$version_before + 1,
	(int) get_option(SAIT_WOOCOMMERCE_PriceService::CACHE_VERSION_OPTION, 1),
	'Invalidar un SKU debe cambiar la versión de precios.'
);
$cached_service->get_price($product, 1);
sait_price_service_assert_same(2, count($cached_client->requests), 'Tras invalidar debe consultar artículo y precio.');

$existing_user_id = username_exists('sait-price-cache-fixture');
if ($existing_user_id) {
	$existing_mapping = SAIT_WOOCOMMERCE()->mapping_repository()->find_by_woocommerce_id('clientes', $existing_user_id);
	if ($existing_mapping) {
		SAIT_WOOCOMMERCE()->mapping_repository()->delete($existing_mapping->id);
	}
	wp_delete_user($existing_user_id);
}
$user_id = wp_insert_user(
	array(
		'user_login' => 'sait-price-cache-fixture',
		'user_pass'  => wp_generate_password(20),
		'user_email' => 'price.cache.fixture@example.test',
		'role'       => 'customer',
	)
);
sait_price_service_assert_true(!is_wp_error($user_id), 'Debe crear el usuario fixture de precios.');
$mapping_id = SAIT_WOOCOMMERCE()->mapping_repository()->add('clientes', '739', $user_id);
sait_price_service_assert_true((bool) $mapping_id, 'Debe mapear el usuario fixture a un numcli único.');
delete_transient('sait_cli_' . $user_id);
wp_set_current_user($user_id);
$registered_client = new SAIT_Test_Price_Client();
$registered_service = new SAIT_WOOCOMMERCE_PriceService($settings, $registered_client, $branch);
$registered_price = $registered_service->get_price($product, 1);
sait_price_service_assert_same('  739', $registered_price['numcli'], 'El numcli registrado debe conservar padding.');
$registered_url = end($registered_client->requests);
$registered_query = array();
parse_str((string) wp_parse_url($registered_url, PHP_URL_QUERY), $registered_query);
sait_price_service_assert_same('  739', $registered_query['numcli'], 'La API debe recibir el numcli registrado con padding.');
sait_price_service_assert_same(1, count($registered_client->requests), 'El artículo global debe reutilizarse entre contextos.');
SAIT_WOOCOMMERCE()->mapping_repository()->delete($mapping_id);
delete_transient('sait_cli_' . $user_id);
wp_delete_user($user_id);
wp_set_current_user(0);

global $wpdb;
$autoload = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT autoload FROM {$wpdb->options} WHERE option_name = %s",
		SAIT_WOOCOMMERCE_PriceService::CACHE_VERSION_OPTION
	)
);
sait_price_service_assert_true(
	!in_array($autoload, array('yes', 'on', 'auto-on', 'auto'), true),
	'La versión de caché no debe cargarse automáticamente.'
);
$settings_version = (int) get_option(SAIT_WOOCOMMERCE_PriceService::CACHE_VERSION_OPTION, 1);
$cached_service->invalidate_after_settings_update(
	SAIT_WOOCOMMERCE_Settings::OPTION_NAME,
	array('SAITNube_NumAlm' => '1'),
	array('SAITNube_NumAlm' => '2')
);
sait_price_service_assert_same(
	$settings_version + 1,
	(int) get_option(SAIT_WOOCOMMERCE_PriceService::CACHE_VERSION_OPTION, 1),
	'Cambiar una regla de precio debe invalidar la versión.'
);
sait_price_service_assert_same(86400, SAIT_WOOCOMMERCE_PriceService::ARTICLE_TTL, 'TTL explícito de artículo.');
sait_price_service_assert_same(900, SAIT_WOOCOMMERCE_PriceService::PRICE_TTL, 'TTL explícito de precio.');

echo "Servicio de precios SAIT cacheado y aislado correctamente.\n";
