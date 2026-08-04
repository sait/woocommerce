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

	/**
	 * Construye y envia un pedido WooCommerce al endpoint de pedidos de SAIT.
	 *
	 * @param WC_Order $order Orden WooCommerce origen.
	 * @param string $formapago Codigo de forma de pago que espera SAIT.
	 * @param bool $wait Si es true espera la respuesta HTTP de SAIT.
	 * @return array|WP_Error Respuesta de wp_remote_post() o error de WordPress.
	 *
	 * Acciones que realiza: consulta articulos/clientes en SAIT, puede ejecutar personalizacion
	 * del pedido y realiza POST a /api/v3/pedidos.
	 */
	public static function SAIT_sendPedido( $order,$formapago,$wait = false ){
    // https://wordpress.stackexchange.com/questions/329009/stuck-with-wp-remote-post-sending-data-to-an-external-api-on-user-registration
			$SAIT_options = SAIT_WOOCOMMERCE()->settings()->all();
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
			$pedido->fentrega = date("Ymd"); // 20251113
			$pedido->hentrega = date("H:i"); // 15:27
			
			$Obs_activo = isset($SAIT_options['SAITNube_PedidoObs_enabled']) && $SAIT_options['SAITNube_PedidoObs_enabled'] === '1';
			$Direnvio_activo = isset($SAIT_options['SAITNube_PedidoDirenvio_enabled']) && $SAIT_options['SAITNube_PedidoDirenvio_enabled'] === '1';
			$FuncionPersonalizadaPedido_activo = isset($SAIT_options['SAITNube_FuncionPersonalizadaPedido_enabled']) && $SAIT_options['SAITNube_FuncionPersonalizadaPedido_enabled'] === '1';
			if ($Obs_activo) {
				$pedido->obs = trim($order->get_customer_note());
			}
			if ($Direnvio_activo) {
				$pedido->direnvio = self::SAIT_getDirEnvio($order);
			}

				
			
			SAIT_WOOCOMMERCE()->logger()->info(
				'Preparando pedido para SAIT.',
				array(
					'order_id'      => $order->get_id(),
					'document_type' => 'P',
					'item_count'    => count($order->get_items()),
				)
			);
			foreach ( $order->get_items() as $item_id => $item ) {
					$art = new stdClass();
					$art->cant = $item->get_quantity();
					$product = $item->get_product();
					$art->numart = $product->get_sku();
					$api_response = null;
					$api_result = null;
					$intentos = 0;
					$max_intentos = 3;
					while (!isset($api_result["unidad"]) && $intentos < $max_intentos) {
							if ($intentos > 0) {
									usleep($intentos * 500000); // 0.5s, 1s, 1.5s
							}
							$api_response = SAIT_UTILS::SAIT_GetNube("/api/v3/articulos/".$art->numart, false);
							$api_result = SAIT_UTILS::SAIT_getResult($api_response);
							$intentos++;
					}
					if (!isset($api_result['unidad'])) {
						SAIT_WOOCOMMERCE()->logger()->warning(
							'No se obtuvo la unidad del articulo para el pedido.',
							array(
								'order_id' => $order->get_id(),
								'sku'      => $art->numart,
								'attempt'  => $intentos,
							)
						);
					}
					$art->unidad = isset($api_result["unidad"]) ? $api_result["unidad"] : "";
					$art->preciopub =  (float)$product->get_regular_price();
					$art->precio = (float)$product->get_regular_price();
					$art->pjedesc1 = self::SAIT_calcularPjeDescuentoItem($art->cant,(float)$item->get_total(),$art->preciopub);
					$pedido->items[] = $art;
			}
		self::SAIT_applyCustomer($pedido, $order);

		if ($FuncionPersonalizadaPedido_activo) {
			$pedido = SAIT_PERSONALIZADO::SAIT_FuncionPersonalizaPostPedido($pedido,$order);
		}

		//$api_response =json_decode( wp_remote_retrieve_body( SAIT_UTILS::SAIT_PostNube("/api/v3/pedidos?dryrun=true",$pedido,true)) );
		//if   ($api_response->result=="OK"){
			// Enviar pedido sin esperar respuesta
			//SAIT_UTILS::SAIT_PostNube("/api/v3/pedidos",$pedido,false);
		//}	
			return SAIT_UTILS::SAIT_PostNube("/api/v3/pedidos",$pedido,$wait);
	}

	/**
	 * Construye y envia una cotizacion WooCommerce al endpoint de cotizaciones de SAIT.
	 *
	 * @param WC_Order $order Orden WooCommerce origen.
	 * @param string $formapago Codigo de forma de pago que espera SAIT.
	 * @param bool $wait Si es true espera la respuesta HTTP de SAIT.
	 * @return array|WP_Error Respuesta de wp_remote_post() o error de WordPress.
	 *
	 * Acciones que realiza: consulta articulos/clientes en SAIT, puede ejecutar personalizacion
	 * del documento y realiza POST a /api/v3/cotizaciones.
	 */
