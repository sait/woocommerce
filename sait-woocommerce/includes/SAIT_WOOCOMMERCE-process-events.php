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
				$res = SAIT_UTILS::SAIT_response(200,"OK");
				break;
		}
		return $res;
	}

	//
	// dividir esta func, add,upd,delete.
	//

	public static function MODART($oXml){
		// Proceso de MODART
		$oKeys = $oXml->action[0]->keys[0];
		$oFlds = $oXml->action[0]->flds[0];
		// Saco la clave del articulo
		$clave = SAIT_UTILS::SAIT_getClaves("arts",trim(self::xml_attribute($oKeys,"numart")),null);

		// Si viene con statusweb=0 salir
		$statusweb = self::xml_attribute($oFlds,"statusweb");
		if ($statusweb == "0") {
			// Si existe lo manda a la papelera
			if (isset($clave->wcid)){
				wp_trash_post($clave->wcid);
			}
			return SAIT_UTILS::SAIT_response(200,"OK");
		}
		

		// Si ya existe el articulo en la tabla de SAIT solo actualizar datos
		if (isset($clave->wcid)) {
			// Revisar si el articulo esta en WC o fue eliminado
			$product = wc_get_product( $clave->wcid );
			if ($product===false) {
				// Producto ya no existe borrarlo de tabla SAIT
				// para evitar conflictos
				SAIT_UTILS::SAIT_deleteClaves($clave->id);
				return SAIT_UTILS::SAIT_response(200,"ART NO EXISTE");
			}
			// wp_untrash_post si el id esta en la papelera sacarlo
			wp_untrash_post($clave->wcid);

			// Actualizar producto
			$product = wc_get_product( $clave->wcid );
			$product->set_name( trim(self::xml_attribute($oFlds,"desc")) );
			// TODO: Tomar de config que tabla usaran para categorizar
			$clavelinea = SAIT_UTILS::SAIT_getClaves("familia",trim(self::xml_attribute($oFlds,"familia")),null);
			if (isset($clavelinea->wcid)) {
				$product->set_category_ids(array( $clavelinea->wcid));
			}
			$product->save();
			
			return SAIT_UTILS::SAIT_response(200,"ART UPD");
		}

		// Registrar nuevo producto
		$product = new WC_Product_Simple();
		$product->set_name( trim(self::xml_attribute($oFlds,"desc")) ); 
		$product->set_SKU(trim(self::xml_attribute($oKeys,"numart")));
		$product->set_status("draft");
		$product->set_manage_stock(true);
		$clavelinea = SAIT_UTILS::SAIT_getClaves("familia",trim(self::xml_attribute($oFlds,"familia")),null);
		if (isset($clavelinea->wcid)) {
			$product->set_category_ids(array( $clavelinea->wcid));
		}
		$product_id = $product->save();
		// Guardar en claves
		SAIT_UTILS::SAIT_insertClaves("arts",trim(self::xml_attribute($oKeys,"numart")),$product_id);

		return SAIT_UTILS::SAIT_response(200,"ART ADD");

	}

	public static function ACTEXISGBL($oXml){
		$SAIT_options=get_option( 'opciones_sait' );
		$NumAlm = $SAIT_options['SAITNube_NumAlm'];
		if (isset($NumAlm) && !is_null($NumAlm)) {
			return SAIT_UTILS::SAIT_response(200,"STOCK ERR ACTEXISGBL");
		}
		foreach ($oXml->action as $action) {
			$clave = SAIT_UTILS::SAIT_getClaves("arts",trim(self::xml_attribute($action->keys[0],"numart")),null);
			if (isset($clave->wcid)) {
				$product = wc_get_product( $clave->wcid );
				if ($product===false) {
					return SAIT_UTILS::SAIT_response(200,"ART NO EXISTE");
				}
				$product->set_stock_quantity(self::xml_attribute($action->flds[0],"existencia"));
				$product->save();
			}
		}

		return SAIT_UTILS::SAIT_response(200,"STOCK UPD");
	}

	public static function ACTEXIST($oXml){
		$SAIT_options=get_option( 'opciones_sait' );
		$NumAlm = $SAIT_options['SAITNube_NumAlm'];
		if (!isset($NumAlm) && is_null($NumAlm)) {
			return SAIT_UTILS::SAIT_response(200,"STOCK ERR ACTEXIST Not set");
		}
		foreach ($oXml->action as $action) {
			$NumAlmEvent = trim(self::xml_attribute($action->keys[0],"numalm"));
			if ($NumAlm!=$NumAlmEvent){
				return SAIT_UTILS::SAIT_response(200,"STOCK ERR ACTEXIST");
			}

			$clave = SAIT_UTILS::SAIT_getClaves("arts",trim(self::xml_attribute($action->keys[0],"numart")),null);
			if (isset($clave->wcid)) {
				$product = wc_get_product( $clave->wcid );
				if ($product===false) {
					return SAIT_UTILS::SAIT_response(200,"ART NO EXISTE");
				}
				$product->set_stock_quantity(self::xml_attribute($action->flds[0],"existencia"));
				$product->save();
			}
		}
		
		return SAIT_UTILS::SAIT_response(200,"STOCK UPD ACTEXIST");
	}

	public static function ACTPRECIO($oXml){
		$numart=trim(self::xml_attribute($oXml->action[0]->keys[0],"numart"));
		$clave = SAIT_UTILS::SAIT_getClaves("arts",$numart,null);
		$productflds = $oXml->action[0]->flds[0];
		if (!isset($clave->wcid) or !wc_get_product( $clave->wcid)) {
			return SAIT_UTILS::SAIT_response(200,"ART NO EXISTE");
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
			$api_response = SAIT_UTILS::SAIT_GetNube("/api/v3/articulos/".$numart);
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
			return SAIT_UTILS::SAIT_response(200,"PRICE UPD API");
				
		}

		return SAIT_UTILS::SAIT_response(200,"PRICE UPD");
		
	}

	// MODCATEGORIAWC()
	//  registra o modifica una categoria en WooCommerce
	//  $oXml: el evento
	//  $tabla: nombre de la tabla de la categoria
	//  $numcat: nombre del campo clave
	//  $nomcat: campo con el nombre de la categoria
	public static function MODCATEGORIAWC($oXml,$tabla,$numcat,$nomcat){
		$clave = SAIT_UTILS::SAIT_getClaves($tabla,trim(self::xml_attribute($oXml->action[0]->keys[0],$numcat)),null);
		if (!isset($clave->wcid)) {
			$term_data = wp_insert_term(
					trim(self::xml_attribute($oXml->action[0]->flds[0],$nomcat)), 
					'product_cat'
			);
			if( is_wp_error( $term_data ) ) {
				return SAIT_UTILS::SAIT_response(500,$term_data->get_error_message());
			}
			SAIT_UTILS::SAIT_insertClaves($tabla,trim(self::xml_attribute($oXml->action[0]->keys[0],$numcat)),$term_data['term_id']);
			return SAIT_UTILS::SAIT_response(200,"ADD Linea");
		}else{
			$term = get_term($clave->wcid);
			if (is_wp_error($term) ||  is_null($term) ){
				// cat ya no existe borrarlo de tabla SAIT
				// para evitar conflictos
				$clave = SAIT_UTILS::SAIT_getClaves($tabla,trim(self::xml_attribute($oXml->action[0]->keys[0]),$numcat),null);
				SAIT_UTILS::SAIT_deleteClaves($clave->id);
				$term_data = wp_insert_term(
						trim(self::xml_attribute($oXml->action[0]->flds[0],$nomcat)), 
						'product_cat'
				);
				if( is_wp_error( $term_data ) ) {
					return SAIT_UTILS::SAIT_response(500,$term_data->get_error_message());
				}
				SAIT_UTILS::SAIT_insertClaves($tabla,trim(self::xml_attribute($oXml->action[0]->keys[0],$numcat)),$term_data['term_id']);
				return SAIT_UTILS::SAIT_response(200,"ADD ".$tabla);
			}
			$term_data = wp_update_term($clave->wcid,
				'product_cat',
				array(
					'name' => trim(self::xml_attribute($oXml->action[0]->flds[0],$nomcat))
				) );

				return SAIT_UTILS::SAIT_response(200,"UPD ".$tabla);
		}

	}	

	public static function MODFAMILIA($oXml){
		return self::MODCATEGORIAWC($oXml,"familia","numfam","nomfam");
	}	

	public static function MODDEPTO($oXml){
		return self::MODCATEGORIAWC($oXml,"deptos","numdep","nomdep");
	}

	public static function MODLINEA($oXml){
		return self::MODCATEGORIAWC($oXml,"lineas","numlin","nomlin");
	}

	public static function ACTTC($oXml){
		$SAIT_options=get_option( 'opciones_sait' );
		$OldTC = $SAIT_options['SAITNube_TipoCambio'];
		$NewTC=self::xml_attribute($oXml->action[0]->flds[0],"tc");
		if ($OldTC == $NewTC){
			return SAIT_UTILS::SAIT_response(200,"same TC");
		}
		$SAIT_options['SAITNube_TipoCambio']=$NewTC;
		update_option( 'opciones_sait', $SAIT_options );
		$api_response = SAIT_UTILS::SAIT_GetNube("/api/v3/articulos?divisa=D&statusweb=1&limit=10000");

		foreach ($api_response["result"] as $row) {
			$clave = SAIT_UTILS::SAIT_getClaves("arts",trim($row["numart"]),null);
			$product = wc_get_product( $clave->wcid );
	
			if ($product===false) {
				continue;
			}
			$precio = strval(round(floatval($row["preciopub"])*floatval($NewTC),2));
			$product->set_regular_price( $precio );		
			$product->save();
		}

		return SAIT_UTILS::SAIT_response(200,"Upd TC");
	}

	public static function MODCLI($oXml){

		// Si no es cliente web omitir
		$emailtw = trim(self::xml_attribute($oXml->action[0]->flds[0],"emailtw"));
		if ($emailtw==""){
			return SAIT_UTILS::SAIT_response(200,"No es cliente web");
		}

		// Si ya existe el cliente no hacer nada
		$clave = SAIT_UTILS::SAIT_getClaves("clientes",trim(self::xml_attribute($oXml->action[0]->keys[0],"numcli")),null);
		if (isset($clave->wcid)) {
			// Buscar el cliente si el email es distinto cambiarlo
			$customer = new WC_Customer( $clave->wcid );
			$customer->get_email();
			if ($emailtw != $customer->get_email()){			
				$customer->set_email( $emailtw );
				$customer->save();
				return SAIT_UTILS::SAIT_response(200,"Cliente actualizado");
			}

			return SAIT_UTILS::SAIT_response(200,"Cliente ya existe");
		}
		// woocommerce 9.3 requiere estas opciones
		update_option('woocommerce_registration_generate_password', 'yes');
		update_option('woocommerce_registration_generate_username', 'yes');
		// Registrar nuevo cliente
		$user_id = wc_create_new_customer( $emailtw  );
		if ( is_wp_error($user_id)) {
			 return SAIT_UTILS::SAIT_response(200,"Ya hay una cuenta registrada con ese correo");
    	}
		// Guardar en claves
		SAIT_UTILS::SAIT_insertClaves("clientes",trim(self::xml_attribute($oXml->action[0]->keys[0],"numcli")),$user_id);
		
		return SAIT_UTILS::SAIT_response(200,"Cli ADD");

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
