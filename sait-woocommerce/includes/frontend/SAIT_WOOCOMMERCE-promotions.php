<?php

/**
 * Precios promocionales en catálogo, producto y carrito.
 */
class SAIT_WOOCOMMERCE_Promotions
{
	/** @var SAIT_WOOCOMMERCE_Settings */
	private $settings;

	/** @var string */
	private $template_path;

	/** @var SAIT_WOOCOMMERCE_BranchSelector|null */
	private $branch_selector;

	public function __construct($settings, $plugin_file, $branch_selector = null)
	{
		$this->settings = $settings;
		$this->template_path = plugin_dir_path($plugin_file) . 'templates/promotion-price.php';
		$this->branch_selector = $branch_selector;
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

		$sku = $product->get_sku();
		if (!$sku) {
			return $price_html;
		}

		$current_user = wp_get_current_user();
		$user_id = get_current_user_id();
		$client_cache_key = 'sait_cli_' . $user_id;
		$client_number = get_transient($client_cache_key);

		if ($client_number === false) {
			$mapping = SAIT_UTILS::SAIT_getClaves('clientes', null, $user_id);
			$client_number = isset($mapping->clave)
				? str_pad($mapping->clave, 5, ' ', STR_PAD_LEFT)
				: ((!empty($current_user->user_email) && is_email($current_user->user_email))
					? SAIT_UTILS::SAIT_getClientebyemail($current_user->user_email)
					: '');

			if (empty($client_number) || strpos($client_number, '-') !== false) {
				$client_number = '    0';
			}
			set_transient($client_cache_key, $client_number, 1800);
		}

		$warehouse = $this->get_selected_branch();
		if (empty($warehouse)) {
			$warehouse = $this->settings->get('SAITNube_NumAlm', '');
		}
		$warehouse = str_pad($warehouse, 2, ' ', STR_PAD_LEFT);

		$article_cache_key = 'sait_art_' . $sku;
		$article_response = get_transient($article_cache_key);
		if ($article_response === false) {
			$article_response = SAIT_UTILS::SAIT_GetNube('/api/v3/articulos/' . $sku);
			$article = SAIT_UTILS::SAIT_getResult($article_response);
			if (!isset($article['unidad'])) {
				usleep(500000);
				$article_response = SAIT_UTILS::SAIT_GetNube('/api/v3/articulos/' . $sku, false);
				$article = SAIT_UTILS::SAIT_getResult($article_response);
			}
			if (!empty($article_response)) {
				set_transient($article_cache_key, $article_response, 86400);
			}
		} else {
			$article = SAIT_UTILS::SAIT_getResult($article_response);
		}

		if (!isset($article['unidad'])) {
			return $price_html;
		}

		$unit = $article['unidad'];
		$price_cache_key = 'sait_precio_' . md5($sku . '_' . $client_number . '_' . $warehouse);
		$cached_price = get_transient($price_cache_key);

		if ($cached_price !== false) {
			$public_price = $cached_price['preciopub'];
			$api_discount = $cached_price['pje_api'];
		} else {
			$price_response = SAIT_UTILS::SAIT_GetNube(
				"/api/v3/calcularprecios?numart=$sku&unidad=$unit&cant=1&divisadoc=P&numalm=$warehouse&formapago=1&numcli=$client_number"
			);
			$calculated = SAIT_UTILS::SAIT_getResult($price_response);
			if (empty($calculated)) {
				return $price_html;
			}

			$public_price = isset($calculated['preciopub']) ? floatval($calculated['preciopub']) : 0;
			$api_discount = isset($calculated['pjedesc']) ? floatval($calculated['pjedesc']) : 0;
			set_transient(
				$price_cache_key,
				array('preciopub' => $public_price, 'pje_api' => $api_discount),
				900
			);
		}

		$regular_price = floatval($product->get_regular_price());
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
			$original_price = $product->get_regular_price();
			$sku = $product->get_sku();
			$article_response = SAIT_UTILS::SAIT_GetNube('/api/v3/articulos/' . $sku);
			$article = SAIT_UTILS::SAIT_getResult($article_response);

			if (!isset($article['unidad'])) {
				usleep(500000);
				$article_response = SAIT_UTILS::SAIT_GetNube('/api/v3/articulos/' . $sku, false);
				$article = SAIT_UTILS::SAIT_getResult($article_response);
			}
			if (!isset($article['unidad'])) {
				$product->set_price($original_price);
				continue;
			}

			$current_user = wp_get_current_user();
			$mapping = SAIT_UTILS::SAIT_getClaves('clientes', null, get_current_user_id());
			$client_number = '    0';
			if (isset($mapping->clave)) {
				$client_number = str_pad($mapping->clave, 5, ' ', STR_PAD_LEFT);
			} elseif (isset($current_user->user_email)) {
				$client_number = SAIT_UTILS::SAIT_getClientebyemail($current_user->user_email);
			}
			if (empty($client_number) || strpos($client_number, '-') !== false) {
				$client_number = '    0';
			}

			$warehouse = $this->get_selected_branch();
			if (empty($warehouse)) {
				$warehouse = $this->settings->get('SAITNube_NumAlm', '');
			}
			$warehouse = str_pad($warehouse, 2, ' ', STR_PAD_LEFT);
			$quantity = $cart_item['quantity'];
			$unit = $article['unidad'];
			$price_response = SAIT_UTILS::SAIT_GetNube(
				"/api/v3/calcularprecios?numart=$sku&unidad=$unit&cant=$quantity&divisadoc=P&numalm=$warehouse&formapago=1&numcli=$client_number"
			);
			$calculated = SAIT_UTILS::SAIT_getResult($price_response);

			if (!isset($calculated['preciopub']) || !isset($calculated['pjedesc'])) {
				$product->set_price($original_price);
				continue;
			}

			$public_price = floatval($calculated['preciopub']);
			$discount = floatval($calculated['pjedesc']);
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

	/** @return int|string */
	private function get_selected_branch()
	{
		if ($this->branch_selector) {
			return $this->branch_selector->get_selected_branch();
		}

		return get_user_meta(get_current_user_id(), 'sucursal_seleccionada', true);
	}
}
