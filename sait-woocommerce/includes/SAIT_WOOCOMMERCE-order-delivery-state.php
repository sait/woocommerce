<?php

/**
 * Estado persistente de entrega de documentos SAIT sobre WC_Order (compatible con HPOS).
 */
class SAIT_WOOCOMMERCE_OrderDeliveryState
{
	const PENDING = 'pending';
	const SENDING = 'sending';
	const SENT = 'sent';
	const FAILED = 'failed';

	const META_STATUS = '_sait_delivery_status';
	const META_ATTEMPTS = '_sait_delivery_attempts';
	const META_UPDATED_AT = '_sait_delivery_updated_at';
	const META_LAST_ATTEMPT_AT = '_sait_delivery_last_attempt_at';
	const META_SENT_AT = '_sait_delivery_sent_at';
	const META_HTTP_STATUS = '_sait_delivery_http_status';
	const META_LAST_ERROR = '_sait_delivery_last_error';
	const META_PAYMENT_METHOD = '_sait_delivery_payment_method';
	const META_DOCUMENT_TYPE = '_sait_delivery_document_type';
	const META_MODE = '_sait_delivery_mode';

	/** @return string */
	public function status($order)
	{
		return (string) $order->get_meta(self::META_STATUS);
	}

	/** @return void */
	public function mark_pending($order, $payment_method, $document_type, $mode = 'automatic')
	{
		$this->save_status($order, self::PENDING, $payment_method, $document_type, $mode);
	}

	/** @return void */
	public function mark_sending($order, $payment_method, $document_type, $mode = 'automatic')
	{
		$attempts = absint($order->get_meta(self::META_ATTEMPTS)) + 1;
		$order->update_meta_data(self::META_ATTEMPTS, $attempts);
		$order->update_meta_data(self::META_LAST_ATTEMPT_AT, current_time('mysql'));
		$this->save_status($order, self::SENDING, $payment_method, $document_type, $mode);
	}

	/**
	 * Sólo una respuesta bloqueante HTTP 201 confirma la entrega.
	 *
	 * @param WC_Order $order Orden WooCommerce.
	 * @param array|WP_Error $response Respuesta del POST.
	 * @return string Estado final.
	 */
	public function record_response($order, $response)
	{
		$is_error = is_wp_error($response);
		$status_code = $is_error ? 0 : (int) wp_remote_retrieve_response_code($response);
		$message = $is_error ? $response->get_error_message() : wp_remote_retrieve_body($response);
		$status = $status_code === 201 ? self::SENT : self::FAILED;

		$order->update_meta_data(self::META_STATUS, $status);
		$order->update_meta_data(self::META_UPDATED_AT, current_time('mysql'));
		$order->update_meta_data(self::META_HTTP_STATUS, $status_code);
		if ($status === self::SENT) {
			$order->update_meta_data(self::META_SENT_AT, current_time('mysql'));
			$order->delete_meta_data(self::META_LAST_ERROR);
		} else {
			$order->update_meta_data(self::META_LAST_ERROR, substr((string) $message, 0, 1000));
		}
		$order->save();

		return $status;
	}

	/** @return void */
	private function save_status($order, $status, $payment_method, $document_type, $mode)
	{
		$order->update_meta_data(self::META_STATUS, $status);
		$order->update_meta_data(self::META_UPDATED_AT, current_time('mysql'));
		$order->update_meta_data(self::META_PAYMENT_METHOD, $payment_method);
		$order->update_meta_data(self::META_DOCUMENT_TYPE, $document_type);
		$order->update_meta_data(self::META_MODE, $mode);
		$order->save();
	}
}
