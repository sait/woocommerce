<?php

/**
 * Logger saneado del plugin sobre el sistema de logs de WooCommerce.
 */
class SAIT_WOOCOMMERCE_Logger
{
	const SOURCE = 'sait-woocommerce';

	/** @var object */
	private $logger;

	/**
	 * @param object|null $logger Logger compatible con WC_Logger::log().
	 */
	public function __construct($logger = null)
	{
		$this->logger = $logger !== null ? $logger : wc_get_logger();
	}

	/**
	 * @param string $message Mensaje sin datos sensibles.
	 * @param array  $context Contexto operativo.
	 * @return void
	 */
	public function debug($message, $context = array())
	{
		$this->log('debug', $message, $context);
	}

	/**
	 * @param string $message Mensaje sin datos sensibles.
	 * @param array  $context Contexto operativo.
	 * @return void
	 */
	public function info($message, $context = array())
	{
		$this->log('info', $message, $context);
	}

	/**
	 * @param string $message Mensaje sin datos sensibles.
	 * @param array  $context Contexto operativo.
	 * @return void
	 */
	public function warning($message, $context = array())
	{
		$this->log('warning', $message, $context);
	}

	/**
	 * @param string $message Mensaje sin datos sensibles.
	 * @param array  $context Contexto operativo.
	 * @return void
	 */
	public function error($message, $context = array())
	{
		$this->log('error', $message, $context);
	}

	/**
	 * Registra una traza operativa en WooCommerce y, con WP_DEBUG_LOG activo,
	 * también en wp-content/debug.log.
	 *
	 * @param string $message Mensaje sin datos sensibles.
	 * @param array  $context Contexto operativo.
	 * @return void
	 */
	public function diagnostic($message, $context = array())
	{
		$this->log('debug', $message, $context);

		if (!defined('WP_DEBUG') || !WP_DEBUG || !defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG) {
			return;
		}

		$sanitized_context = $this->sanitize_context($context);
		$encoded_context = wp_json_encode($sanitized_context);
		$log_message = '[SAIT WooCommerce] ' . substr(sanitize_text_field((string) $message), 0, 500);
		if ($encoded_context !== false && $encoded_context !== '[]') {
			$log_message .= ' ' . $encoded_context;
		}

		error_log($log_message);
	}

	/**
	 * @param string $level Nivel WooCommerce.
	 * @param string $message Mensaje sin datos sensibles.
	 * @param array  $context Contexto operativo.
	 * @return void
	 */
	private function log($level, $message, $context)
	{
		$allowed_levels = array('debug', 'info', 'warning', 'error');
		if (!in_array($level, $allowed_levels, true)) {
			$level = 'info';
		}

		$sanitized_context = $this->sanitize_context($context);
		$sanitized_context['source'] = self::SOURCE;
		$message = substr(sanitize_text_field((string) $message), 0, 500);

		$this->logger->log($level, $message, $sanitized_context);
	}

	/**
	 * Acepta sólo identificadores operativos; descarta PII, credenciales y payloads.
	 *
	 * @param mixed $context Contexto recibido.
	 * @return array<string,int|string>
	 */
	private function sanitize_context($context)
	{
		if (!is_array($context)) {
			return array();
		}

		$integer_keys = array(
			'order_id',
			'attempt',
			'status_code',
			'item_count',
			'action_id',
			'duration_ms',
			'response_bytes',
			'delay_seconds',
		);
		$text_keys = array(
			'event',
			'sku',
			'operation',
			'error_code',
			'mode',
			'document_type',
			'endpoint',
			'payment_method',
			'scheduler',
			'stage',
			'response_message',
		);
		$sanitized = array();

		foreach ($integer_keys as $key) {
			if (isset($context[$key])) {
				$sanitized[$key] = absint($context[$key]);
			}
		}

		foreach ($text_keys as $key) {
			if (isset($context[$key]) && is_scalar($context[$key])) {
				$max_length = $key === 'response_message' ? 500 : 100;
				$sanitized[$key] = substr(sanitize_text_field((string) $context[$key]), 0, $max_length);
			}
		}

		return $sanitized;
	}
}
