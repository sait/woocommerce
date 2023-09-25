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
 * En esta clase estan todas las funciones necesarias para procesar de una orden
 * @since      1.0.3
 * @package    SAIT_WOOCOMMERCE
 * @subpackage SAIT_WOOCOMMERCE/includes
 * @author     Ali Moreno <ali@saitenlinea.com>
 */

	
 class SAIT_WOOCOMMERCE_Pedidos{

	// sendPedido()
	//  Manda el pedido a sait
	public static function SAIT_sendPedido($order_id, $order ){
    // https://wordpress.stackexchange.com/questions/329009/stuck-with-wp-remote-post-sending-data-to-an-external-api-on-user-registration


			$pedido = new stdClass();
			$pedido->numdoc = SAIT_SERIE.strval($order->get_id());
			$date =	$order->get_date_created();
			$pedido->fecha = $date->date_i18n();
			$pedido->hora = date('H:i:s',$date->getTimestamp());
			$pedido->numcli = "0";
			$pedido->numalm = SAIT_NUBE_NUMALM;
			$pedido->formapago = "1";
			$pedido->divisa = "P";
			$pedido->tc = 1;
			$pedido->mostrador = $order->get_formatted_shipping_full_name()."\n".$order->get_shipping_address_1()."\n".$order->get_shipping_city().", ".$order->get_shipping_state()."\n".$order->get_shipping_phone();
			$pedido->items = [];
			foreach ( $order->get_items() as $item_id => $item ) {
					$art = new stdClass();
					$art->cant = $item->get_quantity();
					$product = $item->get_product();
					$art->numart = $product->get_sku();
					$art->unidad = "PZA";
					$art->precio = (float)$product->get_regular_price();
					$art->preciopub = (float)$product->get_regular_price();
					$pedido->items[] = $art;
			}

		$url = SAIT_NUBE_URL;
     
		$args = array(
			'method' => 'POST',
			'timeout' => 45,
			'redirection' => 5,
			'httpversion' => '1.0',
			'sslverify' => false,
			'blocking' => false,
			'headers' => array(
				'X-Apikey' => SAIT_APIKEY,
				'Content-Type' => 'application/json',
				'Accept' => 'application/json',
			),
			'body' => json_encode($pedido),
			'cookies' => array()
		);
		
    	return wp_remote_post($url, $args);
	}




	public static function SAIT_sendPedidoTest(){
			$order = wc_get_order( 4526 );
			return self::SAIT_sendPedido("",$order);
		}
}