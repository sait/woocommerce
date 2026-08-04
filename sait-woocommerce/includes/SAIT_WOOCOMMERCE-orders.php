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
		return SAIT_WOOCOMMERCE()->document_service()->send_order($order, $formapago, $wait);
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
	return SAIT_WOOCOMMERCE()->document_service()->send_quote($order, $formapago, $wait);
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
		SAIT_WOOCOMMERCE()->order_delivery_state()->mark_pending($order, $formapago, $tipo, 'automatic');
		self::SAIT_marcarEnvioAutomaticoDisparado($order, $formapago, $tipo);
		SAIT_WOOCOMMERCE()->order_delivery_state()->mark_sending($order, $formapago, $tipo, 'automatic');
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
			SAIT_WOOCOMMERCE()->order_delivery_state()->mark_sending($order, "1", $tipo, 'manual');
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
		SAIT_WOOCOMMERCE()->order_delivery_state()->record_response($order, $response);
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
		return SAIT_WOOCOMMERCE_DocumentBuilder::discount_percentage($cantidad, $total, $precio);
	}


/**
 * Construye el campo DIR ENVIO esperado por SAIT a partir de shipping/billing.
 *
 * @param WC_Order $order Orden WooCommerce.
 * @return string Direccion en mayusculas con campos separados por ^.
 */
public static function SAIT_getDirEnvio($order) {
	return SAIT_WOOCOMMERCE_DocumentBuilder::shipping_address($order);
}

}
