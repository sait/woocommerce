<?php

/**
 * Contrato inyectable del adaptador HTTP de SAIT Nube.
 */
interface SAIT_WOOCOMMERCE_SaitClientInterface
{
	/**
	 * @param string $uri Ruta relativa.
	 * @param bool   $retry Permite un segundo intento para errores transitorios.
	 * @return array<string,mixed>
	 */
	public function get($uri, $retry = true);

	/**
	 * @param string $uri Ruta relativa.
	 * @param bool   $retry Permite un segundo intento para errores transitorios.
	 * @return mixed|null
	 */
	public function get_legacy($uri, $retry = true);

	/**
	 * @param string       $uri Ruta relativa.
	 * @param object|array $body Datos que se serializan como JSON.
	 * @param bool         $wait Espera la respuesta cuando es true.
	 * @return array|WP_Error
	 */
	public function post($uri, $body, $wait = false);
}

/**
 * Unico adaptador de WordPress HTTP API para SAIT Nube.
 */
class SAIT_WOOCOMMERCE_SaitClient implements SAIT_WOOCOMMERCE_SaitClientInterface
{
	const GET_TIMEOUT = 5;
	const POST_TIMEOUT = 45;
	const RETRY_DELAY_MICROSECONDS = 500000;

	/** @var SAIT_WOOCOMMERCE_Settings */
	private $settings;

	/** @var SAIT_WOOCOMMERCE_Logger */
	private $logger;

	/**
	 * @param SAIT_WOOCOMMERCE_Settings $settings Configuracion compartida.
	 * @param SAIT_WOOCOMMERCE_Logger|null $logger Logger saneado.
	 */
	public function __construct(SAIT_WOOCOMMERCE_Settings $settings, $logger = null)
	{
		$this->settings = $settings;
		$this->logger = $logger !== null ? $logger : SAIT_WOOCOMMERCE()->logger();
	}

	/**
	 * Ejecuta GET y devuelve un resultado normalizado.
	 *
	 * Solo reintenta una vez por WP_Error o JSON invalido. Los estados HTTP no
	 * se reintentan automaticamente.
	 *
	 * @param string $uri Ruta relativa.
	 * @param bool   $retry Permite un segundo intento para errores transitorios.
	 * @return array<string,mixed>
	 */
	public function get($uri, $retry = true)
	{
		$configuration = $this->configuration();
		if (is_wp_error($configuration)) {
			return $this->normalize_error($configuration, 'configuration_error');
		}

		$response = wp_remote_get(
			$this->build_url($configuration['url'], $uri),
			array(
				'timeout'   => self::GET_TIMEOUT,
				'sslverify' => false,
				'blocking'  => true,
				'headers'   => $this->headers($configuration['api_key']),
			)
		);
		$normalized = $this->normalize_response($response);

		if ($retry && in_array($normalized['error_code'], array('transport_error', 'invalid_json'), true)) {
			$this->logger->warning(
				'Reintentando consulta GET a SAIT.',
				array(
					'operation'  => 'GET',
					'attempt'    => 2,
					'error_code' => $normalized['error_code'],
				)
			);
			usleep(self::RETRY_DELAY_MICROSECONDS);

			return $this->get($uri, false);
		}

		return $normalized;
	}

	/**
	 * Conserva el contrato historico: JSON decodificado o null.
	 *
	 * @param string $uri Ruta relativa.
	 * @param bool   $retry Permite el reintento historico de GET.
	 * @return mixed|null
	 */
	public function get_legacy($uri, $retry = true)
	{
		$response = $this->get($uri, $retry);

		return $response['ok'] ? $response['data'] : null;
	}

