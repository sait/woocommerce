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
			case "MODLINEA":
				$res = self::MODLINEA($oXml);
				break;
			case "ACTEXIST":
				$res = self::ACTEXIST($oXml);
				break;
			case "ACTTC":
			 	$res = self::ACTTC($oXml);
			 	break;
			case "MODCLI":
				$res = self::MODCLI($oXml);
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
		// Proceso de MODART

		// Saco la clave del articulo
		$clave = self::getClaves("arts",trim(self::xml_attribute($oXml->action[0]->keys[0],"numart")),null);

		// Si viene con statusweb=0 salir
		$statusweb = self::xml_attribute($oXml->action[0]->flds[0],"statusweb");
		if ($statusweb == "0") {
			// Si existe lo manda a la papelera
			if (isset($clave->wcid)){
				wp_trash_post($clave->wcid);
			}
			$res = new WP_REST_Response();
			$res->set_status(200);
			$res->set_data("OK");
			return $res;
		}
		
		// Toma los campos del articulo 
		$productflds = $oXml->action[0]->flds[0];

		// Si ya existe el articulo en la tabla de SAIT solo actualizar datos
		if (isset($clave->wcid)) {
			// Revisar si el articulo esta en WC o fue eliminado
			$product = wc_get_product( $clave->wcid );
			if ($product===false) {
				// Producto ya no existe borrarlo de tabla SAIT
				// para evitar conflictos
				self::deleteClaves($clave->id);
				$res = new WP_REST_Response();
				$res->set_status(200);
				$res->set_data("ART NO EXISTE");
				return $res;
			}
			// wp_untrash_post si el id esta en la papelera sacarlo
			wp_untrash_post($clave->wcid);

			// Actualizar producto
			$product = wc_get_product( $clave->wcid );
			$product->set_name( trim(self::xml_attribute($productflds,"desc")) );
			$clavelinea = self::getClaves("familia",trim(self::xml_attribute($oXml->action[0]->flds[0],"familia")),null);
			if (isset($clavelinea->wcid)) {
				$product->set_category_ids(array( $clavelinea->wcid));
			}
			$product->save();
			$res = new WP_REST_Response();
			$res->set_status(200);
			$res->set_data("ART UPD");
			return $res;
		}

		// Registrar nuevo producto
		$product = new WC_Product_Simple();
		$product->set_name( trim(self::xml_attribute($oXml->action[0]->flds[0],"desc")) ); 
		$product->set_SKU(trim(self::xml_attribute($oXml->action[0]->keys[0],"numart")));
		$product->set_status("draft");
		$product->set_manage_stock(true);
		$clavelinea = self::getClaves("familia",trim(self::xml_attribute($oXml->action[0]->flds[0],"familia")),null);
		if (isset($clavelinea->wcid)) {
			$product->set_category_ids(array( $clavelinea->wcid));
		}
		$product_id = $product->save();
		// Guardar en claves
		self::insertClaves("arts",trim(self::xml_attribute($oXml->action[0]->keys[0],"numart")),$product_id);
		$res = new WP_REST_Response();
		$res->set_status(200);
		$res->set_data("ART ADD");
		return $res;

	}

	public static function ACTEXISGBL($oXml){
		$SAIT_options=get_option( 'opciones_sait' );
		$NumAlm = $SAIT_options['SAITNube_NumAlm'];
		if (isset($NumAlm) && !is_null($NumAlm)) {
			$res = new WP_REST_Response();
			$res->set_status(200);
			$res->set_data("STOCK ERR ACTEXISGBL");
			return $res;
		}
		foreach ($oXml->action as $action) {
			$clave = self::getClaves("arts",trim(self::xml_attribute($action->keys[0],"numart")),null);
			if (isset($clave->wcid)) {
				$product = wc_get_product( $clave->wcid );
				if ($product===false) {
					$res = new WP_REST_Response();
					$res->set_status(200);
					$res->set_data("ART NO EXISTE");
					return $res;
				}
				$product->set_stock_quantity(self::xml_attribute($action->flds[0],"existencia"));
				$product->save();
			}
		}
		$res = new WP_REST_Response();
		$res->set_status(200);
		$res->set_data("STOCK UPD");
		return $res;
	}

	public static function ACTEXIST($oXml){
		$SAIT_options=get_option( 'opciones_sait' );
		$NumAlm = $SAIT_options['SAITNube_NumAlm'];
		if (!isset($NumAlm) && is_null($NumAlm)) {
			$res = new WP_REST_Response();
			$res->set_status(200);
			$res->set_data("STOCK ERR ACTEXIST Not set");
			return $res;
		}
		foreach ($oXml->action as $action) {
			$NumAlmEvent = trim(self::xml_attribute($action->keys[0],"numalm"));
			if ($NumAlm!=$NumAlmEvent){
				$res = new WP_REST_Response();
				$res->set_status(200);
				$res->set_data("STOCK ERR ACTEXIST");
			return $res;
			}

			$clave = self::getClaves("arts",trim(self::xml_attribute($action->keys[0],"numart")),null);
			if (isset($clave->wcid)) {
				$product = wc_get_product( $clave->wcid );
				if ($product===false) {
					$res = new WP_REST_Response();
					$res->set_status(200);
					$res->set_data("ART NO EXISTE");
					return $res;
				}
				$product->set_stock_quantity(self::xml_attribute($action->flds[0],"existencia"));
				$product->save();
			}
		}
		$res = new WP_REST_Response();
		$res->set_status(200);
		$res->set_data("STOCK UPD ACTEXIST");
		return $res;
	}

	public static function ACTPRECIO($oXml){
		$numart=trim(self::xml_attribute($oXml->action[0]->keys[0],"numart"));
		$clave = self::getClaves("arts",$numart,null);
		$productflds = $oXml->action[0]->flds[0];
		if (!isset($clave->wcid) or !wc_get_product( $clave->wcid)) {
			$res = new WP_REST_Response();
			$res->set_status(200);
			$res->set_data("ART NO EXISTE");
			return $res;
		}
		$product = wc_get_product( $clave->wcid );
		if (self::xml_attribute($oXml->action[0]->flds[0],"preciopub")!="") {
			$preciopub = self::xml_attribute($productflds,"preciopub");
			$product->set_regular_price( $preciopub);
			$product->save();
		}
		$SAIT_options=get_option( 'opciones_sait' );
		$preciolista=$SAIT_options['SAITNube_PrecioLista'];
		$TC = $SAIT_options['SAITNube_TipoCambio'];
		
		if ($preciolista != "" || $TC !="" ) {
			$url = $SAIT_options['SAITNube_URL']."/api/v3/articulos/".$numart;
			$apikey = $SAIT_options['SAITNube_APIKey'];
			$args = array(
				'timeout' => 5,
				'sslverify' => false,
				'blocking' => true,
				'headers' => array(
					'X-sait-api-key' => $apikey,
					'Content-Type' => 'application/json',
					'Accept' => 'application/json',
				)
			);
			$resSAIT =  wp_remote_get($url, $args);
			$api_response = json_decode(  $resSAIT["body"] , true );
			if ($preciolista!=""){
				$precio = $api_response["result"]["precio".$preciolista];
				if (floatval($precio)!=0.00){
					$impuesto1 = $api_response["result"]["impuesto1"];
					$impuesto2 = $api_response["result"]["impuesto2"];
					$preciopub = strval(round(floatval($precio)*(1+(floatval($impuesto1)+floatval($impuesto2))/100),2));
					$product->set_regular_price( $preciopub );
					$product->save();
				}
			}
			if ($api_response["result"]["divisa"] == "D" && $TC !=""){
		 		$precio = strval(round(floatval($preciopub)*floatval($TC),2));
		 		$product->set_regular_price( $precio );
				$product->save();
		 	}
			$res = new WP_REST_Response();
			$res->set_status(200);
			$res->set_data("PRICE UPD API");
			return $res;
				
		}

		$res = new WP_REST_Response();
		$res->set_status(200);
		$res->set_data("PRICE UPD");
		return $res;
		
	}

	public static function MODFAMILIA($oXml){
		
	// Agregar catecorias 
	// https://stackoverflow.com/questions/53460487/add-new-product-categories-programmatically-in-woocommerce
	// TODO: CEMCO Maneja los Deptos como padres de las familias
	// el numfamilia tiene 4 digitos siendo los primeros 2 el DPTO
	//  Se podria usar para agregar el padre automaticamente.
		$clave = self::getClaves("familia",trim(self::xml_attribute($oXml->action[0]->keys[0],"numfam")),null);
		if (!isset($clave->wcid)) {
			$term_data = wp_insert_term(trim(self::xml_attribute($oXml->action[0]->flds[0],"nomfam")), 'product_cat');
			if( is_wp_error( $term_data ) ) {
				echo $term_data->get_error_message();
				$res = new WP_REST_Response();
				$res->set_status(500);
				$res->set_data($term_data->get_error_message());
				return $res;
			}
			self::insertClaves("familia",trim(self::xml_attribute($oXml->action[0]->keys[0],"numfam")),$term_data['term_id']);
			$res = new WP_REST_Response();
			$res->set_status(200);
			$res->set_data("ADD FAM");
			return $res;
		}else{
			$term_data = wp_update_term($clave->wcid,
				'product_cat',
				array(
					'name' => trim(self::xml_attribute($oXml->action[0]->flds[0],"nomfam"))
				) );
				$res = new WP_REST_Response();
				$res->set_status(200);
				$res->set_data("UPD FAM");
				return $res;
		}
	}	

	public static function MODDEPTO($oXml){
		$clave = self::getClaves("deptos",trim(self::xml_attribute($oXml->action[0]->flds[0],"numdep")),null);
		if (!isset($clave->wcid)) {
			$term_data = wp_insert_term(
					trim(self::xml_attribute($oXml->action[0]->flds[0],"nomdep")), 
					'product_cat'
			);
			if( is_wp_error( $term_data ) ) {
				echo $term_data->get_error_message();
				$res = new WP_REST_Response();
				$res->set_status(500);
				$res->set_data($term_data->get_error_message());
				return $res;
			}
			self::insertClaves("deptos",trim(self::xml_attribute($oXml->action[0]->flds[0],"numdep")),$term_data['term_id']);
			$res = new WP_REST_Response();
			$res->set_status(200);
			$res->set_data("ADD DEPTO");
			return $res;
		}else{
			$term_data = wp_update_term($clave->wcid,
				'product_cat',
				array(
					'name' => trim(self::xml_attribute($oXml->action[0]->flds[0],"nomdep"))
				) );
				$res = new WP_REST_Response();
				$res->set_status(200);
				$res->set_data("UPD DEPTO");
				return $res;
		}
	}

	public static function MODLINEA($oXml){
		$clave = self::getClaves("lineas",trim(self::xml_attribute($oXml->action[0]->keys[0],"numlin")),null);
		if (!isset($clave->wcid)) {
			$term_data = wp_insert_term(
					trim(self::xml_attribute($oXml->action[0]->flds[0],"nomlin")), 
					'product_cat'
			);
			if( is_wp_error( $term_data ) ) {
				$res = new WP_REST_Response();
				$res->set_status(200);
				$res->set_data($term_data->get_error_message());
				return $res;
			}
			self::insertClaves("lineas",trim(self::xml_attribute($oXml->action[0]->keys[0],"numlin")),$term_data['term_id']);
			$res = new WP_REST_Response();
			$res->set_status(200);
			$res->set_data("ADD Linea");
			return $res;
		}else{
			$term = get_term($clave->wcid);
			if (is_wp_error($term) ||  is_null($term) ){
				// cat ya no existe borrarlo de tabla SAIT
				// para evitar conflictos
				$clave = self::getClaves("lineas",trim(self::xml_attribute($oXml->action[0]->keys[0]),"numlin"),null);
				self::deleteClaves($clave->id);
				$term_data = wp_insert_term(
						trim(self::xml_attribute($oXml->action[0]->flds[0],"nomlin")), 
						'product_cat'
				);
				if( is_wp_error( $term_data ) ) {
					$res = new WP_REST_Response();
					$res->set_status(200);
					$res->set_data($term_data->get_error_message());
					return $res;
				}
				self::insertClaves("lineas",trim(self::xml_attribute($oXml->action[0]->keys[0],"numlin")),$term_data['term_id']);
				$res = new WP_REST_Response();
				$res->set_status(200);
				$res->set_data("ADD Linea");
				return $res;
			}
			$term_data = wp_update_term($clave->wcid,
				'product_cat',
				array(
					'name' => trim(self::xml_attribute($oXml->action[0]->flds[0],"nomlin"))
				) );
				$res = new WP_REST_Response();
				$res->set_status(200);
				$res->set_data("UPD linea");
				return $res;
		}
	}

	public static function ACTTC($oXml){
		$SAIT_options=get_option( 'opciones_sait' );
		$OldTC = $SAIT_options['SAITNube_TipoCambio'];
		$NewTC=self::xml_attribute($oXml->action[0]->flds[0],"tc");
		if ($OldTC == $NewTC){
			$res = new WP_REST_Response();
			$res->set_status(200);
			$res->set_data("Same TC");
			return $res;
		}
		$SAIT_options['SAITNube_TipoCambio']=$NewTC;
		update_option( 'opciones_sait', $SAIT_options );
		
		$url = $SAIT_options['SAITNube_URL']."/api/v3/articulos?divisa=D&statusweb=1&limit=10000";
		$apikey = $SAIT_options['SAITNube_APIKey'];
		$args = array(
			'timeout' => 5,
			'sslverify' => false,
			'blocking' => true,
			'headers' => array(
				'X-sait-api-key' => $apikey,
				'Content-Type' => 'application/json',
				'Accept' => 'application/json',
			)
		);
		$resSAIT =  wp_remote_get($url, $args);
		$api_response = json_decode(  $resSAIT["body"] , true );
	
		foreach ($api_response["result"] as $row) {
			$clave = self::getClaves("arts",trim($row["numart"]),null);
			$product = wc_get_product( $clave->wcid );
	
			if ($product===false) {
				continue;
			}
			$precio = strval(round(floatval($row["preciopub"])*floatval($NewTC),2));
			$product->set_regular_price( $precio );		
			$product->save();
		}
		$res = new WP_REST_Response();
		$res->set_status(200);
		$res->set_data("UPD TC");
		return $res;
	}

	public static function MODCLI($oXml){
		// Proceso de MODCLI

		// Saco la clave del articulo
		$clave = self::getClaves("clientes",trim(self::xml_attribute($oXml->action[0]->keys[0],"numcli")),null);

		$emailtw = trim(self::xml_attribute($oXml->action[0]->flds[0],"emailtw"));

		if ($emailtw==""){
			$res = new WP_REST_Response();
			$res->set_status(200);
			$res->set_data("no es cliente web");
			return $res;
			return;
		}
		// Si ya existe el cliente no hacer nada
		if (isset($clave->wcid)) {
			$res = new WP_REST_Response();
			$res->set_status(200);
			$res->set_data("cliente ya existe ".$clave->clave." ".$clave->wcid);
			return $res;
			return;
		}

		// Registrar nuevo cliente
		$user_id = wc_create_new_customer( $emailtw  );
		// Guardar en claves
		self::insertClaves("clientes",trim(self::xml_attribute($oXml->action[0]->keys[0],"numcli")),$user_id);
		$res = new WP_REST_Response();
		$res->set_status(200);
		$res->set_data("CLI ADD ");
		return $res;

	}	 
	 
	 
	//
	// Funciones Claves SAIT
	// Tabla sait_claves creada en SAIT_WOOCOMMERCE-activator.php
	//
	public static function getClaves($tabla,$clave,$wcid){
		global $wpdb;
		return $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."sait_claves WHERE tabla = '".$tabla."'and (clave = '".$clave."' or wcid ='" .$wcid."')", OBJECT);
	}
	 
	public static function insertClaves($tabla,$clave,$wcid){
		global $wpdb;
		$wpdb->insert( 
			$wpdb->prefix.'sait_claves', 
				array( 
						'tabla' => $tabla,
						'clave' => $clave,
						'wcid'  => $wcid
				)
		);
	}

	public static function deleteClaves($id){
		global $wpdb;
		$wpdb->delete( $wpdb->prefix.'sait_claves', array( 'id' => $id ) );
	}

	//
	// UTILERIAS

	// xml_attribute()
	// te retorna el valor del atributo del nodo que le mandes 
	public static function xml_attribute($object, $attribute)
	{
			if(isset($object[$attribute]))
					return htmlspecialchars_decode((string) $object[$attribute]);
	}

}
