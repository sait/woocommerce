<?php

/**
 * Restricción de monto mínimo del carrito.
 */
class SAIT_WOOCOMMERCE_CartMinimum
{
	/** @var SAIT_WOOCOMMERCE_Settings */
	private $settings;

	/** @var string */
	private $template_path;

	public function __construct($settings, $plugin_file)
	{
		$this->settings = $settings;
		$this->template_path = plugin_dir_path($plugin_file) . 'templates/cart-minimum-script.php';
	}

	/** @return void */
	public function register_hooks()
	{
		if (!$this->settings->is_enabled('SAITNube_MinimoCarrito_Enabled')) {
			return;
		}

		add_action('woocommerce_checkout_process', array($this, 'validate'));
		add_action('woocommerce_before_cart', array($this, 'validate'));
		add_action('wp_footer', array($this, 'render_button_guard'));
	}

	/** @return void */
	public function validate()
	{
		if (!$this->settings->is_enabled('SAITNube_MinimoCarrito_Enabled') || !WC()->cart) {
			return;
		}

		$minimum = floatval($this->settings->get('SAITNube_MinimoCarrito', ''));
		$subtotal = WC()->cart->get_subtotal();
		if ($subtotal >= $minimum) {
			return;
		}

		$message = sprintf(
			'Tu pedido actual es de %s — el monto mínimo para comprar es %s.',
			wc_price($subtotal),
			wc_price($minimum)
		);
		if (is_cart()) {
			wc_print_notice($message, 'error');
		} else {
			wc_add_notice($message, 'error');
		}
	}

	/** @return void */
	public function render_button_guard()
	{
		if (
			!$this->settings->is_enabled('SAITNube_MinimoCarrito_Enabled')
			|| (!is_cart() && !is_checkout())
		) {
			return;
		}

		$minimum = floatval($this->settings->get('SAITNube_MinimoCarrito', ''));
		include $this->template_path;
	}
}