	/**
	 * Conserva la respuesta cruda de WordPress usada por pedidos y cotizaciones.
	 * POST no se reintenta automaticamente para evitar documentos duplicados.
	 *
	 * @param string       $uri Ruta relativa.
	 * @param object|array $body Datos que se serializan como JSON.
	 * @param bool         $wait Espera la respuesta cuando es true.
	 * @return array|WP_Error
	 */
	public function post($uri, $body, $wait = false)
	{
		$configuration = $this->configuration();
		if (is_wp_error($configuration)) {
			return $configuration;
		}

		return wp_remote_post(
			$this->build_url($configuration['url'], $uri),
			array(
				'method'      => 'POST',
				'timeout'     => self::POST_TIMEOUT,
				'redirection' => 5,
				'httpversion' => '1.0',
				'sslverify'   => false,
				'blocking'    => (bool) $wait,
				'headers'     => $this->headers($configuration['api_key']),
				'body'        => json_encode($body),
				'cookies'     => array(),
			)
		);
	}

	/**
	 * @return array<string,string>|WP_Error
	 */
	private function configuration()
	{
		$url = trim((string) $this->settings->get('SAITNube_URL', ''));
		$api_key = trim((string) $this->settings->get('SAITNube_APIKey', ''));

		if ($url === '' || $api_key === '') {
			return new WP_Error('sait_configuration_error', 'Falta configurar URL o API key.');
		}

		return array('url' => $url, 'api_key' => $api_key);
	}

	/**
	 * @param string $base_url URL configurada.
	 * @param string $uri Ruta relativa.
	 * @return string
	 */
	private function build_url($base_url, $uri)
	{
		return rtrim($base_url, '/') . '/' . ltrim((string) $uri, '/');
	}

	/**
	 * @param string $api_key Credencial configurada.
	 * @return array<string,string>
	 */
	private function headers($api_key)
	{
		return array(
			'X-sait-api-key' => $api_key,
			'Content-Type'   => 'application/json',
			'Accept'         => 'application/json',
		);
	}

	/**
	 * @param array|WP_Error $response Respuesta cruda de WordPress.
	 * @return array<string,mixed>
	 */
	private function normalize_response($response)
	{
		if (is_wp_error($response)) {
			return $this->normalize_error($response, 'transport_error');
		}

		$status_code = (int) wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);
		$json_valid = json_last_error() === JSON_ERROR_NONE;

		if ($status_code < 200 || $status_code >= 300) {
			return array(
				'ok'          => false,
				'status_code' => $status_code,
				'data'        => $json_valid ? $data : null,
				'result'      => null,
				'error_code'  => 'http_error',
				'mensaje'     => 'HTTP ' . $status_code . $this->format_response_detail($body),
			);
		}

		if (!$json_valid) {
			return array(
				'ok'          => false,
				'status_code' => $status_code,
				'data'        => null,
				'result'      => null,
				'error_code'  => 'invalid_json',
				'mensaje'     => 'HTTP ' . $status_code . ', JSON invalido: ' . json_last_error_msg() . '.',
			);
		}

		return array(
			'ok'          => true,
			'status_code' => $status_code,
			'data'        => $data,
			'result'      => is_array($data) && array_key_exists('result', $data) ? $data['result'] : null,
			'error_code'  => '',
			'mensaje'     => 'HTTP ' . $status_code . '.',
		);
	}

	/**
	 * @param WP_Error $error Error de WordPress.
	 * @param string   $error_code Clasificacion estable.
	 * @return array<string,mixed>
	 */
	private function normalize_error($error, $error_code)
	{
		return array(
			'ok'          => false,
			'status_code' => 0,
			'data'        => null,
			'result'      => null,
			'error_code'  => $error_code,
			'mensaje'     => $error->get_error_message(),
		);
	}

	/**
	 * @param string $body Cuerpo HTTP.
	 * @return string
	 */
	private function format_response_detail($body)
	{
		$body = trim(wp_strip_all_tags((string) $body));
		if ($body === '') {
			return '.';
		}

		if (strlen($body) > 180) {
			$body = substr($body, 0, 180) . '...';
		}

		return ': ' . $body;
	}
}
