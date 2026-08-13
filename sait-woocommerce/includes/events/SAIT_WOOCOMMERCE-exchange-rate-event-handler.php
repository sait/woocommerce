<?php

/** Procesa eventos de tipo de cambio SAIT. */
class SAIT_WOOCOMMERCE_ExchangeRateEventHandler
{
	public static function ACTTC($oXml){
		$settings = SAIT_WOOCOMMERCE()->settings();
		$OldTC = $settings->get('SAITNube_TipoCambio', '');
		$NewTC=SAIT_WOOCOMMERCE_ProcessEvents::xml_attribute($oXml->action[0]->flds[0],"tc");
		if ($OldTC == $NewTC){
			return SAIT_UTILS::SAIT_response(200,"same TC");
		}
		$settings->set('SAITNube_TipoCambio', $NewTC);
		$api_response = SAIT_UTILS::SAIT_GetNube("/api/v3/articulos?divisa=D&statusweb=1&limit=10000");
		$result = SAIT_UTILS::SAIT_getResult($api_response);
		if (empty($result)){
				return SAIT_UTILS::SAIT_response(200,"Upd TC");
		}
		foreach ($result as $row) {
			SAIT_WOOCOMMERCE()->product_sync_service()->sync_price_from_row(
				trim($row['numart']),
				$row,
				'ACTTC'
			);
		}

		return SAIT_UTILS::SAIT_response(200,"Upd TC");
	}
}

