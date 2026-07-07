<?php
/**
 * @package SAIT_WOOCOMMERCE
 * @version 1.1.19
 */
/*
Plugin Name: SAIT WooCommerce
Description: Este plugin agrega un endpoint a wordpress para procesar eventos enviados desde SAIT.
Author: SAIT Software Administrativo
Version: 1.1.19
Author URI: http://sait.mx
*/

include plugin_dir_path( __FILE__ ) . 'includes/SAIT_WOOCOMMERCE-options.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/SAIT_UTILS.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/SAIT_WOOCOMMERCE-personalizado.php';

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
			'callback' => 'SAIT_helloworld',
			'permission_callback' => '__return_true', 
		)
	);
});



add_action( 'rest_api_init', function () {
	register_rest_route( 'saitplugin/v1', '/saitevents',
		array(
			'methods' => 'POST', 
			'callback' => 'SAIT_procesEvents',
			'permission_callback' => '__return_true', 
		)
	);
});



add_action( 'rest_api_init', function () {
	register_rest_route( 'saitplugin/v1', '/reenviar-pedido-sait/(?P<idpedido>\d+)',
		array(
			'methods' => 'POST', 
			'callback' => 'SAIT_reenviarPedido',
			'permission_callback' => '__return_true', 
		)
	);

	register_rest_route( 'saitplugin/v1', '/testpedido/(?P<idpedido>\d+)',
		array(
			'methods' => 'GET', 
			'callback' => 'SAIT_reenviarPedido',
			'permission_callback' => '__return_true', 
		)
	);
});


// Callbacks del router


function SAIT_helloworld(){
	require_once plugin_dir_path( __FILE__ ) . 'includes/SAIT_WOOCOMMERCE-hello.php';
	return SAIT_WOOCOMMERCE_Hello::SAIT_helloworld();;
}

/**
 * Procesa eventos entrantes enviados por SAIT al endpoint REST.
 *
 * @param WP_REST_Request $request Peticion con header x-AccessToken y XML en el body.
 * @return WP_REST_Response Respuesta del procesador de eventos o error de validacion/XML.
 *
 * Acciones que realiza: valida el token configurado, parsea XML y puede crear/actualizar productos,
 * categorias, clientes, existencias, precios u opciones segun el tipo de evento recibido.
 */
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

/**
 * Reenvia manualmente un pedido de WooCommerce hacia SAIT.
 *
 * @param WP_REST_Request $request Peticion REST con parametro idpedido.
 * @return WP_REST_Response Resultado del intento de reenvio.
 *
 * Acciones que realiza: envia el pedido/cotizacion a la API SAIT y guarda metadatos del ultimo intento.
 */
function SAIT_reenviarPedido($request){
	require_once plugin_dir_path( __FILE__ ) . 'includes/SAIT_WOOCOMMERCE-orders.php';
	$id_pedido = absint($request['idpedido']);
	return SAIT_WOOCOMMERCE_Orders::SAIT_reenviarPedido($id_pedido);;
}



// Funciones para procesar eventos disparados por wordpress


// Acccion que se ejecuta al hacer pagos con tarjeta o paypal
add_action( 'woocommerce_payment_complete', 'sendOrderSAIT_payment', 10, 2 );
// Acccion que se ejecuta al hacer pedidos sin pago
add_action( 'woocommerce_thankyou', 'sendOrderSAIT_thankyou', 10, 2 );


/**
 * Envia automaticamente a SAIT una orden marcada como pagada.
 *
 * @param int $order_id ID de la orden WooCommerce.
 * @return void
 *
 * Acciones que realiza: dispara un envio idempotente con formapago 1.
 */
function sendOrderSAIT_payment( $order_id ){
	require_once plugin_dir_path( __FILE__ ) . 'includes/SAIT_WOOCOMMERCE-orders.php';
	SAIT_WOOCOMMERCE_Orders::SAIT_sendOrder($order_id,"1");
}


/**
 * Envia automaticamente a SAIT una orden creada sin pago confirmado.
 *
 * @param int $order_id ID de la orden WooCommerce.
 * @return void
 *
 * Acciones que realiza: dispara un envio idempotente con formapago 2.
 */
function sendOrderSAIT_thankyou( $order_id ){
	require_once plugin_dir_path( __FILE__ ) . 'includes/SAIT_WOOCOMMERCE-orders.php';
	SAIT_WOOCOMMERCE_Orders::SAIT_sendOrder($order_id,"2");
}

/**
 * Registra assets frontend para seleccion de sucursal.
 *
 * @return void
 *
 * Acciones que realiza: encola CSS/JS cuando la opcion de sucursal esta activa y mantiene
 * el handle legacy modal-script para scripts personalizados de clientes.
 */
function registrar_estilos_scripts() {
    // Cargar solo si es frontend y no en admin
		$SAIT_options = get_option('opciones_sait');
		$Sucursal_activo = isset($SAIT_options['SAITNube_Sucursal_enabled']) && $SAIT_options['SAITNube_Sucursal_enabled'] === '1';
	
		if (!$Sucursal_activo) {
			return ;
		}
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
            'sait-modal-script', 
            plugin_dir_url(__FILE__) . 'assets/js/modal.js', 
            array('jquery'), 
            '1.0', 
            true
        );

		wp_enqueue_script(
			'sait-personalizado-script',
			plugins_url('../assets/js/personalizado.js', __FILE__),
			array('jquery'),
			'1.0',
			true
		);

		wp_register_script(
			'modal-script',
			false,
			array('sait-personalizado-script'),
			'1.0',
			true
		);
		wp_enqueue_script('modal-script');
	        
        wp_localize_script('sait-modal-script', 'sait_woocommerce_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sait-woocommerce_nonce')
        ));
    }
}
add_action('wp_enqueue_scripts', 'registrar_estilos_scripts');
