<?php

/**
 * Presenta los precios promocionales resueltos por el servicio compartido.
 */
class SAIT_WOOCOMMERCE_Promotions
{
	/** @var SAIT_WOOCOMMERCE_Settings */
	private $settings;

	/** @var SAIT_WOOCOMMERCE_PriceService */
	private $price_service;

	/** @var string */
	private $template_path;

	public function __construct($settings, $plugin_file, $price_service = null)
	{
		$this->settings = $settings;
		$this->price_service = $price_service;
		$this->template_path = plugin_dir_path($plugin_file) . 'templates/promotion-price.php';
	}

	/** @return void */
	public function register_hooks()
	{
		if ($this->settings->is_enabled('SAITNube_PromoGlobal_enabled')) {
			add_filter('woocommerce_get_price_html', array($this, 'filter_price_html'), 30, 2);
		}
		if ($this->settings->is_enabled('SAITNube_Promo_enabled')) {
			add_action('woocommerce_before_calculate_totals', array($this, 'apply_cart_prices'));
			add_filter('woocommerce_cart_item_price', array($this, 'display_cart_item_price'), 10, 3);
		}
	}

	/**
	 * @param string     $price_html Precio original.
	 * @param WC_Product $product Producto WooCommerce.
	 * @return string
	 */
	public function filter_price_html($price_html, $product)
	{
		if (is_admin() || !$this->settings->is_enabled('SAITNube_PromoGlobal_enabled')) {
			return $price_html;
		}

		$calculated = $this->price_service()->get_price($product, 1);
		if (!$calculated) {
			return $price_html;
		}

		$public_price = (float) $calculated['preciopub'];
		$api_discount = (float) $calculated['pjedesc'];
		$regular_price = (float) $product->get_regular_price();
		$promotional_price = round($public_price, 2);
		if ($public_price <= 0) {
			return $price_html;
		}

		if ($api_discount > 0) {
			$discount = round($api_discount);
			$promotional_price = $public_price * (1 - ($discount / 100));
		} elseif ($regular_price > 0 && $promotional_price < $regular_price) {
			$discount = round((1 - ($promotional_price / $regular_price)) * 100);
		} else {
			return $price_html;
		}

		if ($api_discount == 0 && $promotional_price >= $regular_price) {
			return $price_html;
		}

		$is_product_page = is_product();
		ob_start();
		include $this->template_path;
		return (string) ob_get_clean();
	}

	/**
	 * @param WC_Cart $cart Carrito activo.
	 * @return void
	 */
	public function apply_cart_prices($cart)
	{
		if (!$this->settings->is_enabled('SAITNube_Promo_enabled')) {
			return;
		}
		if (is_admin() && !defined('DOING_AJAX')) {
			return;
		}

		foreach ($cart->get_cart() as $cart_item) {
			$product = $cart_item['data'];
			$original_price = (float) $product->get_regular_price();
			$calculated = $this->price_service()->get_price($product, $cart_item['quantity']);
			if (!$calculated) {
				$product->set_price($original_price);
				continue;
			}

			$public_price = (float) $calculated['preciopub'];
			$discount = (float) $calculated['pjedesc'];
			$discounted_price = $public_price * (1 - ($discount / 100));
			if ($public_price <= 0 || $discounted_price <= 0) {
				$product->set_price($original_price);
				continue;
			}

			if (round($discounted_price, 2) < $original_price) {
				$product->set_price($discounted_price);
			}
		}
	}

	/**
	 * @param string $price Precio mostrado.
	 * @param array  $cart_item Línea de carrito.
	 * @param string $cart_item_key Clave de línea.
	 * @return string
	 */
	public function display_cart_item_price($price, $cart_item, $cart_item_key)
	{
		$product = $cart_item['data'];
		$regular_price = $product->get_regular_price();
		$discounted_price = $product->get_price();
		if ($discounted_price < $regular_price) {
			return wc_price($discounted_price) . ' <del>' . wc_price($regular_price) . '</del>';
		}

		return $price;
	}

	/** @return SAIT_WOOCOMMERCE_PriceService */
	private function price_service()
	{
		return $this->price_service ?: SAIT_WOOCOMMERCE()->price_service();
	}
}
