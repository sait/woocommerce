<?php
/*
Plugin Name: SAIT WooCommerce - Fysson
Description: Reglas de sincronización de productos SAIT específicas para Fysson.
Version: 1.0.0
Requires at least: 6.6
Requires PHP: 7.4
Requires Plugins: woocommerce, sait-woocommerce
WC requires at least: 9.3
Text Domain: sait-woocommerce-fysson
*/

defined('ABSPATH') || exit;

require_once __DIR__ . '/includes/class-sait-fysson-plugin.php';

add_action('plugins_loaded', static function () {
	SAIT_Fysson_Plugin::bootstrap();
}, 30);
