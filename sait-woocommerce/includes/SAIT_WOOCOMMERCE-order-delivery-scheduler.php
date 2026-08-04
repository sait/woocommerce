<?php

/**
 * Programa y procesa entregas de documentos mediante Action Scheduler.
 */
class SAIT_WOOCOMMERCE_OrderDeliveryScheduler
{
	const ACTION = 'sait_woocommerce_send_order_document';
	const GROUP = 'sait-woocommerce';

	private $state;

	public function __construct($state)
	{
		$this->state = $state;
	}

	/**
	 * Encola una sola entrega por orden y forma de pago.
	 *
	 * @return array{queued:bool,message:string}
	 */
	public function enqueue($order_id, $payment_method)
	{
		$order = wc_get_order($order_id);
		if (!$order) {
			return array('queued' => false, 'message' => 'Pedido no existe');
		}

		$status = $this->state->status($order);
		if (in_array($status, array(
			SAIT_WOOCOMMERCE_OrderDeliveryState::PENDING,
			SAIT_WOOCOMMERCE_OrderDeliveryState::SENDING,
			SAIT_WOOCOMMERCE_OrderDeliveryState::SENT,
		), true)) {
			return array('queued' => false, 'message' => 'SAIT ENVIO YA PROGRAMADO');
		}

		$options = SAIT_WOOCOMMERCE()->settings()->all();
		$document_type = isset($options['SAITNube_TipoDoc']) ? $options['SAITNube_TipoDoc'] : 'P';
		$args = array((int) $order_id, (string) $payment_method);
		if ($this->is_scheduled($args)) {
			return array('queued' => false, 'message' => 'SAIT ENVIO YA PROGRAMADO');
		}

		$this->state->mark_pending($order, $payment_method, $document_type, 'automatic');
		SAIT_WOOCOMMERCE_Orders::SAIT_marcarEnvioAutomaticoDisparado($order, $payment_method, $document_type);

		if (function_exists('as_enqueue_async_action')) {
			as_enqueue_async_action(self::ACTION, $args, self::GROUP, true);
		} else {
			wp_schedule_single_event(time() + 10, self::ACTION, $args);
		}

		return array('queued' => true, 'message' => 'SAIT ENVIO PROGRAMADO');
	}

	/**
	 * Worker idempotente que confirma la respuesta HTTP antes de marcar sent.
	 *
	 * @return void
	 */
	public function process($order_id, $payment_method)
	{
		$order = wc_get_order($order_id);
		if (!$order || $this->state->status($order) === SAIT_WOOCOMMERCE_OrderDeliveryState::SENT) {
			return;
		}

		$options = SAIT_WOOCOMMERCE()->settings()->all();
		$document_type = isset($options['SAITNube_TipoDoc']) ? $options['SAITNube_TipoDoc'] : 'P';
		$this->state->mark_sending($order, $payment_method, $document_type, 'automatic');
		$response = $document_type === 'P'
			? SAIT_WOOCOMMERCE_Orders::SAIT_sendPedido($order, $payment_method, true)
			: SAIT_WOOCOMMERCE_Orders::SAIT_sendCotizacion($order, $payment_method, true);
		SAIT_WOOCOMMERCE_Orders::SAIT_registrarResultadoEnvio(
			$order,
			$response,
			$document_type,
			$payment_method,
			'automatic'
		);
	}

	/** @return bool */
	private function is_scheduled($args)
	{
		if (function_exists('as_has_scheduled_action')) {
			return (bool) as_has_scheduled_action(self::ACTION, $args, self::GROUP);
		}

		return wp_next_scheduled(self::ACTION, $args) !== false;
	}
}
