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

	/**
	 * Enruta un evento XML de SAIT al procesador correspondiente.
	 *
	 * @param SimpleXMLElement $oXml XML del evento con atributo type.
	 * @return WP_REST_Response Respuesta normalizada para el endpoint REST.
	 *
	 * Acciones que realiza: delega en funciones que pueden modificar productos, categorias,
	 * clientes, existencias, precios u opciones del plugin.
	 */
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
			case "MODCATEGO":
				$res = self::MODCATEGO($oXml);
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

	/**
	 * Procesa altas, cambios o bajas web de articulos SAIT.
	 *
	 * @param SimpleXMLElement $oXml Evento MODART.
	 * @return WP_REST_Response Resultado de la sincronizacion del articulo.
	 *
	 * Acciones que realiza: crea, actualiza, restaura o envia a papelera productos WooCommerce
	 * y mantiene la relacion en sait_claves.
	 */
	public static function MODART($oXml){
		// Proceso de MODART
		$oKeys = $oXml->action[0]->keys[0];
		$oFlds = $oXml->action[0]->flds[0];
	  // pasar atributos a variables
		$numart = trim(self::xml_attribute($oKeys, "numart"));
		$codigo = SAIT_UTILS::SAIT_codigo_valido(trim(self::xml_attribute($oFlds, "codigo")));
		$desc = trim(self::xml_attribute($oFlds, "desc"));
		$linea = trim(self::xml_attribute($oFlds, "linea"));
		$modelo = trim(self::xml_attribute($oFlds, "modelo"));
		$statusweb = trim(self::xml_attribute($oFlds, "statusweb"));
		$obs = trim(self::xml_attribute($oFlds, "obs"));
		// Si statusweb vaio no es modart completo
		if ( $statusweb === "")  {
					return SAIT_UTILS::SAIT_response(200, "statusweb null");
			}
		// Obtener la categoría una sola vez
		$clavelinea = SAIT_UTILS::SAIT_getClaves("lineas", $linea, null);
		$category_id = isset($clavelinea->wcid) ? array($clavelinea->wcid) : array();
		
		$clave = SAIT_UTILS::SAIT_getClaves("arts", $numart, null);
	
		/*
 	   $product_id_by_sku = wc_get_product_id_by_sku($numart);
		if ($product_id_by_sku) {
				$product = wc_get_product($product_id_by_sku);

				// Si existe producto y no teníamos clave registrada aún
				if ($product && !$clave) {
						// Registrar o actualizar la clave ligando el numart al producto por SKU
						//SAIT_UTILS::SAIT_insertClaves("arts", $numart, $product_id_by_sku);
						//	$clave = SAIT_UTILS::SAIT_getClaves("arts", $numart, null); // refrescar clave
						
						//	ES PRODUCTO PREVIAMENTE REGISTRADO DE FYSON HACER UPDATE
			
						// Actualizar producto
						//$product->set_name($desc);
						//$product->set_sku($numart);
						//$product->set_global_unique_id( $codigo );

						if (!empty($category_id)) {
								$product->set_category_ids($category_id);
						}

						if (!empty($obs)) {
								 $product->set_description($obs);
						}

						$product->save();

						return SAIT_UTILS::SAIT_response(200, "ART UPD");
				}
		}
		
		
		
		//$product_id_by_codigo = "";
		//if ($codigo != "") {
			// Obtener id producto por codigo y numart
			//$product_id_by_codigo = wc_get_product_id_by_global_unique_id( $codigo );

			// Si es un articulo que ya estaba en la tienda lo registramos en tabla claves
			//if ( $product_id_by_codigo && !$clave ) {
				//SAIT_UTILS::SAIT_insertClaves("arts", $numart, $product_id_by_codigo);
				//$clave = SAIT_UTILS::SAIT_getClaves("arts", $numart, null); // refrescar clave
			//}	
		//}

*/
		// Si statusweb = 0, vacío o null → eliminar el producto
		if ($statusweb === "0" || $statusweb === "" || $statusweb === null) {
				if (isset($clave->wcid)) {
						wp_trash_post($clave->wcid);
				}
				return SAIT_UTILS::SAIT_response(200, "OK");
		}
		
/*
		$product_id_by_sku = wc_get_product_id_by_sku($numart);

		if ($product_id_by_sku) {
				$product = wc_get_product($product_id_by_sku);

				// Si existe producto y no teníamos clave registrada aún
				if ($product && !$clave) {
						// Registrar o actualizar la clave ligando el numart al producto por SKU
						SAIT_UTILS::SAIT_insertClaves("arts", $numart, $product_id_by_sku);
						$clave = SAIT_UTILS::SAIT_getClaves("arts", $numart, null); // refrescar clave
				}
		}
		*/

		// Si ya existe el artículo → actualizar
		if (isset($clave->wcid)) {
				$product = wc_get_product($clave->wcid);
		
				// Si no existe el producto → eliminar la clave y salir
				if (!$product) {
						SAIT_UTILS::SAIT_deleteClaves($clave->id);
						return SAIT_UTILS::SAIT_response(200, "ART NO EXISTE");
				}
		
				// Si estaba en papelera → restaurar y volver a cargar el producto
				wp_untrash_post($clave->wcid);
				$product = wc_get_product($clave->wcid);
		
				// Actualizar producto
				$product->set_name($desc);
				$product->set_sku($numart);
				try {
					$product->set_global_unique_id( $codigo );
				} catch (Exception $e) {
					// Si falla (por duplicado o inválido), lo registramos en el log y seguimos
					error_log("SAIT Error: No se pudo asignar el código $codigo al producto " . $numart . " - " . $e->getMessage());
				}
		
				if (!empty($category_id)) {
						$product->set_category_ids($category_id);
				}
		
				if (!empty($modelo)) {
						$product->set_short_description("Modelo: " . $modelo);
				}

				if (!empty($obs)) {
					$product->set_description($obs);
				}
				// Obtener stock actual del producto
				$current_stock = $product->get_stock_quantity();

				// Si el stock es 0, consultar existencia en SAIT
				if (empty($current_stock) || $current_stock <= 0) {
					$sait_stock = SAIT_UTILS::getExistSAIT($numart);

					// Si hay existencia en SAIT, actualizar el stock
					if (!empty($sait_stock) && $sait_stock > 0) {
						$product->set_stock_quantity($sait_stock);
	
					}
				}
				$product->save();
		
				return SAIT_UTILS::SAIT_response(200, "ART UPD");
		}
		
		// Si no existe el artículo → crear uno nuevo
		$product = new WC_Product_Simple();
		$product->set_name($desc);
		$product->set_sku($numart);
		try {
			$product->set_global_unique_id( $codigo );
		} catch (Exception $e) {
			// Si falla (por duplicado o inválido), lo registramos en el log y seguimos
			error_log("SAIT Error: No se pudo asignar el código $codigo al producto " . $numart . " - " . $e->getMessage());
		}
		$product->set_status("draft");
		$product->set_manage_stock(true);
		$product->set_regular_price( 0);
		if (!empty($category_id)) {
				$product->set_category_ids($category_id);
		}
		
		if (!empty($modelo)) {
				$product->set_short_description("Modelo: " . $modelo);
		}

		if (!empty($obs)) {
			$product->set_description($obs);
		}

		$sait_stock = SAIT_UTILS::getExistSAIT($numart);

		// Si hay existencia en SAIT, actualizar el stock
		if (!empty($sait_stock) && $sait_stock > 0) {
			$product->set_stock_quantity($sait_stock);

		}

		$product_id = $product->save();
		
		// Guardar la nueva clave si se creó el producto
		if ($product_id) {
				SAIT_UTILS::SAIT_insertClaves("arts", $numart, $product_id);
				return SAIT_UTILS::SAIT_response(200, "ART ADD");
		}
		
		return SAIT_UTILS::SAIT_response(200, "ART NO CREADO");
	}
	

	/**
	 * Actualiza existencias globales cuando no hay almacen especifico configurado.
	 *
	 * @param SimpleXMLElement $oXml Evento ACTEXISGBL.
	 * @return WP_REST_Response Resultado de actualizacion de stock.
	 *
	 * Acciones que realiza: modifica stock de productos WooCommerce ligados en sait_claves.
	 */
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

	/**
	 * Actualiza existencias por articulo y almacen.
	 *
	 * @param SimpleXMLElement $oXml Evento ACTEXIST.
	 * @return WP_REST_Response Resultado de actualizacion de stock.
	 *
	 * Acciones que realiza: modifica stock de productos WooCommerce; en modo multi-almacen
	 * consulta SAIT Nube, suma almacenes permitidos y cachea el total temporalmente.
	 */
	public static function ACTEXIST($oXml){
		$SAIT_options=get_option( 'opciones_sait' );
		$NumAlm = $SAIT_options['SAITNube_NumAlm'];
		
	    $SAIT_options = get_option('opciones_sait');
		$ExistAlm_activo = isset($SAIT_options['SAITNube_ExistAlm_enabled']) && $SAIT_options['SAITNube_ExistAlm_enabled'] === '1';

		
		if (!$ExistAlm_activo && (!isset($NumAlm) || is_null($NumAlm))) {
			return SAIT_UTILS::SAIT_response(200, "STOCK ERR ACTEXIST Not set");
		}
		
		foreach ($oXml->action as $action) {
			$numart = trim(self::xml_attribute($action->keys[0],"numart"));
			$NumAlmEvent = trim(self::xml_attribute($action->keys[0],"numalm"));
			if (!$ExistAlm_activo && $NumAlm != $NumAlmEvent) {
				return SAIT_UTILS::SAIT_response(200, "STOCK ERR ACTEXIST");
			}

			$clave = SAIT_UTILS::SAIT_getClaves("arts",$numart,null);
			$product_id = null;

			// Primero intenta con $clave->wcid
			if (!empty($clave->wcid)) {
				$product_id = $clave->wcid;
			} else {
				// Si no hay wcid, intenta buscar por sku
				$product_id = wc_get_product_id_by_sku($numart);
			}

			// Si no hay producto, salte
			if (empty($product_id)) {
				return SAIT_UTILS::SAIT_response(200,"ART NO EXISTE");
			}

			// Obtén el producto
			$product = wc_get_product($product_id);

			// Si tampoco existe como producto válido, salte
			if (!$product) {
				return SAIT_UTILS::SAIT_response(200,"ART NO EXISTE");
			}
      
			$quantity = self::xml_attribute($action->flds[0], "existencia");
			
			if ($ExistAlm_activo) {
				    // Clave de caché diferente según sucursal
					$cache_key = 'sait_stock_' . md5($numart);
					$total = get_transient($cache_key);

					if ($total === false) {
						$respuesta = SAIT_UTILS::SAIT_GetNube("/api/v3/existencias/" . $numart);
						$result = SAIT_UTILS::SAIT_getResult($respuesta);
						$total = 0;

						if (!empty($result)) {
							foreach ($result as $almacen) {
								// sumar solo las almacenes permitidas
								$almacenes_a_mostrar = array_map('trim', explode(',', $SAIT_options['SAITNube_ExistAlm']));
								if (in_array($almacen['numalm'], $almacenes_a_mostrar)) {
									$total += (float) $almacen['existencia'];
								}
							}

						}

						set_transient($cache_key, $total, 120); // caché 2 min
					}
					$product->set_stock_quantity(round($total, 2));
					$product->save();
					return SAIT_UTILS::SAIT_response(200,"STOCK UPD ACTEXIST");
			}	
				
			// Cambia el stock y guarda
			$product->set_stock_quantity($quantity);
			$product->save();
		}
		
		return SAIT_UTILS::SAIT_response(200,"STOCK UPD ACTEXIST");
	}

	/**
	 * Actualiza el precio regular de un articulo WooCommerce desde SAIT.
	 *
	 * @param SimpleXMLElement $oXml Evento ACTPRECIO.
	 * @return WP_REST_Response Resultado de actualizacion de precio.
	 *
	 * Acciones que realiza: puede consultar datos del articulo en SAIT Nube y guardar cambios
	 * de precio en el producto WooCommerce.
	 */
	public static function ACTPRECIO($oXml){
		$numart = trim(self::xml_attribute($oXml->action[0]->keys[0], "numart"));
		

		$productflds = $oXml->action[0]->flds[0];

		// Verificar si hay precios normales
		$tiene_precios_normales = false;
		foreach (["preciopub", "precio1", "precio2", "precio3", "precio4", "precio5"] as $campo) {
			$valor = self::xml_attribute($productflds, $campo);
			if ($valor !== "" && is_numeric($valor) && floatval($valor) > 0) {
				$tiene_precios_normales = true;
				break;
			}
		}

		if (!$tiene_precios_normales) {
			return SAIT_UTILS::SAIT_response(200, "IGNORADO (ppubv*)");
		}



		
		$clave  = SAIT_UTILS::SAIT_getClaves("arts", $numart, null);
		$productflds = $oXml->action[0]->flds[0];

		$product_id = null;

		// Primero intenta con $clave->wcid
		if (!empty($clave->wcid)) {
			$product_id = $clave->wcid;
		} else {
			// Si no existe o no es válido, buscar por SKU
			$product_id = wc_get_product_id_by_sku($numart);
		}

		// Si no se encontró producto
		if (empty($product_id)) {
			return SAIT_UTILS::SAIT_response(200, "ART NO EXISTE");
		}

		// Obtener producto
		$product = wc_get_product($product_id);

		// Si el objeto producto no es válido
		if (!$product) {
			return SAIT_UTILS::SAIT_response(200, "ART NO EXISTE");
		}


		$cambios = false;
		$SAIT_options = get_option('opciones_sait');
		$preciolista = isset($SAIT_options['SAITNube_PrecioLista']) ? $SAIT_options['SAITNube_PrecioLista'] : "";
		$TC = isset($SAIT_options['SAITNube_TipoCambio']) ? $SAIT_options['SAITNube_TipoCambio'] : "";

		// Precio desde XML
		$preciopub_attr = self::xml_attribute($productflds, "preciopub");
		if ($preciopub_attr !== "" && is_numeric($preciopub_attr) && floatval($preciopub_attr) > 0) {
			$preciopub = floatval($preciopub_attr);
			if (floatval($product->get_regular_price()) != $preciopub) {
				$product->set_regular_price($preciopub);
				$cambios = true;
			}
		}

		// Precio desde API solo si es necesario
		if ($preciolista != "" || $TC != "") {
			$api_response = SAIT_UTILS::SAIT_GetNube("/api/v3/articulos/".$numart);
			$api_result = SAIT_UTILS::SAIT_getResult($api_response);

			if ($api_result !== null) {
				// Precio lista
				if ($preciolista != "") {
					$precio = floatval($api_result["precio".$preciolista]);
					if ($precio != 0.0) {
						$impuesto1 = floatval($api_result["impuesto1"]);
						$impuesto2 = floatval($api_result["impuesto2"]);
						$preciopub_api = round($precio * (1 + ($impuesto1 + $impuesto2)/100), 2);
						if (floatval($product->get_regular_price()) != $preciopub_api) {
							$product->set_regular_price($preciopub_api);
							$cambios = true;
						}
					}
				}

				// Tipo de cambio
				if ($api_result["divisa"] === "D" && $TC != "") {
					$precio = round(floatval(self::xml_attribute($productflds, "preciopub")) * floatval($TC), 2);
					if (floatval($product->get_regular_price()) != $precio) {
						$product->set_regular_price($precio);
						$cambios = true;
					}
				}
			}
		}

		if ($cambios) {
			$product->save();
			return SAIT_UTILS::SAIT_response(200, "PRICE UPD");
		}

		return SAIT_UTILS::SAIT_response(200, "NO CAMBIO");
	}

	/**
	 * Crea o actualiza una categoria WooCommerce vinculada a una tabla de SAIT.
	 *
	 * @param SimpleXMLElement $oXml Evento de categoria.
	 * @param string $tabla Nombre logico guardado en sait_claves.
	 * @param string $numcat Atributo XML que contiene la clave SAIT.
	 * @param string $nomcat Atributo XML que contiene el nombre de la categoria.
	 * @return WP_REST_Response Resultado de alta/actualizacion.
	 *
	 * Acciones que realiza: crea/actualiza terminos product_cat y mantiene relaciones en sait_claves.
	 */
	public static function MODCATEGORIAWC($oXml,$tabla,$numcat,$nomcat){
		$clave = SAIT_UTILS::SAIT_getClaves($tabla,trim(self::xml_attribute($oXml->action[0]->keys[0],$numcat)),null);
		$nombre = trim(self::xml_attribute($oXml->action[0]->flds[0],$nomcat));
		if (empty($nombre)) {
			return SAIT_UTILS::SAIT_response(200,"linea vacia");
		}
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
				// no existe una categoria con ese ID
				// buscar por nombre para evitar conflictos
				$term = get_term_by('name', trim(self::xml_attribute($oXml->action[0]->flds[0],$nomcat)), 'product_cat');
				if (isset($term->term_id)) {
                    // si existe cambio de id
                    SAIT_UTILS::SAIT_deleteClaves($clave->id);
					SAIT_UTILS::SAIT_insertClaves($tabla,trim(self::xml_attribute($oXml->action[0]->keys[0],$numcat)),$term->term_id);
					return SAIT_UTILS::SAIT_response(200,"UPD ".$tabla);
				} 
				// cat ya no existe borrarlo de tabla SAIT
				// para evitar conflictos
				$clave = SAIT_UTILS::SAIT_getClaves($tabla,trim(self::xml_attribute($oXml->action[0]->keys[0],$numcat)),null);
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
		return self::MODCATEGORIAWC($oXml,"deptos","valdep","nomdep");
	}

	public static function MODLINEA($oXml){
		return self::MODCATEGORIAWC($oXml,"lineas","numlin","nomlin");
	}

	public static function MODCATEGO($oXml){
		return self::MODCATEGORIAWC($oXml,"catego","numcat","nomcat");
	}

	/**
	 * Actualiza el tipo de cambio configurado y recalcula precios de articulos en dolares.
	 *
	 * @param SimpleXMLElement $oXml Evento ACTTC.
	 * @return WP_REST_Response Resultado de actualizacion de tipo de cambio.
	 *
	 * Acciones que realiza: actualiza opciones_sait, consulta articulos en divisa D y guarda
	 * precios regulares recalculados en productos WooCommerce.
	 */
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
		$result = SAIT_UTILS::SAIT_getResult($api_response);
		if (empty($result)){
				return SAIT_UTILS::SAIT_response(200,"Upd TC");
		}
		foreach ($result as $row) {
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

	/**
	 * Sincroniza un cliente SAIT con un usuario/cliente WooCommerce.
	 *
	 * @param SimpleXMLElement $oXml Evento MODCLI.
	 * @return WP_REST_Response Resultado de sincronizacion del cliente.
	 *
	 * Acciones que realiza: puede crear clientes WooCommerce, actualizar email, enviar correo
	 * de nueva cuenta y mantener la relacion en sait_claves.
	 */
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
				$user_by_email = get_user_by('email',$emailtw);
				if ($user_by_email && $user_by_email->ID != $clave->wcid){
					return SAIT_UTILS::SAIT_response(200,"Correo ya asignado a otro usuario");
				}
				$customer->set_email( $emailtw );
				$customer->save();
				$mailer = WC()->mailer();
				$email = $mailer->emails['WC_Email_Customer_New_Account'];
				$email->trigger($clave->wcid,null,true);
				return SAIT_UTILS::SAIT_response(200,"Cliente actualizado");
			}

			return SAIT_UTILS::SAIT_response(200,"Cliente ya existe");
		}
	
		// Si no existe el numcli pero el correo ya existe, ligar la clave
		$user_by_email = get_user_by('email',$emailtw);
		if ($user_by_email){
			SAIT_UTILS::SAIT_insertClaves("clientes",trim(self::xml_attribute($oXml->action[0]->keys[0],"numcli")),$user_by_email->ID);
			return SAIT_UTILS::SAIT_response(200,"Cliente ligado a usuario existente");
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

	/**
	 * Lee un atributo XML y lo devuelve como texto decodificado.
	 *
	 * @param SimpleXMLElement $object Nodo XML origen.
	 * @param string $attribute Nombre del atributo.
	 * @return string|null Valor del atributo o null si no existe.
	 */
	public static function xml_attribute($object, $attribute)
	{
			if(isset($object[$attribute]))
					return htmlspecialchars_decode((string) $object[$attribute]);
	}

}
