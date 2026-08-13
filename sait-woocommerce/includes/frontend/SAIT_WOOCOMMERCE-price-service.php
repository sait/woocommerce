<?php

/**
 * Resuelve precios SAIT conservando el contexto de cliente, sucursal y cantidad.
 */
class SAIT_WOOCOMMERCE_PriceService
{
	const PUBLIC_CLIENT = '    0';
	const CACHE_VERSION_OPTION = 'sait_price_cache_version';
	const ARTICLE_TTL = 86400;
	const PRICE_TTL = 900;

	/** @var SAIT_WOOCOMMERCE_Settings */
	private $settings;

	/** @var SAIT_WOOCOMMERCE_SaitClientInterface */
	private $client;

	/** @var SAIT_WOOCOMMERCE_BranchSelector */
	private $branch_selector;

	/** @var array<string,array<string,mixed>> */
	private $article_memory = array();

	/** @var array<string,array<string,mixed>> */
	private $price_memory = array();

	/** @var array<string,string>|null */
	private $client_context = null;

	public function __construct($settings, $client, $branch_selector)
	{
		$this->settings = $settings;
		$this->client = $client;
		$this->branch_selector = $branch_selector;
	}

	/** @return void */
	public function register_hooks()
	{
		add_action('updated_option', array($this, 'invalidate_after_settings_update'), 10, 3);
	}

	/**
	 * @param WC_Product $product Producto consultado.
	 * @param int|float  $quantity Cantidad solicitada.
	 * @return array<string,mixed>|null
	 */
	public function get_price($product, $quantity)
	{
		$sku = trim((string) $product->get_sku());
		if ($sku === '') {
			return null;
		}

		$article = $this->get_article($sku);
		if (!isset($article['unidad'])) {
			return null;
		}

		$client = $this->get_client_context();
		$warehouse = $this->branch_selector->get_selected_branch();
		if (empty($warehouse)) {
			$warehouse = $this->settings->get('SAITNube_NumAlm', '');
		}
		$warehouse = str_pad(trim((string) $warehouse), 2, ' ', STR_PAD_LEFT);
		$quantity = max(1, (float) $quantity);
		$unit = (string) $article['unidad'];
		$version = max(1, (int) get_option(self::CACHE_VERSION_OPTION, 1));
		$cache_context = array(
			'version'    => $version,
			'customer'   => $client['cache_context'],
			'numcli'     => $client['numcli'],
			'sku'        => $sku,
			'unit'       => $unit,
			'quantity'   => $quantity,
			'currency'   => 'P',
			'warehouse'  => $warehouse,
			'payment'    => '1',
		);
		$context_hash = md5(wp_json_encode($cache_context));
		if (isset($this->price_memory[$context_hash])) {
			return $this->price_memory[$context_hash];
		}

		$transient_key = 'sait_precio_' . $context_hash;
		$cached = get_transient($transient_key);
		if (is_array($cached) && isset($cached['preciopub'], $cached['pjedesc'])) {
			$this->price_memory[$context_hash] = $cached;
			return $cached;
		}

		$uri = add_query_arg(
			array(
				'numart'   => $sku,
				'unidad'   => $unit,
				'cant'     => $quantity,
				'divisadoc' => 'P',
				'numalm'   => $warehouse,
				'formapago' => '1',
				'numcli'   => $client['numcli'],
			),
			'/api/v3/calcularprecios'
		);
		$response = $this->client->get($uri);
		$result = !empty($response['ok']) && is_array($response['result']) ? $response['result'] : null;
		if (!$result || !isset($result['preciopub'])) {
			return null;
		}

		$price = array(
			'preciopub' => (float) $result['preciopub'],
			'pjedesc'   => isset($result['pjedesc']) ? (float) $result['pjedesc'] : 0,
			'numcli'    => $client['numcli'],
			'numalm'    => $warehouse,
			'cantidad'  => $quantity,
		);
		$this->price_memory[$context_hash] = $price;
		set_transient($transient_key, $price, self::PRICE_TTL);

		return $price;
	}

