<?php

defined('ABSPATH') || exit;

/**
 * Consulta existencias SAIT para Papelía sin desactivar las validaciones globales de WooCommerce.
 */
final class SAIT_Papelia_Stock
{
	const CACHE_TTL = 120;

	/** @return void */
	public function register_hooks()
	{
		add_filter('woocommerce_product_get_stock_quantity', array($this, 'filter_total_stock'), 10, 2);
		add_filter('woocommerce_add_to_cart_validation', array($this, 'validate_add_to_cart'), 10, 3);
		add_filter('woocommerce_update_cart_validation', array($this, 'validate_cart_update'), 10, 4);
	}

	/**
	 * @param int|float|null $stock Stock guardado en WooCommerce.
	 * @param WC_Product $product Producto consultado.
	 * @return int|float|null
	 */
	public function filter_total_stock($stock, $product)
	{
		if (is_admin() || !$this->remote_stock_enabled()) {
			return $stock;
		}

		$remote = $this->get_stock($product);

		return $remote === null ? $stock : $remote;
	}

	/**
	 * @param bool $passed Resultado acumulado.
	 * @param int $product_id ID del producto.
	 * @param int|float $quantity Cantidad solicitada.
	 * @return bool
	 */
	public function validate_add_to_cart($passed, $product_id, $quantity)
	{
		$product = wc_get_product($product_id);
		if (!$product) {
			return $passed;
		}

		$remote = $this->get_stock($product);
		if ($remote === null) {
			return $passed;
		}

		$in_cart = 0.0;
		if (WC()->cart) {
			foreach (WC()->cart->get_cart() as $item) {
				if ((int) $item['product_id'] === (int) $product_id) {
					$in_cart += (float) $item['quantity'];
				}
			}
		}

		if ((float) $quantity > $remote || $in_cart + (float) $quantity > $remote) {
			$this->add_stock_notice($product, $remote);
			return false;
		}

		return $passed;
	}

	/**
	 * @param bool $passed Resultado acumulado.
	 * @param string $cart_item_key Clave del carrito.
	 * @param array<string,mixed> $values Datos de la línea.
	 * @param int|float $quantity Cantidad solicitada.
	 * @return bool
	 */
	public function validate_cart_update($passed, $cart_item_key, $values, $quantity)
	{
		$product = isset($values['data']) ? $values['data'] : null;
		if (!$product instanceof WC_Product) {
			return $passed;
		}

		$remote = $this->get_stock($product);
		if ($remote !== null && (float) $quantity > $remote) {
			$this->add_stock_notice($product, $remote);
			return false;
		}

		return $passed;
	}

	/**
	 * @param WC_Product $product Producto consultado.
	 * @param string $branch_id Clave de almacén; vacío suma almacenes configurados.
	 * @return float|null
	 */
	public function get_stock(WC_Product $product, $branch_id = '')
	{
		$sku = trim((string) $product->get_sku());
		if ($sku === '' || !$this->remote_stock_enabled()) {
			return null;
		}

		$context = $branch_id !== '' ? trim($branch_id) : 'total';
		$cache_key = 'sait_papelia_stock_' . md5($sku . '|' . $context);
		$cached = get_transient($cache_key);
		if ($cached !== false) {
			return (float) $cached;
		}

		$rows = $this->stock_rows($sku);
		if ($rows === null) {
			return null;
		}

		$allowed = SAIT_WOOCOMMERCE()->settings()->warehouses();
		$total = 0.0;
		foreach ($rows as $row) {
			$row_branch = isset($row['numalm']) ? trim((string) $row['numalm']) : '';
			$quantity = isset($row['existencia']) ? (float) $row['existencia'] : 0.0;
			if ($branch_id !== '' && $row_branch === trim($branch_id)) {
				$total = $quantity;
				break;
			}
			if ($branch_id === '' && in_array($row_branch, $allowed, true)) {
				$total += $quantity;
			}
		}

		set_transient($cache_key, $total, self::CACHE_TTL);

		return $total;
	}

	/** @return bool */
	private function remote_stock_enabled()
	{
		return SAIT_WOOCOMMERCE()->settings()->is_enabled('SAITNube_ExistAlm_enabled');
	}

	/**
	 * @param string $sku SKU sin espacios exteriores.
	 * @return array<int,array<string,mixed>>|null
	 */
	private function stock_rows($sku)
	{
		$response = SAIT_WOOCOMMERCE()->sait_client()->get('/api/v3/existencias/' . $sku, false);
		$data = isset($response['data']) && is_array($response['data']) ? $response['data'] : array();

		return isset($data['result']) && is_array($data['result']) ? $data['result'] : null;
	}

	/** @return void */
	private function add_stock_notice(WC_Product $product, $stock)
	{
		wc_add_notice(
			sprintf(
				/* translators: 1: stock available, 2: product name. */
				esc_html__('No puedes agregar más de %1$s unidades de “%2$s” al carrito.', 'sait-woocommerce-papelia'),
				wc_format_decimal($stock),
				esc_html($product->get_name())
			),
			'error'
		);
	}
}
