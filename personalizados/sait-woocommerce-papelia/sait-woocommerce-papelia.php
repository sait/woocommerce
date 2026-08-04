<?php
/*
Plugin Name: SAIT WooCommerce - Papelía
Description: Reglas de checkout, sucursales y payload SAIT específicas para Papelía.
Version: 1.0.0
Requires at least: 6.6
Requires PHP: 7.4
Requires Plugins: woocommerce, sait-woocommerce
WC requires at least: 9.3
Text Domain: sait-woocommerce-papelia
*/

defined('ABSPATH') || exit;

require_once __DIR__ . '/includes/class-sait-papelia-plugin.php';

add_action('plugins_loaded', static function () {
	SAIT_Papelia_Plugin::bootstrap();
}, 30);
