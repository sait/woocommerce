<?php

/** Procesa eventos de clasificaciones SAIT como categorias WooCommerce. */
class SAIT_WOOCOMMERCE_CategoryEventHandler
{
	public static function MODCATEGORIAWC($oXml,$tabla,$numcat,$nomcat){
		$clave = SAIT_UTILS::SAIT_getClaves($tabla,trim(SAIT_WOOCOMMERCE_ProcessEvents::xml_attribute($oXml->action[0]->keys[0],$numcat)),null);
		$nombre = trim(SAIT_WOOCOMMERCE_ProcessEvents::xml_attribute($oXml->action[0]->flds[0],$nomcat));
		if (empty($nombre)) {
			return SAIT_UTILS::SAIT_response(200,"linea vacia");
		}
		if (!isset($clave->wcid)) {
			$term_data = wp_insert_term(
					trim(SAIT_WOOCOMMERCE_ProcessEvents::xml_attribute($oXml->action[0]->flds[0],$nomcat)), 
					'product_cat'
			);
			if( is_wp_error( $term_data ) ) {
				return SAIT_UTILS::SAIT_response(500,$term_data->get_error_message());
			}
			SAIT_UTILS::SAIT_insertClaves($tabla,trim(SAIT_WOOCOMMERCE_ProcessEvents::xml_attribute($oXml->action[0]->keys[0],$numcat)),$term_data['term_id']);
			return SAIT_UTILS::SAIT_response(200,"ADD Linea");
		}else{
			$term = get_term($clave->wcid);
			if (is_wp_error($term) ||  is_null($term) ){
				// no existe una categoria con ese ID
				// buscar por nombre para evitar conflictos
				$term = get_term_by('name', trim(SAIT_WOOCOMMERCE_ProcessEvents::xml_attribute($oXml->action[0]->flds[0],$nomcat)), 'product_cat');
				if (isset($term->term_id)) {
                    // si existe cambio de id
                    SAIT_UTILS::SAIT_deleteClaves($clave->id);
					SAIT_UTILS::SAIT_insertClaves($tabla,trim(SAIT_WOOCOMMERCE_ProcessEvents::xml_attribute($oXml->action[0]->keys[0],$numcat)),$term->term_id);
					return SAIT_UTILS::SAIT_response(200,"UPD ".$tabla);
				} 
				// cat ya no existe borrarlo de tabla SAIT
				// para evitar conflictos
				$clave = SAIT_UTILS::SAIT_getClaves($tabla,trim(SAIT_WOOCOMMERCE_ProcessEvents::xml_attribute($oXml->action[0]->keys[0],$numcat)),null);
				SAIT_UTILS::SAIT_deleteClaves($clave->id);
				$term_data = wp_insert_term(
						trim(SAIT_WOOCOMMERCE_ProcessEvents::xml_attribute($oXml->action[0]->flds[0],$nomcat)), 
						'product_cat'
				);
				if( is_wp_error( $term_data ) ) {
					return SAIT_UTILS::SAIT_response(500,$term_data->get_error_message());
				}
				SAIT_UTILS::SAIT_insertClaves($tabla,trim(SAIT_WOOCOMMERCE_ProcessEvents::xml_attribute($oXml->action[0]->keys[0],$numcat)),$term_data['term_id']);
				return SAIT_UTILS::SAIT_response(200,"ADD ".$tabla);
			}
			$term_data = wp_update_term($clave->wcid,
				'product_cat',
				array(
					'name' => trim(SAIT_WOOCOMMERCE_ProcessEvents::xml_attribute($oXml->action[0]->flds[0],$nomcat))
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
}

