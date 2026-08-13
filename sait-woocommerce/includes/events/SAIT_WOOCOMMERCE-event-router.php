<?php

/** Enruta eventos validados a handlers sin contener reglas de negocio. */
class SAIT_WOOCOMMERCE_EventRouter
{
	public function route($event)
	{
		$xml = $event->xml();
		switch ($event->type()) {
			case 'MODART':
				return SAIT_WOOCOMMERCE_ProductEventHandler::MODART($xml);
			case 'ACTEXISGBL':
				return SAIT_WOOCOMMERCE_PriceStockEventHandler::ACTEXISGBL($xml);
			case 'ACTEXIST':
				return SAIT_WOOCOMMERCE_PriceStockEventHandler::ACTEXIST($xml);
			case 'ACTPRECIO':
				return SAIT_WOOCOMMERCE_PriceStockEventHandler::ACTPRECIO($xml);
			case 'MODFAMILIA':
				return SAIT_WOOCOMMERCE_CategoryEventHandler::MODFAMILIA($xml);
			case 'MODDEPTO':
				return SAIT_WOOCOMMERCE_CategoryEventHandler::MODDEPTO($xml);
			case 'MODLINEA':
				return SAIT_WOOCOMMERCE_CategoryEventHandler::MODLINEA($xml);
			case 'MODCATEGO':
				return SAIT_WOOCOMMERCE_CategoryEventHandler::MODCATEGO($xml);
			case 'ACTTC':
				return SAIT_WOOCOMMERCE_ExchangeRateEventHandler::ACTTC($xml);
			case 'MODCLI':
				return SAIT_WOOCOMMERCE_CustomerEventHandler::MODCLI($xml);
			default:
				return SAIT_UTILS::SAIT_response(200, 'OK');
		}
	}
}

