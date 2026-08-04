<?php
/**
 * Plugin Name: SAIT API Mock For Tests
 * Description: Intercepta las llamadas de SAIT WooCommerce en el entorno Docker.
 */

if (getenv('SAIT_TEST_API_MOCK') !== '1') {
	return;
}

// Evita que las pruebas de clientes intenten entregar correos reales.
add_filter('pre_wp_mail', '__return_true');

/**
 * Construye una respuesta compatible con la WordPress HTTP API.
 *
 * @param mixed $body Cuerpo que sera serializado como JSON.
 * @param int   $code Estado HTTP.
 * @return array
 */
function sait_test_http_response($body, $code = 200)
{
	return array(
		'headers'  => array('content-type' => 'application/json'),
		'body'     => wp_json_encode($body),
		'response' => array(
			'code'    => $code,
			'message' => $code === 201 ? 'Created' : 'OK',
		),
		'cookies'  => array(),
		'filename' => null,
	);
}

/**
 * Construye una respuesta con cuerpo literal para probar JSON invalido.
 *
 * @param string $body Cuerpo sin serializar.
 * @param int    $code Estado HTTP.
 * @return array
 */
function sait_test_raw_http_response($body, $code = 200)
{
	$response = sait_test_http_response(null, $code);
	$response['body'] = $body;

	return $response;
}

/**
 * Lee el catalogo de respuestas simuladas.
 *
 * @return array
 */
function sait_test_api_fixtures()
{
	static $fixtures = null;

	if ($fixtures !== null) {
		return $fixtures;
	}

	$path = WP_CONTENT_DIR . '/sait-test-fixtures/api/responses.json';
	$contents = is_readable($path) ? file_get_contents($path) : false;
	$decoded = $contents !== false ? json_decode($contents, true) : null;
	$fixtures = is_array($decoded) ? $decoded : array();

	return $fixtures;
}

/**
 * Intercepta exclusivamente el host falso configurado para pruebas.
 *
 * @param false|array|WP_Error $preempt Respuesta previa.
 * @param array                $args Argumentos HTTP.
 * @param string               $url URL solicitada.
 * @return false|array|WP_Error
 */
function sait_test_intercept_api_request($preempt, $args, $url)
{
	$parts = wp_parse_url($url);
	if (!is_array($parts) || !isset($parts['host']) || $parts['host'] !== 'sait-api.invalid') {
		return $preempt;
	}

	$fixtures = sait_test_api_fixtures();
	$path = isset($parts['path']) ? $parts['path'] : '/';
	$query = array();
	if (isset($parts['query'])) {
		parse_str($parts['query'], $query);
	}

	$method = isset($args['method']) ? strtoupper($args['method']) : 'GET';
	$body = isset($args['body']) ? json_decode($args['body'], true) : null;
	$counts = get_option('sait_test_request_counts', array());
	$count_key = $method . ' ' . $path;
	$counts[$count_key] = isset($counts[$count_key]) ? $counts[$count_key] + 1 : 1;
	update_option('sait_test_request_counts', $counts, false);
	$headers = isset($args['headers']) && is_array($args['headers']) ? $args['headers'] : array();
	update_option(
		'sait_test_last_request',
		array(
			'method'      => $method,
			'path'        => $path,
			'query'       => $query,
			'body'        => $body,
			'timeout'     => isset($args['timeout']) ? $args['timeout'] : null,
			'sslverify'   => isset($args['sslverify']) ? $args['sslverify'] : null,
			'blocking'    => isset($args['blocking']) ? $args['blocking'] : null,
			'has_api_key' => !empty($headers['X-sait-api-key']),
		),
		false
	);

	if ($method === 'POST' && in_array($path, array('/api/v3/pedidos', '/api/v3/cotizaciones'), true)) {
		return sait_test_http_response(array('result' => 'OK'), 201);
	}

	if ($method !== 'GET') {
		return new WP_Error('sait_test_method_not_supported', 'Metodo no simulado: ' . $method);
	}

	if ($path === '/api/v3/test-result-null') {
		return sait_test_http_response(array('result' => null));
	}

	if ($path === '/api/v3/test-invalid-json') {
		return sait_test_raw_http_response('{invalido');
	}

	if ($path === '/api/v3/test-http-error') {
		return sait_test_http_response(array('error' => 'servicio no disponible'), 503);
	}

	if ($path === '/api/v3/test-wp-error') {
		return new WP_Error('sait_test_transport_error', 'Fallo de transporte simulado.');
	}

	if ($path === '/api/v3/articulos/FIX-ART-001' && isset($fixtures['articulo_pesos'])) {
		return sait_test_http_response($fixtures['articulo_pesos']);
	}

	if ($path === '/api/v3/articulos/FIX-USD-001' && isset($fixtures['articulo_dolares'])) {
		return sait_test_http_response($fixtures['articulo_dolares']);
	}

	if ($path === '/api/v3/articulos' && isset($query['divisa']) && $query['divisa'] === 'D' && isset($fixtures['articulos_dolares'])) {
		return sait_test_http_response($fixtures['articulos_dolares']);
	}

	if ($path === '/api/v3/existencias/FIX-ART-001' && isset($fixtures['existencias'])) {
		return sait_test_http_response($fixtures['existencias']);
	}

	if ($path === '/api/v3/clientes' && isset($query['emailtw'])) {
		if ($query['emailtw'] === 'normal.fixture@example.test') {
			$key = 'cliente_normal';
		} elseif ($query['emailtw'] === 'eventual.fixture@example.test') {
			$key = 'cliente_eventual';
		} else {
			$key = 'sin_resultados';
		}
		return sait_test_http_response(isset($fixtures[$key]) ? $fixtures[$key] : array('result' => array()));
	}

	if ($path === '/api/v3/almacenes' && isset($fixtures['almacenes'])) {
		return sait_test_http_response($fixtures['almacenes']);
	}

	if ($path === '/api/v3/calcularprecios' && isset($fixtures['calcular_precios'])) {
		return sait_test_http_response($fixtures['calcular_precios']);
	}

	return new WP_Error(
		'sait_test_unhandled_request',
		'Ruta de SAIT no simulada: ' . $method . ' ' . $path
	);
}
add_filter('pre_http_request', 'sait_test_intercept_api_request', 10, 3);