public static function SAIT_sendCotizacion( $order,$formapago,$wait = false ){
    // https://wordpress.stackexchange.com/questions/329009/stuck-with-wp-remote-post-sending-data-to-an-external-api-on-user-registration

		$SAIT_options = SAIT_WOOCOMMERCE()->settings()->all();
			$cotizacion = new stdClass();
			$cotizacion->numdoc = SAIT_SERIE.strval($order->get_id());
			$date =	$order->get_date_created();
			$cotizacion->fecha = $date->date_i18n();
			$cotizacion->hora = date('H:i:s',$date->getTimestamp());
			$cotizacion->numcli = "";
			$cotizacion->numcliev = "";
			$cotizacion->numalm =  str_pad(SAIT_NUBE_NUMALM,2, " ", STR_PAD_LEFT);
			// Si tiene NumAlm configurado usar ese.
			$NumAlm = $SAIT_options['SAITNube_NumAlm'];
			if (isset($NumAlm) && !is_null($NumAlm)) {
				$cotizacion->numalm =  str_pad($NumAlm,2, " ", STR_PAD_LEFT);
			}
			$cotizacion->formapago = $formapago;
			$cotizacion->divisa = "P";
			$cotizacion->tc = 1;
			$cotizacion->items = [];
			$cotizacion->fentrega = date("Ymd"); // 20251113
			$cotizacion->hentrega = date("H:i"); // 15:27
		
		$Obs_activo = isset($SAIT_options['SAITNube_PedidoObs_enabled']) && $SAIT_options['SAITNube_PedidoObs_enabled'] === '1';
		$Direnvio_activo = isset($SAIT_options['SAITNube_PedidoDirenvio_enabled']) && $SAIT_options['SAITNube_PedidoDirenvio_enabled'] === '1';
		$FuncionPersonalizadaPedido_activo = isset($SAIT_options['SAITNube_FuncionPersonalizadaPedido_enabled']) && $SAIT_options['SAITNube_FuncionPersonalizadaPedido_enabled'] === '1';
		if ($Obs_activo) {
			$cotizacion->obs = trim($order->get_customer_note());
		}
		if ($Direnvio_activo) {
			$cotizacion->direnvio = self::SAIT_getDirEnvio($order);
		}

		SAIT_WOOCOMMERCE()->logger()->info(
			'Preparando cotizacion para SAIT.',
			array(
				'order_id'      => $order->get_id(),
				'document_type' => 'Q',
				'item_count'    => count($order->get_items()),
			)
		);


		foreach ( $order->get_items() as $item_id => $item ) {
				$art = new stdClass();
				$art->cant = $item->get_quantity();
				$product = $item->get_product();
				$art->numart = $product->get_sku();
				$art->preciopub =  (float)$product->get_regular_price();
				$api_response = null;
				$api_result = null;
				$intentos = 0;
				$max_intentos = 3;
				while (!isset($api_result["unidad"]) && $intentos < $max_intentos) {
						if ($intentos > 0) {
								usleep($intentos * 500000); // 0.5s, 1s, 1.5s
						}
						$api_response = SAIT_UTILS::SAIT_GetNube("/api/v3/articulos/".$art->numart, false);
						$api_result = SAIT_UTILS::SAIT_getResult($api_response);
						$intentos++;
				}
				if (!isset($api_result['unidad'])) {
					SAIT_WOOCOMMERCE()->logger()->warning(
						'No se obtuvo la unidad del articulo para la cotizacion.',
						array(
							'order_id' => $order->get_id(),
							'sku'      => $art->numart,
							'attempt'  => $intentos,
						)
					);
				}
				$art->unidad = isset($api_result["unidad"]) ? $api_result["unidad"] : "";
				$art->precio = (float)$product->get_regular_price();
				$art->pjedesc1 = self::SAIT_calcularPjeDescuentoItem($art->cant,(float)$item->get_total(),$art->preciopub);
				$cotizacion->items[] = $art;
		}
	self::SAIT_applyCustomer($cotizacion, $order);

	if ($FuncionPersonalizadaPedido_activo) {
		$cotizacion = SAIT_PERSONALIZADO::SAIT_FuncionPersonalizaPostPedido($cotizacion,$order);
	}

	//$api_response =json_decode( wp_remote_retrieve_body( SAIT_UTILS::SAIT_PostNube("/api/v3/cotizaciones?dryrun=true",$cotizacion,true)) );
	//if   ($api_response->result=="OK"){
		// Enviar cotizacion sin esperar respuesta
		//SAIT_UTILS::SAIT_PostNube("/api/v3/cotizaciones",$cotizacion,false);
	//}	
		return SAIT_UTILS::SAIT_PostNube("/api/v3/cotizaciones",$cotizacion,$wait);
}



	/**
	 * Envia una orden a SAIT desde hooks automaticos de WooCommerce.
	 *
	 * @param int $id_pedido ID de la orden WooCommerce.
	 * @param string $formapago Codigo de forma de pago a enviar.
	 * @return WP_REST_Response|array|WP_Error Respuesta local o respuesta del POST a SAIT.
	 *
	 * Acciones que realiza: marca la orden como envio automatico disparado antes del POST para
	 * evitar duplicados entre hooks de WooCommerce.
	 */
	public static function SAIT_sendOrder($id_pedido,$formapago){
		
		$order = wc_get_order( $id_pedido );
		if (!$order) {
			return SAIT_UTILS::SAIT_response(404, "Pedido no existe");
		}
		$SAIT_options = SAIT_WOOCOMMERCE()->settings()->all();
		$tipo = $SAIT_options['SAITNube_TipoDoc'];
		if (self::SAIT_envioAutomaticoDisparado($order)) {
			return SAIT_UTILS::SAIT_response(200, "SAIT ENVIO YA DISPARADO");
		}
		self::SAIT_marcarEnvioAutomaticoDisparado($order, $formapago, $tipo);
		if ($tipo==="P"){
			return self::SAIT_sendPedido($order,$formapago);
		}else{
			return self::SAIT_sendCotizacion($order,$formapago);
		}
	}

	/**
	 * Indica si una orden ya tuvo un envio automatico disparado.
	 *
	 * @param WC_Order $order Orden WooCommerce.
	 * @return bool True si el meta _sait_envio_disparado esta marcado.
	 */
	public static function SAIT_envioAutomaticoDisparado($order){
		return $order->get_meta('_sait_envio_disparado') === 'yes';
	}

	/**
	 * Guarda la marca idempotente del envio automatico.
	 *
	 * @param WC_Order $order Orden WooCommerce.
	 * @param string $formapago Codigo de forma de pago enviado.
	 * @param string $tipo Tipo de documento configurado en SAITNube_TipoDoc.
	 * @return void
	 *
	 * Acciones que realiza: actualiza metadatos de la orden y la guarda.
	 */
	public static function SAIT_marcarEnvioAutomaticoDisparado($order, $formapago, $tipo){
		$order->update_meta_data('_sait_envio_disparado', 'yes');
		$order->update_meta_data('_sait_envio_disparado_at', current_time('mysql'));
		$order->update_meta_data('_sait_envio_formapago', $formapago);
		$order->update_meta_data('_sait_envio_tipodoc', $tipo);
		$order->save();
	}

	/**
	 * Reenvia manualmente una orden a SAIT y registra el resultado del intento.
	 *
	 * @param int $id_pedido ID de la orden WooCommerce.
	 * @return WP_REST_Response Respuesta con estado, codigo HTTP y mensaje de SAIT.
	 *
	 * Acciones que realiza: omite la marca idempotente automatica, espera respuesta de SAIT
	 * y guarda metadatos del ultimo envio manual.
	 */
	public static function SAIT_reenviarPedido($id_pedido){
			$order = wc_get_order( $id_pedido );
			if (!$order) {
				return SAIT_UTILS::SAIT_response(404, "Pedido no existe");
			}
			$SAIT_options = SAIT_WOOCOMMERCE()->settings()->all();
			$tipo = $SAIT_options['SAITNube_TipoDoc'];
			if ($tipo==="P"){
				$response = self::SAIT_sendPedido($order,"1",true);
			}else{
				$response = self::SAIT_sendCotizacion($order,"1",true);
			}
			$resultado = self::SAIT_registrarResultadoEnvio($order, $response, $tipo, "1", "manual");
			return self::SAIT_responderResultadoEnvio($resultado);
		}

	/**
	 * Alias legacy para compatibilidad con integraciones que llaman testpedido.
	 *
	 * @param int $id_pedido ID de la orden WooCommerce.
	 * @return WP_REST_Response Respuesta del reenvio manual.
	 */
	public static function SAIT_sendPedidoTest($id_pedido){
			return self::SAIT_reenviarPedido($id_pedido);
		}

	/**
	 * Clasifica y persiste el resultado de un envio a SAIT.
	 *
	 * @param WC_Order $order Orden WooCommerce.
	 * @param array|WP_Error $response Respuesta de wp_remote_post() o error.
	 * @param string $tipo Tipo de documento enviado.
	 * @param string $formapago Forma de pago enviada.
	 * @param string $modo Origen del envio: automatico o manual.
	 * @return array Estado normalizado con estado, status_code y message.
	 *
	 * Acciones que realiza: actualiza metadatos _sait_ultimo_* en la orden y la guarda.
	 */
	public static function SAIT_registrarResultadoEnvio($order, $response, $tipo, $formapago, $modo){
		$is_error = is_wp_error($response);
		$status_code = $is_error ? 0 : (int) wp_remote_retrieve_response_code($response);
		$message = $is_error ? $response->get_error_message() : wp_remote_retrieve_body($response);

		if ($status_code === 201) {
			$estado = 'enviado';
		} elseif ($is_error || $status_code === 0 || $status_code >= 500) {
			$estado = 'reintento_requerido';
		} else {
			$estado = 'error';
		}

		$log_context = array(
			'order_id'      => $order->get_id(),
			'status_code'   => $status_code,
			'mode'          => $modo,
			'document_type' => $tipo,
			'error_code'    => $estado,
		);
		if ($estado === 'enviado') {
			SAIT_WOOCOMMERCE()->logger()->info('Documento enviado a SAIT.', $log_context);
		} else {
			SAIT_WOOCOMMERCE()->logger()->warning('El envio del documento a SAIT requiere revision.', $log_context);
		}

		$order->update_meta_data('_sait_ultimo_envio_estado', $estado);
		$order->update_meta_data('_sait_ultimo_status_code', $status_code);
		$order->update_meta_data('_sait_ultimo_envio_at', current_time('mysql'));
		$order->update_meta_data('_sait_ultimo_envio_formapago', $formapago);
		$order->update_meta_data('_sait_ultimo_envio_tipodoc', $tipo);
		$order->update_meta_data('_sait_ultimo_envio_modo', $modo);

		if ($estado === 'enviado') {
			$order->delete_meta_data('_sait_ultimo_error');
		} else {
			$order->update_meta_data('_sait_ultimo_error', substr((string) $message, 0, 1000));
		}

		$order->save();

		return array(
			'estado' => $estado,
			'status_code' => $status_code,
			'message' => $message,
		);
	}

	/**
	 * Convierte el resultado normalizado de envio en una respuesta REST.
	 *
	 * @param array $resultado Estado devuelto por SAIT_registrarResultadoEnvio().
	 * @return WP_REST_Response Respuesta con el mismo codigo HTTP de SAIT cuando existe.
	 */
	public static function SAIT_responderResultadoEnvio($resultado){
		$status_code = !empty($resultado['status_code']) ? (int) $resultado['status_code'] : 500;
		return SAIT_UTILS::SAIT_response($status_code, array(
			'estado' => $resultado['estado'],
			'status_code' => $resultado['status_code'],
			'message' => $resultado['message'],
		));
	}

	public static function SAIT_calcularPjeDescuentoItem($cantidad,$total,$precio){
		return round((($precio-($total/$cantidad))/$precio)*100,2);
	}

	/**
	 * Aplica al documento una sola representacion de cliente SAIT.
	 *
	 * @param object   $document Documento en construccion.
	 * @param WC_Order $order Orden WooCommerce.
	 * @return void
	 */
	private static function SAIT_applyCustomer($document, $order){
		$customer = SAIT_WOOCOMMERCE()->customer_resolver()->resolve($order);
		$document->numcli = $customer['numcli'];
		$document->numcliev = $customer['numcliev'];
		if ($customer['clievent'] !== null) {
			$document->clievent = $customer['clievent'];
		}
	}
	 

/**
 * Construye el campo DIR ENVIO esperado por SAIT a partir de shipping/billing.
 *
 * @param WC_Order $order Orden WooCommerce.
 * @return string Direccion en mayusculas con campos separados por ^.
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

}
