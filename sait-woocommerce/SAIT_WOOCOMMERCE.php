<?php
/**
 * @package SAIT_WOOCOMMERCE
 * @version 1.1.8
 */
/*
Plugin Name: SAIT WooCommerce
Description: Este plugin agrega un endpoint a wordpress para procesar eventos enviados desde SAIT.
Author: SAIT Software Administrativo
Version: 1.1.8
Author URI: http://sait.mx
*/

include plugin_dir_path( __FILE__ ) . 'includes/SAIT_WOOCOMMERCE-options.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/SAIT_UTILS.php';

// Incluir archivos necesarios
require_once plugin_dir_path(__FILE__) . 'includes/SAIT_WOOCOMMERCE-cart.php';


// Variables globales del plugin

if ( !defined( 'SAIT_NUBE_NUMALM' ) ) {
    define( 'SAIT_NUBE_NUMALM', '1' );
}	

if ( !defined( 'SAIT_SERIE' ) ) {
    define( 'SAIT_SERIE', 'WO' );
}	


// Funciones Inicializacion

function activate_SAIT_WOOCOMMERCE() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/SAIT_WOOCOMMERCE-activator.php';
	SAIT_WOOCOMMERCE_Activator::SAIT_create_db();
}


register_activation_hook( __FILE__, 'activate_SAIT_WOOCOMMERCE' );



// Router del Plugin

add_action( 'rest_api_init', function () {
	register_rest_route( 'saitplugin/v1', '/hello',
		array(
			'methods' => 'GET', 
			'callback' => 'SAIT_helloworld'
		)
	);
});



add_action( 'rest_api_init', function () {
	register_rest_route( 'saitplugin/v1', '/saitevents',
		array(
			'methods' => 'POST', 
			'callback' => 'SAIT_procesEvents'
		)
	);
});



add_action( 'rest_api_init', function () {
	register_rest_route( 'saitplugin/v1', '/testpedido',
		array(
			'methods' => 'GET', 
			'callback' => 'SAIT_testPedido'
		)
	);
});


// Callbacks del router


function SAIT_helloworld(){
	require_once plugin_dir_path( __FILE__ ) . 'includes/SAIT_WOOCOMMERCE-hello.php';
	return SAIT_WOOCOMMERCE_Hello::SAIT_helloworld();;
}

function SAIT_procesEvents($request){
	$AccessToken = $request->get_header('x-AccessToken');
	$SAIT_options=get_option( 'opciones_sait' );
	$SAITAccessToken = $SAIT_options['SAITNube_AccessToken'];
	if ($AccessToken != $SAITAccessToken ){
		$res = new WP_REST_Response();
		$res->set_status(401);
		$res->set_data("Bad token");
		return $res;
	}
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

function SAIT_testPedido(){
	require_once plugin_dir_path( __FILE__ ) . 'includes/SAIT_WOOCOMMERCE-orders.php';
	return SAIT_WOOCOMMERCE_Orders::SAIT_sendPedidoTest();;
}



// Funciones para procesar eventos disparados por wordpress


// Acccion que se ejecuta al hacer pagos con tarjeta o paypal
add_action( 'woocommerce_payment_complete', 'sendOrderSAIT_payment', 10, 2 );
// Acccion que se ejecuta al hacer pedidos sin pago
add_action( 'woocommerce_thankyou', 'sendOrderSAIT_thankyou', 10, 2 );


// Order Pagada enviar formapago 1
function sendOrderSAIT_payment( $order_id ){
	require_once plugin_dir_path( __FILE__ ) . 'includes/SAIT_WOOCOMMERCE-orders.php';
	SAIT_WOOCOMMERCE_Orders::SAIT_sendOrder($order_id,"1");
}


// Orden sin pago enviar formapago 2
function sendOrderSAIT_thankyou( $order_id ){
	require_once plugin_dir_path( __FILE__ ) . 'includes/SAIT_WOOCOMMERCE-orders.php';
	SAIT_WOOCOMMERCE_Orders::SAIT_sendOrder($order_id,"2");
}

// Registrar scripts y estilos
/* Agregar estilos y scripts */
function registrar_estilos_scripts() {
    // Cargar solo si es frontend y no en admin
    if (!is_admin()) {
		wp_enqueue_style(
			'font-awesome',
			'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
			array(),
			'6.4.0'
		);
        wp_enqueue_style('modal-style', plugin_dir_url(__FILE__) . 'assets/css/modal.css');
        
        // Cargar el script en el footer con alta prioridad
        wp_enqueue_script(
            'modal-script', 
            plugin_dir_url(__FILE__) . 'assets/js/modal.js', 
            array('jquery'), 
            '1.0', 
            true
        );
        
        wp_localize_script('modal-script', 'sait_woocommerce_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sait-woocommerce_nonce')
        ));
    }
}
// Usar wp_enqueue_scripts con prioridad alta (20)
add_action('wp_enqueue_scripts', 'registrar_estilos_scripts', 20);


