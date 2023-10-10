<?php

/**
 * 
 * @link       http://sait.mx
 * @since      1.0.3
 *
 * @package    SAIT_WOOCOMMERCE
 * @subpackage SAIT_WOOCOMMERCE/includes
 */

/**
 * Se ejecuta al procesar eventos
 *
 * En esta clase estan todas las funciones necesarias para procesar el XML
 * @since      1.0.3
 * @package    SAIT_WOOCOMMERCE
 * @subpackage SAIT_WOOCOMMERCE/includes
 * @author     Ali Moreno <ali@saitenlinea.com>
 */

 class SAIT_WOOCOMMERCE_ProcessEvents{

	// processEvent()
	//  Manda a procesar cada tipo de evento a su funcion correspondiente
	public static function SAIT_processEvent($oXml){
		$type = self::xml_attribute($oXml,"type");
		switch ($type) {
			case "MODART":
				$res = self::MODART($oXml);
				break;
			case "ACTEXISGBL":
				$res = self::ACTEXISGBL($oXml);
				break;
			case "ACTPRECIO":
				$res = self::ACTPRECIO($oXml);
				break;
			case "MODFAMILIA":
				$res = self::MODFAMILIA($oXml);
				break;
			case "MODDEPTO":
				$res = self::MODDEPTO($oXml);
				break;
			default:
				$res = new WP_REST_Response();
				$res->set_status(200);
				$res->set_data("OK");
				break;
		}
		return $res;
	}

	public static function MODART($oXml){
		// Cuando sea MODART y no esta marcado para ecommerce no procesar
		// Proceso de MODART
		$statusweb = self::xml_attribute($oXml->action[0]->flds[0],"statusweb");
		if ($statusweb == "0") {
			$res = new WP_REST_Response();
			$res->set_status(200);
			$res->set_data("OK");
			return $res;
		}

		$clave = self::getClaves("arts",self::xml_attribute($oXml->action[0]->keys[0],"numart"),null);
		$productflds = $oXml->action[0]->flds[0];
		if (isset($clave->wcid)) {
			// Actualizar producto
			// https://www.websitebuilderinsider.com/how-do-i-change-product-pricing-programmatically-in-woocommerce/
			$product = wc_get_product( $clave->wcid );
			if (self::xml_attribute($productflds,"preciopub")) {
				$product->set_regular_price( self::xml_attribute($productflds,"preciopub") );
			}
			$product->set_name( self::xml_attribute($productflds,"desc") );
			$clavefam = self::getClaves("familia",self::xml_attribute($oXml->action[0]->flds[0],"familia"),null);
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
			$product->set_name( self::xml_attribute($oXml->action[0]->flds[0],"desc") ); // product title
			$product->set_regular_price( self::xml_attribute($oXml->action[0]->flds[0],"preciopub")); // in current shop currency
			$product->set_SKU(self::xml_attribute($oXml->action[0]->keys[0],"numart"));
			$product->set_manage_stock(true);
			$clavefam = self::getClaves("familia",self::xml_attribute($oXml->action[0]->flds[0],"familia"),null);
			if (isset($clavefam ->wcid)) {
			$product->set_category_ids(array( $clavefam->wcid));
			}
			$product_id = $product->save();
			// Guardar en claves
			self::insertClaves("arts",self::xml_attribute($oXml->action[0]->keys[0],"numart"),$product_id);
			$res = new WP_REST_Response();
			$res->set_status(200);
			$res->set_data("ART ADD");
			return $res;
		}
	}

	public static function ACTEXISGBL($oXml){
		foreach ($oXml->action as $action) {
			$clave = self::getClaves("arts",self::xml_attribute($action->keys[0],"numart"),null);
			if (isset($clave->wcid)) {
				$product = wc_get_product( $clave->wcid );
				$product->set_stock_quantity(self::xml_attribute($action->flds[0],"existencia"));
				$product->save();
				$res = new WP_REST_Response();
				$res->set_status(200);
				$res->set_data("STOCK UPD");
				return $res;
			}
		}
	}

	public static function ACTPRECIO($oXml){
		$clave = self::getClaves("arts",self::xml_attribute($oXml->action[0]->keys[0],"numart"),null);
		if (isset($clave->wcid)) {
			$product = wc_get_product( $clave->wcid );
			$product->set_regular_price(self::xml_attribute($oXml->action[0]->flds[0],"preciopub"));
			$product->save();
			$res = new WP_REST_Response();
			$res->set_status(200);
			$res->set_data("PRICE UPD");
			return $res;
		}
	}

	public static function MODFAMILIA($oXml){
		
	// Agregar catecorias 
	// https://stackoverflow.com/questions/53460487/add-new-product-categories-programmatically-in-woocommerce
	// TODO: CEMCO Maneja los Deptos como padres de las familias
	// el numfamilia tiene 4 digitos siendo los primeros 2 el DPTO
	//  Se podria usar para agregar el padre automaticamente.
		$clave = self::getClaves("familia",self::xml_attribute($oXml->action[0]->keys[0],"numfam"),null);
		if (!isset($clave->wcid)) {
			$term_data = wp_insert_term(self::xml_attribute($oXml->action[0]->flds[0],"nomfam"), 'product_cat');
			if( is_wp_error( $term_data ) ) {
				echo $term_data->get_error_message();
				$res = new WP_REST_Response();
				$res->set_status(500);
				$res->set_data($term_data->get_error_message());
				return $res;
			}
			self::insertClaves("familia",self::xml_attribute($oXml->action[0]->keys[0],"numfam"),$term_data['term_id']);
			$res = new WP_REST_Response();
			$res->set_status(200);
			$res->set_data("ADD FAM");
			return $res;
		}
	}	

	public static function MODDEPTO($oXml){
		$clave = self::getClaves("deptos",self::xml_attribute($oXml->action[0]->flds[0],"numdep"),null);
		if (!isset($clave->wcid)) {
			$term_data = wp_insert_term(
					self::xml_attribute($oXml->action[0]->flds[0],"nomdep"), 
					'product_cat'
			);
			if( is_wp_error( $term_data ) ) {
				echo $term_data->get_error_message();
				$res = new WP_REST_Response();
				$res->set_status(500);
				$res->set_data($term_data->get_error_message());
				return $res;
			}
			self::insertClaves("deptos",self::xml_attribute($oXml->action[0]->flds[0],"numdep"),$term_data['term_id']);
			$res = new WP_REST_Response();
			$res->set_status(200);
			$res->set_data("ADD DEPTO");
			return $res;
		}
	}

	public static function insertClaves($tabla,$clave,$wcid){
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


	public static function getClaves($tabla,$clave,$wcid){
		global $wpdb;
		return $wpdb->get_row("SELECT * FROM wp_sait_claves WHERE tabla = '".$tabla."'and (clave = '".$clave."' or wcid ='" .$wcid."')", OBJECT);
	}

	//
	// UTILERIAS

	// xml_attribute()
	// te retorna el valor del atributo del nodo que le mandes 
	public static function xml_attribute($object, $attribute)
	{
			if(isset($object[$attribute]))
					return (string) $object[$attribute];
	}

}