<?php

if (!defined('ABSPATH')) {
	exit(1);
}

function sait_client_assert_same($expected, $actual, $message)
{
	if ($expected !== $actual) {
		throw new RuntimeException(
			$message . ' Esperado: ' . var_export($expected, true) .
			' Actual: ' . var_export($actual, true)
		);
	}
}

function sait_client_assert_true($condition, $message)
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

class SAIT_Test_Fake_Client implements SAIT_WOOCOMMERCE_SaitClientInterface
{
	public function get($uri, $retry = true)
	{
		return array(
			'ok' => true,
			'status_code' => 200,
			'data' => array('result' => array('fake' => $uri)),
			'result' => array('fake' => $uri),
			'error_code' => '',
			'mensaje' => 'fake',
		);
	}

	public function get_legacy($uri, $retry = true)
	{
		return $this->get($uri, $retry)['data'];
	}

	public function post($uri, $body, $wait = false)
	{
		return sait_test_http_response(array('result' => 'FAKE'), 201);
	}
}

$settings = SAIT_WOOCOMMERCE()->settings();
$original_options = get_option(SAIT_WOOCOMMERCE_Settings::OPTION_NAME, array());
$options = is_array($original_options) ? $original_options : array();
$options['SAITNube_URL'] = 'https://sait-api.invalid';
$options['SAITNube_APIKey'] = 'fixture-api-key';
update_option(SAIT_WOOCOMMERCE_Settings::OPTION_NAME, $options);
delete_option('sait_test_request_counts');
delete_option('sait_test_last_request');

$client = SAIT_WOOCOMMERCE()->sait_client();
sait_client_assert_true($client instanceof SAIT_WOOCOMMERCE_SaitClient, 'Debe crear el cliente HTTP real.');

$valid = $client->get('/api/v3/articulos/FIX-ART-001', false);
sait_client_assert_same(true, $valid['ok'], 'Respuesta valida.');
sait_client_assert_same(200, $valid['status_code'], 'Status valido.');
sait_client_assert_same('FIX-ART-001', $valid['result']['numart'], 'Nodo result normalizado.');
$last_request = get_option('sait_test_last_request');
sait_client_assert_same(5, $last_request['timeout'], 'Timeout GET centralizado.');
sait_client_assert_same(false, $last_request['sslverify'], 'SSL GET compatible con instalaciones actuales.');
sait_client_assert_same(true, $last_request['blocking'], 'GET bloqueante.');
sait_client_assert_same(true, $last_request['has_api_key'], 'Header API key presente.');

$null_result = $client->get('/api/v3/test-result-null', false);
sait_client_assert_same(true, $null_result['ok'], 'result null sigue siendo JSON valido.');
sait_client_assert_same(null, $null_result['result'], 'result null normalizado.');

$invalid_json = $client->get('/api/v3/test-invalid-json', true);
sait_client_assert_same(false, $invalid_json['ok'], 'JSON invalido rechazado.');
sait_client_assert_same('invalid_json', $invalid_json['error_code'], 'Clasificacion JSON invalido.');

$transport_error = $client->get('/api/v3/test-wp-error', true);
sait_client_assert_same(false, $transport_error['ok'], 'WP_Error rechazado.');
sait_client_assert_same('transport_error', $transport_error['error_code'], 'Clasificacion WP_Error.');

$http_error = $client->get('/api/v3/test-http-error', true);
sait_client_assert_same(false, $http_error['ok'], 'HTTP no exitoso rechazado.');
sait_client_assert_same(503, $http_error['status_code'], 'Status HTTP no exitoso.');
sait_client_assert_same('http_error', $http_error['error_code'], 'Clasificacion HTTP.');

$counts = get_option('sait_test_request_counts', array());
sait_client_assert_same(2, $counts['GET /api/v3/test-invalid-json'], 'JSON invalido debe reintentarse una vez.');
sait_client_assert_same(2, $counts['GET /api/v3/test-wp-error'], 'WP_Error debe reintentarse una vez.');
sait_client_assert_same(1, $counts['GET /api/v3/test-http-error'], 'HTTP no exitoso no debe reintentarse.');

$post = $client->post('/api/v3/pedidos', array('numdoc' => 'WOFIXTURE-CLIENT'), true);
sait_client_assert_same(201, wp_remote_retrieve_response_code($post), 'POST conserva respuesta WordPress.');
$last_request = get_option('sait_test_last_request');
sait_client_assert_same(45, $last_request['timeout'], 'Timeout POST centralizado.');
sait_client_assert_same(false, $last_request['sslverify'], 'SSL POST compatible con instalaciones actuales.');
sait_client_assert_same(true, $last_request['blocking'], 'POST wait debe ser bloqueante.');

$options['SAITNube_URL'] = '';
update_option(SAIT_WOOCOMMERCE_Settings::OPTION_NAME, $options);
$configuration_error = $client->get('/api/v3/articulos/FIX-ART-001', false);
sait_client_assert_same('configuration_error', $configuration_error['error_code'], 'Configuracion incompleta.');
$options['SAITNube_URL'] = 'https://sait-api.invalid';
update_option(SAIT_WOOCOMMERCE_Settings::OPTION_NAME, $options);

$fake = new SAIT_Test_Fake_Client();
SAIT_WOOCOMMERCE()->set_sait_client($fake);
$legacy_fake = SAIT_UTILS::SAIT_GetNube('/api/v3/fake', false);
sait_client_assert_same('/api/v3/fake', $legacy_fake['result']['fake'], 'Adaptador GET usa cliente inyectado.');
$post_fake = SAIT_UTILS::SAIT_PostNube('/api/v3/fake', array(), true);
sait_client_assert_same(201, wp_remote_retrieve_response_code($post_fake), 'Adaptador POST usa cliente inyectado.');
SAIT_WOOCOMMERCE()->set_sait_client($client);

update_option(SAIT_WOOCOMMERCE_Settings::OPTION_NAME, $original_options);

echo "Cliente HTTP SAIT validado correctamente.\n";
