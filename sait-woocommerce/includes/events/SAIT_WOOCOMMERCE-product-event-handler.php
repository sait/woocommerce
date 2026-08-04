<?php

/** Procesa eventos de altas, cambios y bajas de productos SAIT. */
class SAIT_WOOCOMMERCE_ProductEventHandler
{
	public static function MODART($oXml){
		// Proceso de MODART
		$oKeys = $oXml->action[0]->keys[0];
		$oFlds = $oXml->action[0]->flds[0];
	  // pasar atributos a variables
		$numart = trim(SAIT_WOOCOMMERCE_ProcessEvents::xml_attribute($oKeys, "numart"));
		$codigo = SAIT_UTILS::SAIT_codigo_valido(trim(SAIT_WOOCOMMERCE_ProcessEvents::xml_attribute($oFlds, "codigo")));
		$desc = trim(SAIT_WOOCOMMERCE_ProcessEvents::xml_attribute($oFlds, "desc"));
		$modelo = trim(SAIT_WOOCOMMERCE_ProcessEvents::xml_attribute($oFlds, "modelo"));
		$statusweb = trim(SAIT_WOOCOMMERCE_ProcessEvents::xml_attribute($oFlds, "statusweb"));
		$obs = trim(SAIT_WOOCOMMERCE_ProcessEvents::xml_attribute($oFlds, "obs"));
		// Si statusweb vaio no es modart completo
		if ( $statusweb === "")  {
					return SAIT_UTILS::SAIT_response(200, "statusweb null");
			}
		SAIT_WOOCOMMERCE()->price_service()->invalidate_sku($numart);
		// Los atributos MODART y las claves de sus eventos no siguen un unico patron.
		$category_source = SAIT_WOOCOMMERCE()->settings()->category_source_config();
		$category_key = trim(SAIT_WOOCOMMERCE_ProcessEvents::xml_attribute($oFlds, $category_source['article_attribute']));
		$category_mapping = $category_key === ''
			? null
			: SAIT_UTILS::SAIT_getClaves($category_source['mapping_table'], $category_key, null);
		$category_id = isset($category_mapping->wcid) ? array($category_mapping->wcid) : array();
		
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
		if (!isset($clave->wcid)) {
			$resolved = SAIT_WOOCOMMERCE()->product_resolver()->resolve($numart);
			if ($resolved['source'] === 'sku' && $resolved['product']) {
				$product_id = $resolved['product']->get_id();
				$mapping_id = SAIT_WOOCOMMERCE()->mapping_repository()->add('arts', $numart, $product_id);
				if ($mapping_id) {
					$clave = SAIT_WOOCOMMERCE()->mapping_repository()->find_product($numart);
				}
			}
		}

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
					SAIT_WOOCOMMERCE()->logger()->warning(
						'No se pudo asignar el codigo global al producto.',
						array('event' => 'MODART', 'sku' => $numart, 'error_code' => get_class($e))
					);
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
					$stock_result = SAIT_WOOCOMMERCE()->product_sync_service()->get_stock_from_sait($numart);
					$sait_stock = $stock_result['matched'] ? $stock_result['stock'] : 0;

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
			SAIT_WOOCOMMERCE()->logger()->warning(
				'No se pudo asignar el codigo global al producto.',
				array('event' => 'MODART', 'sku' => $numart, 'error_code' => get_class($e))
			);
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

		$stock_result = SAIT_WOOCOMMERCE()->product_sync_service()->get_stock_from_sait($numart);
		$sait_stock = $stock_result['matched'] ? $stock_result['stock'] : 0;

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
}
