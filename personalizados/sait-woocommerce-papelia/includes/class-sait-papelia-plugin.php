<?php

defined('ABSPATH') || exit;

/**
 * Inicializa las reglas específicas de Papelía sin modificar el plugin principal.
 */
final class SAIT_Papelia_Plugin
{
	const VERSION = '1.0.0';

	/** @var self|null */
	private static $instance;

	/** @var SAIT_Papelia_Stock */
	private $stock;

	/**
	 * @return self|null
	 */
	public static function bootstrap()
	{
		if (!function_exists('SAIT_WOOCOMMERCE') || !class_exists('WooCommerce')) {
			add_action('admin_notices', array(__CLASS__, 'dependency_notice'));
			return null;
		}

		if (self::$instance === null) {
			self::$instance = new self();
			self::$instance->register_hooks();
		}

		return self::$instance;
	}

	/** @return void */
	public static function dependency_notice()
	{
		if (!current_user_can('activate_plugins')) {
			return;
		}

		echo '<div class="notice notice-error"><p>';
		echo esc_html__('SAIT WooCommerce - Papelía requiere WooCommerce y SAIT WooCommerce activos.', 'sait-woocommerce-papelia');
		echo '</p></div>';
	}

	/** @return void */
	private function register_hooks()
	{
		require_once __DIR__ . '/class-sait-papelia-order-payload.php';
		require_once __DIR__ . '/class-sait-papelia-stock.php';
		require_once __DIR__ . '/class-sait-papelia-pickup.php';

		$this->stock = new SAIT_Papelia_Stock();

		$payload = new SAIT_Papelia_Order_Payload();
		$pickup = new SAIT_Papelia_Pickup($this->stock);
		$payload->register_hooks();
		$this->stock->register_hooks();
		$pickup->register_hooks();
	}
}
