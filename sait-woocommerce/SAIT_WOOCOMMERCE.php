<?php
/**
 * @package SAIT_WOOCOMMERCE
 * @version 2.0.0
 */
/*
Plugin Name: SAIT WooCommerce
Description: Este plugin agrega un endpoint a wordpress para procesar eventos enviados desde SAIT.
Author: SAIT Software Administrativo
Version: 2.0.0
Author URI: http://sait.mx
Requires at least: 6.6
Requires PHP: 7.4
WC requires at least: 9.3
WC tested up to: 9.3
*/

if (!defined('SAIT_WOOCOMMERCE_VERSION')) {
	define('SAIT_WOOCOMMERCE_VERSION', '2.0.0');
}

if (!defined('SAIT_NUBE_NUMALM')) {
	define('SAIT_NUBE_NUMALM', '1');
}

if (!defined('SAIT_SERIE')) {
	define('SAIT_SERIE', 'WO');
}

add_action('before_woocommerce_init', static function () {
	if (class_exists('Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) {
		Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			__FILE__,
			true
		);
	}
});

require_once plugin_dir_path(__FILE__) . 'includes/SAIT_WOOCOMMERCE-lifecycle.php';
require_once plugin_dir_path(__FILE__) . 'includes/SAIT_WOOCOMMERCE-plugin.php';

/**
 * Devuelve la instancia compartida del plugin.
 *
 * @return SAIT_WOOCOMMERCE_Plugin
 */
function SAIT_WOOCOMMERCE()
{
	static $plugin = null;
	if ($plugin === null) {
		$plugin = new SAIT_WOOCOMMERCE_Plugin(__FILE__);
	}

	return $plugin;
}

/**
 * Activa y actualiza el esquema del plugin.
 *
 * @return void
 */
function activate_SAIT_WOOCOMMERCE()
{
	SAIT_WOOCOMMERCE_Lifecycle::activate();
}

/**
 * Desactiva el plugin sin eliminar datos.
 *
 * @return void
 */
function deactivate_SAIT_WOOCOMMERCE()
{
	SAIT_WOOCOMMERCE_Lifecycle::deactivate();
}

register_activation_hook(__FILE__, 'activate_SAIT_WOOCOMMERCE');
register_deactivation_hook(__FILE__, 'deactivate_SAIT_WOOCOMMERCE');

// Adaptadores globales conservados para compatibilidad con instalaciones existentes.

function SAIT_rest_controller()
{
	return SAIT_WOOCOMMERCE()->rest_controller();
}

function SAIT_register_rest_routes()
{
	SAIT_WOOCOMMERCE()->register_rest_routes();
}

function SAIT_helloworld()
{
	return SAIT_rest_controller()->hello();
}

function SAIT_procesEvents($request)
{
	return SAIT_rest_controller()->process_events($request);
}

function SAIT_reenviarPedido($request)
{
	return SAIT_rest_controller()->resend_order($request);
}

function sendOrderSAIT_payment($order_id)
{
	SAIT_WOOCOMMERCE()->send_order_payment($order_id);
}

function sendOrderSAIT_thankyou($order_id)
{
	SAIT_WOOCOMMERCE()->send_order_thankyou($order_id);
}

function registrar_estilos_scripts()
{
	SAIT_WOOCOMMERCE()->enqueue_assets();
}

SAIT_WOOCOMMERCE()->run();
