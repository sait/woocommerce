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

		$clave = getClaves("arts",xml_attribute($oXml->action[0]->keys[0],"numart"),null);
		$productflds = $oXml->action[0]->flds[0];
		if (isset($clave->wcid)) {
	 		// Actualizar producto
			// https://www.websitebuilderinsider.com/how-do-i-change-product-pricing-programmatically-in-woocommerce/
			$product = wc_get_product( $clave->wcid );
			if (xml_attribute($productflds,"preciopub")) {
				$product->set_regular_price( xml_attribute($productflds,"preciopub") );
			}
			$product->set_name( xml_attribute($productflds,"desc") );
			$clavefam = getClaves("familia",xml_attribute($oXml->action[0]->flds[0],"familia"),null);
			if (isset($clavefam->wcid)) {
				$product->set_category_ids(array( $clavefam->wcid));
			}
			$product->save();
			$res = new WP_REST_Response();
			$res->set_status(200);
			$res->set_data("ART UPD");
			return $res;
		}else{
			// Registrar producto
			// https://rudrastyh.com/woocommerce/create-product-programmatically.html
			$product = new WC_Product_Simple();
			$product->set_name( xml_attribute($oXml->action[0]->flds[0],"desc") ); // product title
			$product->set_regular_price( xml_attribute($oXml->action[0]->flds[0],"preciopub")); // in current shop currency
			$product->set_SKU(xml_attribute($oXml->action[0]->keys[0],"numart"));
			$product->set_manage_stock(true);
			$clavefam = getClaves("familia",xml_attribute($oXml->action[0]->flds[0],"familia"),null);
			if (isset($clavefam ->wcid)) {
			$product->set_category_ids(array( $clavefam->wcid));
			}
			$product_id = $product->save();
			// Guardar en claves
			insertClaves("arts",xml_attribute($oXml->action[0]->keys[0],"numart"),$product_id);
			$res = new WP_REST_Response();
			$res->set_status(200);
			$res->set_data("ART ADD");
			return $res;
		}
		
	}

	if ($type == "ACTEXISGBL"){
		foreach ($oXml->action as $action) {
			$clave = getClaves("arts",xml_attribute($action->keys[0],"numart"),null);
			if (isset($clave->wcid)) {
				$product = wc_get_product( $clave->wcid );
				$product->set_stock_quantity(xml_attribute($action->flds[0],"existencia"));
				$product->save();
				$res = new WP_REST_Response();
				$res->set_status(200);
				$res->set_data("STOCK UPD");
				return $res;
			}
		}  
	}

	if ($type == "ACTPRECIO"){
			$clave = getClaves("arts",xml_attribute($oXml->action[0]->keys[0],"numart"),null);
			if (isset($clave->wcid)) {
				$product = wc_get_product( $clave->wcid );
				$product->set_regular_price(xml_attribute($oXml->action[0]->flds[0],"preciopub"));
				$product->save();
				$res = new WP_REST_Response();
				$res->set_status(200);
				$res->set_data("PRICE UPD");
				return $res;
			}
	}


	// Agregar catecorias 
	// https://stackoverflow.com/questions/53460487/add-new-product-categories-programmatically-in-woocommerce
	// TODO: CEMCO Maneja los Deptos como padres de las familias
	// el numfamilia tiene 4 digitos siendo los primeros 2 el DPTO
	//  Se podria usar para agregar el padre automaticamente.
	if ($type == "MODFAMILIA"){
		$clave = getClaves("familia",xml_attribute($oXml->action[0]->keys[0],"numfam"),null);
		if (!isset($clave->wcid)) {
			$term_data = wp_insert_term(
					xml_attribute($oXml->action[0]->flds[0],"nomfam"), 
					'product_cat'
			);
			if( is_wp_error( $term_data ) ) {
				echo $term_data->get_error_message();
				$res = new WP_REST_Response();
				$res->set_status(500);
				$res->set_data($term_data->get_error_message());
				return $res;
			}
			insertClaves("familia",xml_attribute($oXml->action[0]->keys[0],"numfam"),$term_data['term_id']);
			$res = new WP_REST_Response();
			$res->set_status(200);
			$res->set_data("ADD FAM");
			return $res;
		}
	}

	if ($type == "MODDEPTO"){
		$clave = getClaves("deptos",xml_attribute($oXml->action[0]->flds[0],"numdep"),null);
		if (!isset($clave->wcid)) {
			$term_data = wp_insert_term(
					xml_attribute($oXml->action[0]->flds[0],"nomdep"), 
					'product_cat'
			);
			if( is_wp_error( $term_data ) ) {
				echo $term_data->get_error_message();
				$res = new WP_REST_Response();
				$res->set_status(500);
				$res->set_data($term_data->get_error_message());
				return $res;
			}
			insertClaves("deptos",xml_attribute($oXml->action[0]->flds[0],"numdep"),$term_data['term_id']);
			$res = new WP_REST_Response();
			$res->set_status(200);
			$res->set_data("ADD DEPTO");
			return $res;
		}
	}

	$res = new WP_REST_Response();
	$res->set_status(200);
	$res->set_data("OK");
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


function getClaves($tabla,$clave,$wcid){
	global $wpdb;
	return $wpdb->get_row("SELECT * FROM wp_sait_claves WHERE tabla = '".$tabla."'and (clave = '".$clave."' or wcid ='" .$wcid."')", OBJECT);
}