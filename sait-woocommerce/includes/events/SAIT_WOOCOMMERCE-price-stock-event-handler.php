<?php

/** Procesa eventos de precios y existencias SAIT. */
class SAIT_WOOCOMMERCE_PriceStockEventHandler
{
	public static function ACTEXISGBL($oXml){
		$SAIT_options = SAIT_WOOCOMMERCE()->settings()->all();
		$NumAlm = $SAIT_options['SAITNube_NumAlm'];
		if (isset($NumAlm) && !is_null($NumAlm)) {
			return SAIT_UTILS::SAIT_response(200,"STOCK ERR ACTEXISGBL");
		}
		foreach ($oXml->action as $action) {
			$clave = SAIT_UTILS::SAIT_getClaves("arts",trim(SAIT_WOOCOMMERCE_ProcessEvents::xml_attribute($action->keys[0],"numart")),null);
			if (isset($clave->wcid)) {
				$product = wc_get_product( $clave->wcid );
				if ($product===false) {
					return SAIT_UTILS::SAIT_response(200,"ART NO EXISTE");
				}
				$product->set_stock_quantity(SAIT_WOOCOMMERCE_ProcessEvents::xml_attribute($action->flds[0],"existencia"));
				$product->save();
			}
		}

		return SAIT_UTILS::SAIT_response(200,"STOCK UPD");
	}

	public static function ACTEXIST($oXml){
		$settings = SAIT_WOOCOMMERCE()->settings();
		$NumAlm = $settings->get('SAITNube_NumAlm', '');
		$ExistAlm_activo = $settings->is_enabled('SAITNube_ExistAlm_enabled');
		if (!$ExistAlm_activo && (!isset($NumAlm) || is_null($NumAlm))) {
			return SAIT_UTILS::SAIT_response(200, "STOCK ERR ACTEXIST Not set");
		}
		
		foreach ($oXml->action as $action) {
			$numart = trim(SAIT_WOOCOMMERCE_ProcessEvents::xml_attribute($action->keys[0],"numart"));
			$NumAlmEvent = trim(SAIT_WOOCOMMERCE_ProcessEvents::xml_attribute($action->keys[0],"numalm"));
			if (!$ExistAlm_activo && $NumAlm != $NumAlmEvent) {
				return SAIT_UTILS::SAIT_response(200, "STOCK ERR ACTEXIST");
			}

			$result = $ExistAlm_activo
				? SAIT_WOOCOMMERCE()->product_sync_service()->sync_stock_from_sait($numart, 'ACTEXIST')
				: SAIT_WOOCOMMERCE()->product_sync_service()->sync_stock_from_rows(
					$numart,
					array(array(
						'numalm' => $NumAlmEvent,
						'existencia' => SAIT_WOOCOMMERCE_ProcessEvents::xml_attribute($action->flds[0], 'existencia'),
					)),
					'ACTEXIST'
				);
			if ($result['estado'] === 'ignorado') {
				return SAIT_UTILS::SAIT_response(200, $result['mensaje']);
			}
		}
		
		return SAIT_UTILS::SAIT_response(200,"STOCK UPD ACTEXIST");
	}

	public static function ACTPRECIO($oXml){
		$numart = trim(SAIT_WOOCOMMERCE_ProcessEvents::xml_attribute($oXml->action[0]->keys[0], "numart"));
		

		$productflds = $oXml->action[0]->flds[0];

		// Verificar si hay precios normales
		$tiene_precios_normales = false;
		foreach (["preciopub", "precio1", "precio2", "precio3", "precio4", "precio5"] as $campo) {
			$valor = SAIT_WOOCOMMERCE_ProcessEvents::xml_attribute($productflds, $campo);
			if ($valor !== "" && is_numeric($valor) && floatval($valor) > 0) {
				$tiene_precios_normales = true;
				break;
			}
		}

		if (!$tiene_precios_normales) {
			return SAIT_UTILS::SAIT_response(200, "IGNORADO (ppubv*)");
		}



		
		$row = array();
		foreach (array('preciopub', 'precio1', 'precio2', 'precio3', 'precio4', 'precio5') as $field) {
			$value = SAIT_WOOCOMMERCE_ProcessEvents::xml_attribute($productflds, $field);
			if ($value !== '') {
				$row[$field] = $value;
			}
		}
		$result = SAIT_WOOCOMMERCE()->product_sync_service()->sync_price_from_event($numart, $row, 'ACTPRECIO');

		return SAIT_UTILS::SAIT_response(200, $result['mensaje']);
	}
}

