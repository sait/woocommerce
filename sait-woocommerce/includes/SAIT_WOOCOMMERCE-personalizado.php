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
 *
 * En esta clase se registraran las funciones personalizadas para clientes.
 * @since      1.0.3
 * @package    SAIT_WOOCOMMERCE
 * @subpackage SAIT_WOOCOMMERCE/includes
 * @author     Ali Moreno <ali@saitenlinea.com>
 */

	
 class SAIT_PERSONALIZADO{


	public static function SAIT_FuncionPersonalizaPostPedido($body,$order) {
		//$body->otrosdatos =  self::SAIT_getOtrosDatos($order);
		return $body;
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


add_action('woocommerce_after_checkout_form', function() {
	wp_enqueue_script(
			'modal-script',
			plugins_url('../assets/js/personalizado.js', __FILE__),
			array('jquery'),
			'1.0',
			true
	);

	wp_localize_script('modal-script', 'sait_woocommerce_ajax', array(
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce'    => wp_create_nonce('sait-woocommerce_nonce'),
	));
});