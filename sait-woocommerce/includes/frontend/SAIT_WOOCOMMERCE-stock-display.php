<?php

/**
 * Presentación de existencias y filtro del catálogo.
 */
class SAIT_WOOCOMMERCE_StockDisplay
{
	/** @var SAIT_WOOCOMMERCE_Settings */
	private $settings;

	/** @var string */
	private $template_path;

	public function __construct($settings, $plugin_file)
	{
		$this->settings = $settings;
		$this->template_path = plugin_dir_path($plugin_file) . 'templates/stock-table.php';
	}

	/** @return void */
	public function register_hooks()
	{
		if ($this->settings->is_enabled('SAITNube_ExistAlm_enabled')) {
			add_action('woocommerce_single_product_summary', array($this, 'render_stock_table'), 25);
		}

		if ($this->settings->is_enabled('SAITNube_OcultarSinPrecio_enabled')) {
			add_action('woocommerce_product_query', array($this, 'hide_products_without_price'));
		}
	}

	/** @return void */
	public function render_stock_table()
	{
		if (!$this->settings->is_enabled('SAITNube_ExistAlm_enabled')) {
			return;
		}

		global $product;
		if (!$product || !is_a($product, 'WC_Product')) {
			return;
		}

		$sku = $product->get_sku();
		$response = SAIT_UTILS::SAIT_GetNube('/api/v3/existencias/' . trim($sku));
		$warehouses = SAIT_UTILS::SAIT_getResult($response);

		if (empty($warehouses)) {
			echo '<p>' . esc_html__('No hay información de existencias (respuesta vacía o sin resultados).', 'sait-woocommerce') . '</p>';
			return;
		}

		if (is_array($response) && !empty($response['error'])) {
			echo '<p>' . esc_html__('Error en la respuesta de la API:', 'sait-woocommerce') . ' ' . esc_html($response['error']) . '</p>';
			return;
		}

		$allowed_warehouses = $this->settings->warehouses();
		include $this->template_path;
	}

	/**
	 * @param WC_Product_Query $query Consulta del catálogo.
	 * @return void
	 */
	public function hide_products_without_price($query)
	{
		if (!$this->settings->is_enabled('SAITNube_OcultarSinPrecio_enabled')) {
			return;
		}

		if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST)) {
			return;
		}

		if (!is_shop() && !is_product_taxonomy() && !is_search()) {
			return;
		}

		$meta_query = $query->get('meta_query');
		$meta_query = $meta_query ? $meta_query : array();
		$meta_query[] = array(
			'key'     => '_price',
			'value'   => 0,
			'compare' => '>',
			'type'    => 'NUMERIC',
		);
		$query->set('meta_query', $meta_query);
	}
}