	/**
	 * Invalida artículo y precios derivados de un SKU.
	 *
	 * @param string $sku SKU modificado.
	 * @return void
	 */
	public function invalidate_sku($sku)
	{
		$sku = trim((string) $sku);
		if ($sku !== '') {
			delete_transient($this->article_cache_key($sku));
			delete_transient('sait_art_' . $sku);
		}
		$this->invalidate_prices();
	}

	/** @return void */
	public function invalidate_prices()
	{
		$version = max(1, (int) get_option(self::CACHE_VERSION_OPTION, 1));
		update_option(self::CACHE_VERSION_OPTION, $version + 1, false);
		$this->article_memory = array();
		$this->price_memory = array();
		$this->client_context = null;
	}

	/**
	 * @param string $option Nombre actualizado.
	 * @param mixed  $old_value Valor anterior.
	 * @param mixed  $new_value Valor nuevo.
	 * @return void
	 */
	public function invalidate_after_settings_update($option, $old_value, $new_value)
	{
		if ($option !== SAIT_WOOCOMMERCE_Settings::OPTION_NAME || $old_value === $new_value) {
			return;
		}

		$keys = array('SAITNube_URL', 'SAITNube_NumAlm', 'SAITNube_PrecioLista', 'SAITNube_TipoCambio');
		foreach ($keys as $key) {
			$old = is_array($old_value) && isset($old_value[$key]) ? $old_value[$key] : null;
			$new = is_array($new_value) && isset($new_value[$key]) ? $new_value[$key] : null;
			if ($old !== $new) {
				$this->invalidate_prices();
				return;
			}
		}
	}

	/** @return array<string,mixed>|null */
	private function get_article($sku)
	{
		$memory_key = md5($sku);
		if (array_key_exists($memory_key, $this->article_memory)) {
			return $this->article_memory[$memory_key];
		}

		$transient_key = $this->article_cache_key($sku);
		$article = get_transient($transient_key);
		if (!is_array($article)) {
			$response = $this->client->get('/api/v3/articulos/' . rawurlencode($sku));
			$article = !empty($response['ok']) && is_array($response['result']) ? $response['result'] : null;
			if (is_array($article)) {
				set_transient($transient_key, $article, self::ARTICLE_TTL);
			}
		}

		$this->article_memory[$memory_key] = $article;
		return $article;
	}

	/** @return array<string,string> */
	private function get_client_context()
	{
		if ($this->client_context !== null) {
			return $this->client_context;
		}

		$user_id = get_current_user_id();
		if ($user_id <= 0) {
			$this->client_context = array('numcli' => self::PUBLIC_CLIENT, 'cache_context' => 'public');
			return $this->client_context;
		}

		$cache_key = 'sait_cli_' . $user_id;
		$numcli = get_transient($cache_key);
		if ($numcli === false) {
			$mapping = SAIT_UTILS::SAIT_getClaves('clientes', null, $user_id);
			$user = wp_get_current_user();
			$numcli = isset($mapping->clave)
				? $mapping->clave
				: ((!empty($user->user_email) && is_email($user->user_email))
					? SAIT_UTILS::SAIT_getClientebyemail($user->user_email)
					: '');
			$numcli = $this->normalize_client_number($numcli);
			set_transient($cache_key, $numcli, 1800);
		}

		$numcli = $this->normalize_client_number($numcli);
		$this->client_context = array(
			'numcli'        => $numcli,
			'cache_context' => $numcli === self::PUBLIC_CLIENT ? 'public' : 'user:' . $user_id . ':client:' . $numcli,
		);

		return $this->client_context;
	}

	/** @return string */
	private function normalize_client_number($numcli)
	{
		$numcli = trim((string) $numcli);
		if ($numcli === '' || strpos($numcli, '-') !== false) {
			return self::PUBLIC_CLIENT;
		}

		return str_pad($numcli, 5, ' ', STR_PAD_LEFT);
	}

	/** @return string */
	private function article_cache_key($sku)
	{
		return 'sait_articulo_' . md5($sku);
	}
}
