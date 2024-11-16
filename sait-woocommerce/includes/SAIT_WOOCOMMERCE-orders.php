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

	
 class SAIT_WOOCOMMERCE_Orders{

	// sendPedido()
	//  Manda el pedido a sait
	public static function SAIT_sendPedido( $order,$formapago ){
    // https://wordpress.stackexchange.com/questions/329009/stuck-with-wp-remote-post-sending-data-to-an-external-api-on-user-registration
			$SAIT_options=get_option( 'opciones_sait' );
			$pedido = new stdClass();
			$pedido->numdoc = SAIT_SERIE.strval($order->get_id());
			$pedido->numcli = "";
			$pedido->numcliev = "";
			$pedido->numalm =  str_pad(SAIT_NUBE_NUMALM,2, " ", STR_PAD_LEFT);
			// Si tiene NumAlm configurado usar ese.
			$NumAlm = $SAIT_options['SAITNube_NumAlm'];
			if (isset($NumAlm) && !is_null($NumAlm)) {
				$pedido->numalm =  str_pad($NumAlm,2, " ", STR_PAD_LEFT);
			}
			$pedido->formapago = $formapago;
			$pedido->divisa = "P";
			$pedido->tc = 1;
			$pedido->items = [];
			$order_items_data = array_map( function($item){ return $item->get_data(); }, $order->get_items() );
    		$logger = wc_get_logger();
    		$logger->add("send-order-debug", json_encode($order_items_data));
			foreach ( $order->get_items() as $item_id => $item ) {
					$art = new stdClass();
					$art->cant = $item->get_quantity();
					$product = $item->get_product();
					$art->numart = $product->get_sku();
					$art->preciopub = (float)$product->get_regular_price();
					$art->pjedesc1 = self::SAIT_calcularPjeDescuentoItem($art->cant,(float)$item->get_total(),$art->preciopub);
					$pedido->items[] = $art;
			}
		$clave = SAIT_UTILS::SAIT_getClaves("clientes",null,$order->get_user_id());		
		if (isset($clave->clave)){
		 	$pedido->numcli =  str_pad($clave->clave,5, " ", STR_PAD_LEFT);
		}else{
			$pedido->numcli = SAIT_UTILS::SAIT_getClientebyemail($order->get_billing_email());
		}

		// no se encontro ningun cliente
		if ($pedido->numcli == "") {
				// aqui agregar el objeto clienteventual a pedido.
				$clienteeventual =  new stdClass();
				$clienteeventual->nomcliev  = $order->get_formatted_billing_full_name();
				$clienteeventual->calle = $order->get_billing_address_1();
				$clienteeventual->ciudad = $order->get_billing_city();
				$clienteeventual->estado = $order->get_billing_state();
				$clienteeventual->telefono = $order->get_billing_phone();
				$clienteeventual->email = $order->get_billing_email();
				$pedido->clievent = $clienteeventual;
		}
		
		return SAIT_UTILS::SAIT_PostNube("/api/v3/pedidos",$pedido);
	}

	public static function SAIT_sendCotizacion( $order ){
    // https://wordpress.stackexchange.com/questions/329009/stuck-with-wp-remote-post-sending-data-to-an-external-api-on-user-registration

			$SAIT_options=get_option( 'opciones_sait' );
			$pedido = new stdClass();
			$pedido->numdoc = SAIT_SERIE.strval($order->get_id());
			$date =	$order->get_date_created();
			$pedido->fecha = $date->date_i18n();
			$pedido->hora = date('H:i:s',$date->getTimestamp());
			$pedido->numcli = str_pad("0",5, " ", STR_PAD_LEFT);
			$pedido->numalm = SAIT_NUBE_NUMALM;
			// $NumAlm = $SAIT_options['SAITNube_NumAlm'];
			// if (isset($NumAlm) && !is_null($NumAlm)) {
			// 	$pedido->numalm = $NumAlm;
			// }
			$pedido->formapago = "1";
			$pedido->divisa = "P";
			$pedido->tc = 1;
			$pedido->mostrador = $order->get_formatted_billing_full_name()."\r\n\r\n".$order->get_billing_address_1()."\r\n".$order->get_billing_city().", ".$order->get_billing_state()."\r\n".$order->get_billing_phone();
			$pedido->items = [];
			$order_items_data = array_map( function($item){ return $item->get_data(); }, $order->get_items() );
    		$logger = wc_get_logger();
    		$logger->add("send-order-debug", json_encode($order_items_data));
			foreach ( $order->get_items() as $item_id => $item ) {
					$art = new stdClass();
					$art->cant = $item->get_quantity();
					$product = $item->get_product();
					$art->numart = $product->get_sku();
					$art->preciopub = (float)$product->get_regular_price();
					$art->pjedesc1 = self::SAIT_calcularPjeDescuentoItem($art->cant,(float)$item->get_total(),$art->preciopub);
					$pedido->items[] = $art;
			}
			
			return SAIT_UTILS::SAIT_PostNube("/api/v3/cotizaciones",$pedido);
	}



	// funcion para cuando los pedidos no fueron pagados.
	public static function SAIT_sendOrder($id_pedido,$formapago){
		
		$order = wc_get_order( $id_pedido );
		$SAIT_options=get_option( 'opciones_sait' );
		$tipo = $SAIT_options['SAITNube_TipoDoc'];
		if ($tipo==="P"){
			return self::SAIT_sendPedido($order,$formapago);
		}else{
			return self::SAIT_sendCotizacion($order);
		}
	}

	 
	public static function SAIT_sendPedidoTest(){
			$order = wc_get_order( 11823   );
			return self::SAIT_sendPedido($order,"1");
		}

	public static function SAIT_calcularPjeDescuentoItem($cantidad,$total,$precio){
		return round((($precio-($total/$cantidad))/$precio)*100,2);
	}
}