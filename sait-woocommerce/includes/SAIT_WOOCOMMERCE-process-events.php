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
	public static function SAIT_processEvent($event){
		if (!$event instanceof SAIT_WOOCOMMERCE_Event) {
			$type = self::xml_attribute($event, 'type');
			$event = new SAIT_WOOCOMMERCE_Event($type, $event);
		}

		return SAIT_WOOCOMMERCE()->event_router()->route($event);
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
		return SAIT_WOOCOMMERCE_ProductEventHandler::MODART($oXml);
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
		return SAIT_WOOCOMMERCE_PriceStockEventHandler::ACTEXISGBL($oXml);
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
		return SAIT_WOOCOMMERCE_PriceStockEventHandler::ACTEXIST($oXml);
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
		return SAIT_WOOCOMMERCE_PriceStockEventHandler::ACTPRECIO($oXml);
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
		return SAIT_WOOCOMMERCE_CategoryEventHandler::MODCATEGORIAWC($oXml,$tabla,$numcat,$nomcat);
	}

	public static function MODFAMILIA($oXml){
		return SAIT_WOOCOMMERCE_CategoryEventHandler::MODFAMILIA($oXml);
	}

	public static function MODDEPTO($oXml){
		return SAIT_WOOCOMMERCE_CategoryEventHandler::MODDEPTO($oXml);
	}

	public static function MODLINEA($oXml){
		return SAIT_WOOCOMMERCE_CategoryEventHandler::MODLINEA($oXml);
	}

	public static function MODCATEGO($oXml){
		return SAIT_WOOCOMMERCE_CategoryEventHandler::MODCATEGO($oXml);
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
		return SAIT_WOOCOMMERCE_ExchangeRateEventHandler::ACTTC($oXml);
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
		return SAIT_WOOCOMMERCE_CustomerEventHandler::MODCLI($oXml);
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
