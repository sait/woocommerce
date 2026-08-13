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
		// Si statusweb viene vacio no es un MODART completo.
		if ( $statusweb === "")  {
					return SAIT_UTILS::SAIT_response(200, "statusweb null");
		}
		SAIT_WOOCOMMERCE()->price_service()->invalidate_sku($numart);
		$settings = SAIT_WOOCOMMERCE()->settings();
		$sync_category = $settings->category_source() !== 'none';
		$sync_category = (bool) apply_filters(
			'sait_woocommerce_modart_sync_category',
			$sync_category,
			$numart,
			$oXml
		);
		$sync_model = (bool) apply_filters(
			'sait_woocommerce_modart_sync_model',
			$settings->is_enabled(SAIT_WOOCOMMERCE_Settings::SYNC_MODEL_KEY),
			$numart,
			$oXml
		);
		$category_id = array();
		if ($sync_category) {
			// Los atributos MODART y las claves de sus eventos no siguen un unico patron.
			$category_source = $settings->category_source_config();
			$category_key = trim(SAIT_WOOCOMMERCE_ProcessEvents::xml_attribute($oFlds, $category_source['article_attribute']));
			$category_mapping = $category_key === ''
				? null
				: SAIT_UTILS::SAIT_getClaves($category_source['mapping_table'], $category_key, null);
			$category_id = isset($category_mapping->wcid) ? array($category_mapping->wcid) : array();
		}

		$clave = SAIT_UTILS::SAIT_getClaves("arts", $numart, null);

		// Si statusweb = 0, vacío o null → eliminar el producto
		if ($statusweb === "0" || $statusweb === "" || $statusweb === null) {
				if (isset($clave->wcid)) {
						wp_trash_post($clave->wcid);
				}
				return SAIT_UTILS::SAIT_response(200, "OK");
		}

		if (!isset($clave->wcid)) {
			$resolved = SAIT_WOOCOMMERCE()->product_resolver()->resolve($numart);
			if ($resolved['source'] === 'sku' && $resolved['product']) {
				$existing_sku_mode = apply_filters(
					'sait_woocommerce_modart_existing_sku_mode',
					'link_and_sync',
					$resolved['product'],
					$numart,
					$oXml
				);
				if ($existing_sku_mode === 'ignore') {
					return SAIT_UTILS::SAIT_response(200, "ART IGNORADO SKU EXISTENTE");
				}
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
		
				if ($sync_category && !empty($category_id)) {
						$product->set_category_ids($category_id);
				}
		
				if ($sync_model && !empty($modelo)) {
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
					if ($stock_result['matched']) {
						$product->set_stock_quantity($stock_result['stock']);
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
		if ($sync_category && !empty($category_id)) {
			$product->set_category_ids($category_id);
		}
		
		if ($sync_model && !empty($modelo)) {
			$product->set_short_description("Modelo: " . $modelo);
		}

		if (!empty($obs)) {
			$product->set_description($obs);
		}

		$stock_result = SAIT_WOOCOMMERCE()->product_sync_service()->get_stock_from_sait($numart);
		if ($stock_result['matched']) {
			$product->set_stock_quantity($stock_result['stock']);
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
