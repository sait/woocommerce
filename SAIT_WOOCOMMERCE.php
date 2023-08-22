<?php
/**
 * @package SAIT_WOOCOMMERCE
 * @version 1.0.0
 */
/*
Plugin Name: SAIT WooCommerce
Description: Este plugin agrega un endpoint a wordpress para procesar eventos enviados desde SAIT.
Author: SAIT Software Administrativo
Version: 1.0.0
Author URI: http://sait.mx
*/

add_action( 'rest_api_init', function () {
	register_rest_route( 'saitplugin/v1', '/Hello/',
		array(
			'methods' => 'GET', 
			'callback' => 'helloworld'
		)
	);
});

function helloworld(){
	return "hello world!";
}


