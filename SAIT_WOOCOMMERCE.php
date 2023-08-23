<?php
/**
 * @package SAIT_WOOCOMMERCE
 * @version 1.0.1
 */
/*
Plugin Name: SAIT WooCommerce
Description: Este plugin agrega un endpoint a wordpress para procesar eventos enviados desde SAIT.
Author: SAIT Software Administrativo
Version: 1.0.1
Author URI: http://sait.mx
*/

add_action( 'rest_api_init', function () {
	register_rest_route( 'saitplugin/v1', '/hello',
		array(
			'methods' => 'GET', 
			'callback' => 'helloworld'
		)
	);
});

function helloworld(){
	return "hello world!";
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'saitplugin/v1', '/saitevents',
		array(
			'methods' => 'POST', 
			'callback' => 'procesEvents'
		)
	);
});


function procesEvents($request){
	// tomamos el request
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


	$type = xml_attribute($oXml,"type");


	// Cuando sea MODART y no esta marcado para ecommerce no procesar
	// Proceso de MODART
	if ($type == "MODART"){
		$statusweb = xml_attribute($oXml->action[0]->flds[0],"statusweb");
		if ($statusweb == "0") {
			$res = new WP_REST_Response();
			$res->set_status(200);
			$res->set_data("OK");
			return $res;
		}
		// Guardar producto
		// TODO: Checar si ya existe y hacer update
		// https://rudrastyh.com/woocommerce/create-product-programmatically.html
			$product = new WC_Product_Simple();
			$product->set_name( xml_attribute($oXml->action[0]->flds[0],"desc") ); // product title
			$product->set_regular_price( xml_attribute($oXml->action[0]->flds[0],"preciopub")); // in current shop currency
			$product_id = $product->save();
			// Guardar en claves
			insertClaves("arts",xml_attribute($oXml->action[0]->keys[0],"numart"),$product_id);
		// Actualizar producto
		// https://www.websitebuilderinsider.com/how-do-i-change-product-pricing-programmatically-in-woocommerce/
		// $product = wc_get_product( $product_id );
		// $product->set_price( 10 );
		// $product->save();

	}

	$res = new WP_REST_Response();
	$res->set_status(200);
	$res->set_data(xml_attribute($oXml->action[0]->flds[0],"desc"));
	return $res;



}


// xml_attribute()
// te retorna el valor del atributo del nodo que le mandes 
function xml_attribute($object, $attribute)
{
    if(isset($object[$attribute]))
        return (string) $object[$attribute];
}






/// Funciones Inicializacion


// Crear Tabla intermedia 
//  https://wpmudev.com/blog/creating-database-tables-for-plugins/
//
register_activation_hook( __FILE__, 'SAIT_create_db' );


function SAIT_create_db() {
	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();
	$table_name = $wpdb->prefix . 'sait_claves';

	$sql = "CREATE TABLE $table_name (
		`id` INT NOT NULL AUTO_INCREMENT,
		`tabla` VARCHAR(20),
		`clave` VARCHAR(20),
		`wcid` INT(12),
		PRIMARY KEY (`id`)
	) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
}

function insertClaves($tabla,$clave,$wcid){
	global $wpdb;
	$wpdb->insert( 
			'wp_sait_claves', 
			array( 
					'tabla' => $tabla,
					'clave' => $clave,
					'wcid'  => $wcid
			)
	);
}
