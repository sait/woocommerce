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
			
			$Obs_activo = isset($SAIT_options['SAITNube_PedidoObs_Enabled']) && $SAIT_options['SAITNube_PedidoObs_Enabled'] === '1';
			$Direnvio_activo = isset($SAIT_options['SAITNube_PedidoDirenvio_Enabled']) && $SAIT_options['SAITNube_PedidoDirenvio_Enabled'] === '1';
			$Otrosdatos_activo = isset($SAIT_options['SAITNube_PedidoOtrosDatos_Enabled']) && $SAIT_options['SAITNube_PedidoOtrosDatos_Enabled'] === '1';
			if (!$Obs_activo) {
				$pedido->obs = self::SAIT_getObs($order);
			}
			if (!$Direnvio_activo) {
				$pedido->direnvio = self::SAIT_getDirEnvio($order);
			}
			if (!$Otrosdatos_activo) {
				$pedido->otrosdatos = self::SAIT_getOtrosDatos($order);
			}
				
			
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
			// buscar Cliente o Cliente Eventual en SAIT
			$pedido->numcli = SAIT_UTILS::SAIT_getClientebyemail($order->get_billing_email());
			if ($pedido->numcli == ""  ) {
				$pedido->numcliev = SAIT_UTILS::SAIT_getClienteEventualbyemail($order->get_billing_email());
			}
		}

		// no se encontro ningun cliente
		if ($pedido->numcli == "" && $pedido->numcliev == "" ) {
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
			$pedido->obs = self::SAIT_getObs($order);
			$pedido->direnvio = self::SAIT_getDirEnvio($order);
			$pedido->otrosdatos = self::SAIT_getOtrosDatos($order);
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
			$order = wc_get_order( 6138   );
			return self::SAIT_sendPedido($order,"1");
		}

	public static function SAIT_calcularPjeDescuentoItem($cantidad,$total,$precio){
		return round((($precio-($total/$cantidad))/$precio)*100,2);
	}
	 
/**
 * Obtiene OBS: tipo de entrega + forma de pago + notas
 */
public static function SAIT_getObs($order) {
    $shipping_method = trim($order->get_shipping_method());
    if (empty($shipping_method)) {
        $shipping_method = "SIN ENTREGA";
    }

    $payment_method_title = trim($order->get_payment_method_title());
    if (empty($payment_method_title)) {
        $payment_method_title = "SIN PAGO";
    }

    $customer_note = trim($order->get_customer_note());

    $obs = strtoupper($shipping_method . " Y " . $payment_method_title);

    if (!empty($customer_note)) {
        $obs .= "\r\n Obs: " . $customer_note;
    }

    return $obs;
}

/**
 * Obtiene DIR ENVIO en formato: calle^numero^colonia^ciudad^estado^c.p 00000
 */
public static function SAIT_getDirEnvio($order) {
    // Tomamos datos de shipping, y si no hay, usamos billing
    $address_1 = trim($order->get_shipping_address_1());
    $address_2 = trim($order->get_shipping_address_2());
    $city      = trim($order->get_shipping_city());
    $state     = trim($order->get_shipping_state());
    $postcode  = trim($order->get_shipping_postcode());
    $phone     = trim($order->get_billing_phone());

    if (empty($address_1)) $address_1 = trim($order->get_billing_address_1());
    if (empty($address_2)) $address_2 = trim($order->get_billing_address_2());
    if (empty($city))      $city      = trim($order->get_billing_city());
    if (empty($state))     $state     = trim($order->get_billing_state());
    if (empty($postcode))  $postcode  = trim($order->get_billing_postcode());

    // Validaciones mínimas
    if (empty($address_1)) $address_1 = "SIN CALLE";
    if (empty($address_2)) $address_2 = "SN"; // Número exterior
    if (empty($city))      $city      = "SIN CIUDAD";
    if (empty($state))     $state     = "SIN ESTADO";
    if (empty($postcode))  $postcode  = "00000";
    if (empty($phone))     $phone     = "SIN TELEFONO";

    // Construir en el orden correcto
    $dir = "1^WEB^".$address_1 . "^"   // CALLE
         . $address_2 . "^"   // NUMEXT
         . "^"    
         . $city . "^"
         . $state . "^"
         . $postcode . "^"
         . $phone;

    return strtoupper($dir);
}

/**
 * Obtiene otros datos en formato de texto multilinea para VFP
 */
public static function SAIT_getOtrosDatos($order) {
    $shipping_method = trim($order->get_shipping_method());
    if (empty($shipping_method)) {
        $shipping_method = "Recoger en sucursal";
    }
	
	$payment_method_title = trim($order->get_payment_method_title());
    if (empty($payment_method_title)) {
        $payment_method_title = "SIN PAGO";
    }

    $name  = trim($order->get_formatted_billing_full_name());
    $phone = trim($order->get_billing_phone());
    $email = trim($order->get_billing_email());

    if (empty($name))  $name  = "SIN NOMBRE";
    if (empty($phone)) $phone = "SIN TELEFONO";
    if (empty($email)) $email = "SIN CORREO";

    // Usamos \r\n para asegurar compatibilidad con VFP
    $otros = "Tipo de Entrega=" . $shipping_method . "\r\n"
           . "Metodo de pago=" . $payment_method_title . "\r\n"
           . "Nombre= " . $name . "\r\n"
           . "Telefono= " . $phone . "\r\n"
           . "Correo= " . $email . "\r\n"
           . "clinum=     0";

    return $otros;
}
	  
	 
	 
}