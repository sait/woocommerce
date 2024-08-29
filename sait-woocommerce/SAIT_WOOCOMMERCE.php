<?php
/**
 * @package SAIT_WOOCOMMERCE
 * @version 99.0.23
 */
/*
Plugin Name: SAIT WooCommerce
Description: Este plugin agrega un endpoint a wordpress para procesar eventos enviados desde SAIT.
Author: SAIT Software Administrativo
Version: 99.0.23
Author URI: http://sait.mx
*/

include plugin_dir_path( __FILE__ ) . 'includes/SAIT_WOOCOMMERCE-options.php';

if ( !defined( 'SAIT_NUBE_NUMALM' ) ) {
    define( 'SAIT_NUBE_NUMALM', '1' );
}	

if ( !defined( 'SAIT_SERIE' ) ) {
    define( 'SAIT_SERIE', 'WO' );
}	

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

  $xml = $request->get_body();
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

add_action( 'rest_api_init', function () {
	register_rest_route( 'saitplugin/v1', '/testpedido',
		array(
			'methods' => 'GET', 
			'callback' => 'SAIT_testPedido'
		)
	);
});

function SAIT_testPedido(){
	require_once plugin_dir_path( __FILE__ ) . 'includes/SAIT_WOOCOMMERCE-orders.php';
	return SAIT_WOOCOMMERCE_Pedidos::SAIT_sendPedidoTest();;
}

/// Funciones Inicializacion

function activate_SAIT_WOOCOMMERCE() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/SAIT_WOOCOMMERCE-activator.php';
	SAIT_WOOCOMMERCE_Activator::SAIT_create_db();
}


register_activation_hook( __FILE__, 'activate_SAIT_WOOCOMMERCE' );

add_action( 'woocommerce_thankyou', 'sendOrderSAIT_thankyou', 10, 2 );


function sendOrderSAIT_thankyou( $order_id ){
	require_once plugin_dir_path( __FILE__ ) . 'includes/SAIT_WOOCOMMERCE-orders.php';
	SAIT_WOOCOMMERCE_Orders::SAIT_sendOrderThankyou($order_id );
}

