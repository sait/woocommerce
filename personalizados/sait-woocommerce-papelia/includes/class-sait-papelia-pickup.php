<?php

defined('ABSPATH') || exit;

/**
 * Checkout clásico de recogida local y selección de sucursal para Papelía.
 */
final class SAIT_Papelia_Pickup
{
	const DEFAULT_SHIPPING_METHOD = 'local_pickup:4';
	const META_BRANCH_ID = '_sait_sucursal';
	const META_BRANCH_NAME = '_sait_sucursal_nombre';
	const META_MISSING_STOCK = '_sait_pedido_sin_existencias';
	const SESSION_BRANCH = 'sait_sucursal_seleccionada';

	/** @var SAIT_Papelia_Stock */
	private $stock;

	public function __construct(SAIT_Papelia_Stock $stock)
	{
		$this->stock = $stock;
	}

	/** @return void */
	public function register_hooks()
	{
		add_action('woocommerce_after_shipping_rate', array($this, 'render_branch_select'), 20, 2);
		add_action('woocommerce_checkout_process', array($this, 'validate_branch'));
		add_action('woocommerce_checkout_update_order_review', array($this, 'remember_branch'));
		add_action('woocommerce_checkout_create_order', array($this, 'save_order_meta'), 10, 2);
		add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'render_admin_meta'));
		add_action('woocommerce_email_order_meta', array($this, 'render_email_meta'), 10, 4);
		add_filter('woocommerce_checkout_fields', array($this, 'filter_checkout_fields'));
		add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
		add_action('wp_ajax_sait_papelia_validate_stock', array($this, 'validate_stock_ajax'));
		add_action('wp_ajax_nopriv_sait_papelia_validate_stock', array($this, 'validate_stock_ajax'));
	}

	/**
	 * @param WC_Shipping_Rate $method Tarifa de envío.
	 * @param int $index Índice del paquete.
	 * @return void
	 */
	public function render_branch_select($method, $index)
	{
		if ($method->get_id() !== $this->shipping_method()) {
			return;
		}

		$branches = $this->available_branches();
		if (empty($branches)) {
			echo '<p>' . esc_html__('No hay sucursales disponibles para recoger el pedido.', 'sait-woocommerce-papelia') . '</p>';
			return;
		}

		$selected = $this->selected_branch();
		echo '<div class="sait-papelia-branch-wrapper" style="margin:10px 0;display:none">';
		echo '<label for="sait_papelia_branch"><strong>';
		echo esc_html__('Selecciona tu sucursal:', 'sait-woocommerce-papelia');
		echo '</strong></label><br>';
		echo '<select name="sait_papelia_branch" id="sait_papelia_branch" required>';
		echo '<option value="">' . esc_html__('-- Selecciona una sucursal --', 'sait-woocommerce-papelia') . '</option>';

		foreach ($branches as $branch) {
			$id = isset($branch['numalm']) ? trim((string) $branch['numalm']) : '';
			$name = isset($branch['nomalm']) ? trim((string) $branch['nomalm']) : '';
			$street = isset($branch['calle']) ? trim((string) $branch['calle']) : '';
			$neighborhood = isset($branch['colonia']) ? trim((string) $branch['colonia']) : '';
			$description = trim($name . ($street !== '' || $neighborhood !== '' ? ' — ' . trim($street . ' ' . $neighborhood) : ''));
			if ($id === '') {
				continue;
			}

			echo '<option value="' . esc_attr($id) . '" ' . selected($selected, $id, false) . '>';
			echo esc_html($description);
			echo '</option>';
		}

		echo '</select></div>';
	}

	/** @return void */
	public function validate_branch()
	{
		$shipping_methods = isset($_POST['shipping_method'])
			? (array) wp_unslash($_POST['shipping_method'])
			: array();
		$branch_id = isset($_POST['sait_papelia_branch'])
			? sanitize_text_field(wp_unslash($_POST['sait_papelia_branch']))
			: '';

		if (in_array($this->shipping_method(), $shipping_methods, true) && $branch_id === '') {
			wc_add_notice(
				esc_html__('Por favor selecciona una sucursal para recoger tu pedido.', 'sait-woocommerce-papelia'),
				'error'
			);
		}
	}

	/**
	 * @param string $posted_data Datos serializados por checkout.
	 * @return void
	 */
	public function remember_branch($posted_data)
	{
		$data = array();
		wp_parse_str($posted_data, $data);
		if (isset($data['sait_papelia_branch']) && WC()->session) {
			WC()->session->set(
				self::SESSION_BRANCH,
				sanitize_text_field(wp_unslash($data['sait_papelia_branch']))
			);
		}
	}

	/**
	 * @param WC_Order $order Orden que WooCommerce guardará.
	 * @param array<string,mixed> $data Datos saneados por checkout.
	 * @return void
	 */
	public function save_order_meta($order, $data)
	{
		$branch_id = isset($_POST['sait_papelia_branch'])
			? sanitize_text_field(wp_unslash($_POST['sait_papelia_branch']))
			: '';
		$branch = $this->find_available_branch($branch_id);
		if ($branch !== null) {
			$order->update_meta_data(self::META_BRANCH_ID, $branch_id);
			$order->update_meta_data(
				self::META_BRANCH_NAME,
				isset($branch['nomalm']) ? trim((string) $branch['nomalm']) : ''
			);
		}

		$missing_stock = isset($_POST['sait_papelia_missing_stock'])
			? sanitize_text_field(wp_unslash($_POST['sait_papelia_missing_stock']))
			: '';
		if ($missing_stock === '1') {
			$order->update_meta_data(self::META_MISSING_STOCK, '1');
		}
	}

	/** @return void */
	public function enqueue_assets()
	{
		if (!is_checkout() || is_order_received_page()) {
			return;
		}

		wp_enqueue_script(
			'sait-papelia-checkout',
			plugins_url('assets/js/checkout.js', dirname(__DIR__) . '/sait-woocommerce-papelia.php'),
			array('jquery', 'wc-checkout'),
			SAIT_Papelia_Plugin::VERSION,
			true
		);
		wp_localize_script('sait-papelia-checkout', 'saitPapeliaCheckout', array(
			'ajaxUrl'        => admin_url('admin-ajax.php'),
			'nonce'          => wp_create_nonce('sait-papelia-stock'),
			'shippingMethod' => $this->shipping_method(),
			'cartUrl'        => wc_get_cart_url(),
			'warningTitle'   => __('Advertencia', 'sait-woocommerce-papelia'),
			'warningBody'    => __('Sin existencia suficiente en la sucursal seleccionada. ¿Deseas continuar? Si aceptas, un asesor se pondrá en contacto en breve.', 'sait-woocommerce-papelia'),
			'errorMessage'   => __('Ocurrió un error al verificar existencias.', 'sait-woocommerce-papelia'),
		));
	}

	/** @return void */
	public function validate_stock_ajax()
	{
		check_ajax_referer('sait-papelia-stock', 'nonce');

		$branch_id = isset($_POST['branchId'])
			? sanitize_text_field(wp_unslash($_POST['branchId']))
			: '';
		if ($this->find_available_branch($branch_id) === null) {
			wp_send_json_error(array('message' => 'Sucursal no válida.'), 400);
		}

		$missing = array();
		if (WC()->cart) {
			foreach (WC()->cart->get_cart() as $item) {
				$product = isset($item['data']) ? $item['data'] : null;
				if (!$product instanceof WC_Product) {
					continue;
				}

				$available = $this->stock->get_stock($product, $branch_id);
				$required = isset($item['quantity']) ? (float) $item['quantity'] : 0.0;
				if ($available === null || $available < $required) {
					$missing[] = $product->get_name();
				}
			}
		}

		wp_send_json_success(array('missingStock' => array_values($missing)));
	}

	/**
	 * @param WC_Order $order Orden mostrada.
	 * @return void
	 */
	public function render_admin_meta($order)
	{
		$branch_id = (string) $order->get_meta(self::META_BRANCH_ID);
		$branch_name = (string) $order->get_meta(self::META_BRANCH_NAME);
		$missing = (string) $order->get_meta(self::META_MISSING_STOCK);
		if ($branch_id !== '' || $branch_name !== '') {
			echo '<p><strong>' . esc_html__('Sucursal seleccionada:', 'sait-woocommerce-papelia') . '</strong> ';
			echo esc_html(trim($branch_id . ' ' . $branch_name)) . '</p>';
		}
		if ($missing !== '') {
			echo '<p><strong>' . esc_html__('Pedido con faltantes:', 'sait-woocommerce-papelia') . '</strong> ';
			echo esc_html__('Algunos productos no tienen existencias suficientes en la sucursal.', 'sait-woocommerce-papelia') . '</p>';
		}
	}

	/**
	 * @param WC_Order $order Orden incluida en el correo.
	 * @param bool $sent_to_admin Indica si el destinatario es administrador.
	 * @param bool $plain_text Indica formato de texto plano.
	 * @param WC_Email $email Instancia de correo.
	 * @return void
	 */
	public function render_email_meta($order, $sent_to_admin, $plain_text, $email)
	{
		if (!$sent_to_admin) {
			return;
		}

		$branch_id = (string) $order->get_meta(self::META_BRANCH_ID);
		$branch_name = (string) $order->get_meta(self::META_BRANCH_NAME);
		$missing = (string) $order->get_meta(self::META_MISSING_STOCK);
		if ($branch_id === '' && $branch_name === '') {
			return;
		}

		if ($plain_text) {
			echo "\n" . esc_html__('Sucursal:', 'sait-woocommerce-papelia') . ' ' . esc_html(trim($branch_id . ' ' . $branch_name)) . "\n";
			if ($missing !== '') {
				echo esc_html__('ALERTA: Existencias faltantes en esta sucursal.', 'sait-woocommerce-papelia') . "\n";
			}
			return;
		}

		echo '<h2>' . esc_html__('Detalles de sucursal', 'sait-woocommerce-papelia') . '</h2>';
		echo '<p><strong>' . esc_html__('Sucursal:', 'sait-woocommerce-papelia') . '</strong> ';
		echo esc_html(trim($branch_id . ' ' . $branch_name)) . '</p>';
		if ($missing !== '') {
			echo '<p><strong>' . esc_html__('Advertencia: existen artículos con faltantes.', 'sait-woocommerce-papelia') . '</strong></p>';
		}
	}

	/**
	 * @param array<string,mixed> $fields Campos de checkout.
	 * @return array<string,mixed>
	 */
	public function filter_checkout_fields($fields)
	{
		$chosen = WC()->session ? (array) WC()->session->get('chosen_shipping_methods', array()) : array();
		if (!in_array($this->shipping_method(), $chosen, true)) {
			return $fields;
		}

		foreach (array('billing_address_1', 'billing_postcode', 'billing_city', 'billing_state', 'billing_country') as $key) {
			if (isset($fields['billing'][$key])) {
				$fields['billing'][$key]['required'] = false;
			}
		}
		foreach (array('billing_first_name', 'billing_last_name', 'billing_phone', 'billing_email') as $key) {
			if (isset($fields['billing'][$key])) {
				$fields['billing'][$key]['required'] = true;
			}
		}
		if (isset($fields['shipping']) && is_array($fields['shipping'])) {
			foreach ($fields['shipping'] as $key => $field) {
				$fields['shipping'][$key]['required'] = false;
			}
		}

		return $fields;
	}

	/** @return string */
	private function shipping_method()
	{
		return (string) apply_filters('sait_papelia_pickup_shipping_method', self::DEFAULT_SHIPPING_METHOD);
	}

	/** @return string */
	private function selected_branch()
	{
		return WC()->session ? trim((string) WC()->session->get(self::SESSION_BRANCH, '')) : '';
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function available_branches()
	{
		$response = SAIT_WOOCOMMERCE()->sait_client()->get('/api/v3/almacenes', false);
		$data = isset($response['data']) && is_array($response['data']) ? $response['data'] : array();
		$branches = isset($data['result']) && is_array($data['result']) ? $data['result'] : array();
		$allowed = SAIT_WOOCOMMERCE()->settings()->warehouses();
		$excluded = (array) apply_filters('sait_papelia_excluded_pickup_warehouses', array('4'));
		$excluded = array_map('trim', $excluded);

		return array_values(array_filter($branches, function ($branch) use ($allowed, $excluded) {
			$id = isset($branch['numalm']) ? trim((string) $branch['numalm']) : '';
			return in_array($id, $allowed, true) && !in_array($id, $excluded, true);
		}));
	}

	/**
	 * @param string $branch_id Clave de almacén solicitada.
	 * @return array<string,mixed>|null
	 */
	private function find_available_branch($branch_id)
	{
		if ($branch_id === '') {
			return null;
		}
		foreach ($this->available_branches() as $branch) {
			if (isset($branch['numalm']) && trim((string) $branch['numalm']) === trim($branch_id)) {
				return $branch;
			}
		}

		return null;
	}
}
