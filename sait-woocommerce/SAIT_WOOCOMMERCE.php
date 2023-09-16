<?php
/**
 * @package SAIT_WOOCOMMERCE
 * @version 1.0.3
 */
/*
Plugin Name: SAIT WooCommerce
Description: Este plugin agrega un endpoint a wordpress para procesar eventos enviados desde SAIT.
Author: SAIT Software Administrativo
Version: 1.0.3
Author URI: http://sait.mx
*/

add_action( 'rest_api_init', function () {
	register_rest_route( 'saitplugin/v1', '/hello',
		array(
			'methods' => 'GET', 
			'callback' => 'SAIT_helloworld'
		)
	);
});

function SAIT_helloworld(){
	require_once plugin_dir_path( __FILE__ ) . 'includes/SAIT_WOOCOMMERCE-hello.php';
	return SAIT_WOOCOMMERCE_Hello::SAIT_helloworld();;
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'saitplugin/v1', '/saitevents',
		array(
			'methods' => 'POST', 
			'callback' => 'SAIT_procesEvents'
		)
	);
});


function SAIT_procesEvents($request){
	// Tomamos el request
	$xml = $request->get_body();
	// Carga el XML
	libxml_use_internal_errors(true);
  $oXml = simplexml_load_string((string)$xml);
  if (!$oXml){
		$res = new WP_REST_Response();
		$res->set_status(500);
		$res->set_data(json_encode(libxml_get_errors()));
		return $res;
	}
	require_once plugin_dir_path( __FILE__ ) . 'includes/SAIT_WOOCOMMERCE-process-events.php';
	return SAIT_WOOCOMMERCE_ProcessEvents::SAIT_processEvent($oXml);
}

/// Funciones Inicializacion

function activate_SAIT_WOOCOMMERCE() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/SAIT_WOOCOMMERCE-activator.php';
	SAIT_WOOCOMMERCE_Activator::SAIT_create_db();
}


register_activation_hook( __FILE__, 'activate_SAIT_WOOCOMMERCE' );


add_action( 'woocommerce_new_order', 'sendPedidoSAIT', 10, 2 );


function sendPedidoSAIT( $order_id, $order ){
	require_once plugin_dir_path( __FILE__ ) . 'includes/SAIT_WOOCOMMERCE-pedidos.php';
	SAIT_WOOCOMMERCE_Pedidos::SAIT_($order_id, $order );
}
