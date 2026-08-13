<?php

defined('ABSPATH') || exit;

/**
 * Agrega al pedido SAIT los datos operativos que Papelía consume en VFP.
 */
final class SAIT_Papelia_Order_Payload
{
	/** @return void */
	public function register_hooks()
	{
		add_filter('sait_woocommerce_legacy_customizer_enabled', '__return_false');
		add_filter('sait_woocommerce_order_payload', array($this, 'customize'), 10, 2);
		add_filter('sait_woocommerce_quote_payload', array($this, 'customize'), 10, 2);
	}

	/**
	 * @param object   $document Payload construido por SAIT WooCommerce.
	 * @param WC_Order $order Pedido de origen.
	 * @return object
	 */
	public function customize($document, $order)
	{
		$document->otrosdatos = $this->other_data($order);
		$document->obs = $this->observation($order);

		return $document;
	}

	/** @return string */
	private function other_data(WC_Order $order)
	{
		$shipping = trim((string) $order->get_shipping_method());
		$payment = trim((string) $order->get_payment_method_title());
		$name = trim((string) $order->get_formatted_billing_full_name());
		$phone = trim((string) $order->get_billing_phone());
		$email = trim((string) $order->get_billing_email());

		return implode("\r\n", array(
			'Tipo de Entrega=' . ($shipping !== '' ? $shipping : 'Recoger en sucursal'),
			'Metodo de pago=' . ($payment !== '' ? $payment : 'SIN PAGO'),
			'Nombre= ' . ($name !== '' ? $name : 'SIN NOMBRE'),
			'Telefono= ' . ($phone !== '' ? $phone : 'SIN TELEFONO'),
			'Correo= ' . ($email !== '' ? $email : 'SIN CORREO'),
			'clinum=     0',
		));
	}

	/** @return string */
	private function observation(WC_Order $order)
	{
		$shipping = trim((string) $order->get_shipping_method());
		$payment = trim((string) $order->get_payment_method_title());
		$note = trim((string) $order->get_customer_note());
		$branch_id = trim((string) $order->get_meta('_sait_sucursal'));
		$branch_name = trim((string) $order->get_meta('_sait_sucursal_nombre'));
		$missing_stock = (string) $order->get_meta('_sait_pedido_sin_existencias');

		$observation = strtoupper(
			($shipping !== '' ? $shipping : 'SIN ENTREGA')
			. ' Y '
			. ($payment !== '' ? $payment : 'SIN PAGO')
		);

		if ($branch_id !== '') {
			$observation .= "\r\nSucursal: " . trim($branch_id . '  ' . $branch_name);
		}
		if ($missing_stock !== '') {
			$observation .= "\r\nExistencias faltantes en sucursal";
		}
		if ($note !== '') {
			$observation .= "\r\nObs: " . $note;
		}

		return $observation;
	}
}
