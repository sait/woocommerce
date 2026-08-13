<?php

defined('ABSPATH') || exit;

/**
 * Conserva las reglas históricas de sincronización de artículos de Fysson.
 */
final class SAIT_Fysson_Plugin
{
	const VERSION = '1.0.0';

	/** @var self|null */
	private static $instance;

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
		echo esc_html__('SAIT WooCommerce - Fysson requiere WooCommerce y SAIT WooCommerce activos.', 'sait-woocommerce-fysson');
		echo '</p></div>';
	}

	/**
	 * No adopta productos preexistentes que compartan el SKU recibido por MODART.
	 *
	 * @return string
	 */
	public function ignore_existing_sku()
	{
		return 'ignore';
	}

	/** @return void */
	private function register_hooks()
	{
		add_filter(
			'sait_woocommerce_modart_existing_sku_mode',
			array($this, 'ignore_existing_sku'),
			10,
			4
		);
		add_filter('sait_woocommerce_modart_sync_category', '__return_false');
		add_filter('sait_woocommerce_modart_sync_model', '__return_false');
	}
}
