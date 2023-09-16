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

 class SAIT_WOOCOMMERCE_Pedidos{

	// sendPedido()
	//  Manda el pedido a sait
	public static function SAIT_sendPedido($order_id, $order ){
// https://wordpress.stackexchange.com/questions/329009/stuck-with-wp-remote-post-sending-data-to-an-external-api-on-user-registration


		$pedido = new stdClass();
		$pedido->numdoc = $order->get_id();
		$pedido->fecha = $order->get_date_created();
		$pedido->numcli = "0";
		$pedido->mostrador = "HECTOR RAMIREZ
		AV. KINO Y CALLE 12 NO. 1006
		SAN LUIS RIO COLORADO, SONORA
		6535348800 ";
		$pedido->items = [];

		foreach ( $order->get_items() as $item_id => $item ) {
				$art = new stdClass();
				$art->cant = $item->get_quantity();
				$product = $item->get_product(); // see link above to get $product info
				$art->numart = $product->get_sku();
				$art->preciopub = $product->get_regular_price();
				$pedido->items[] = $art;
		}

		$url = 'https://provlimpieza2.saitnube.com/api/v2/ventas/pedidos';

    $args = array(
        'method' => 'POST',
        'timeout' => 45,
        'redirection' => 5,
        'httpversion' => '1.0',
        'sslverify' => false,
        'blocking' => false,
        'headers' => array(
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ),
        'body' => json_encode($pedido),
        'cookies' => array()
    );

    $request = wp_remote_post ($url, $args);

	}

}