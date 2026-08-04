<?php

/**
 * Acciones administrativas para pedidos WooCommerce.
 *
 * @package    SAIT_WOOCOMMERCE
 * @subpackage SAIT_WOOCOMMERCE/includes
 */

class SAIT_WOOCOMMERCE_OrderAdmin {
	const NOTICE_TRANSIENT_PREFIX = 'sait_reenviar_pedido_notice_';

	public function __construct() {
		add_action('woocommerce_admin_order_data_after_order_details', array($this, 'render_resend_button'));
		add_action('admin_post_sait_reenviar_pedido_admin', array($this, 'handle_resend_order'));
		add_action('admin_notices', array($this, 'show_notice'));
	}

	/**
	 * Muestra el boton de reenvio en la pantalla de edicion de pedido.
	 *
	 * @param WC_Order $order Pedido WooCommerce.
	 * @return void
	 */
	public function render_resend_button($order) {
		if (!$order || !current_user_can('edit_shop_orders')) {
			return;
		}

		$order_id = $order->get_id();
		$url = wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'sait_reenviar_pedido_admin',
					'order_id' => $order_id,
				),
				admin_url('admin-post.php')
			),
			'sait_reenviar_pedido_admin_' . $order_id
		);
		?>
		<div class="form-field form-field-wide sait-delivery-status">
			<h4>Entrega SAIT</h4>
			<p>
				<strong>Estado:</strong>
				<?php echo esc_html($this->delivery_status_label($order->get_meta(SAIT_WOOCOMMERCE_OrderDeliveryState::META_STATUS))); ?>
			</p>
			<p>
				<strong>Intentos:</strong>
				<?php echo esc_html(absint($order->get_meta(SAIT_WOOCOMMERCE_OrderDeliveryState::META_ATTEMPTS))); ?>
				&middot; <strong>Ultimo intento:</strong>
				<?php echo esc_html((string) $order->get_meta(SAIT_WOOCOMMERCE_OrderDeliveryState::META_LAST_ATTEMPT_AT)); ?>
				&middot; <strong>HTTP:</strong>
				<?php echo esc_html((string) $order->get_meta(SAIT_WOOCOMMERCE_OrderDeliveryState::META_HTTP_STATUS)); ?>
			</p>
		</div>
		<p class="form-field form-field-wide">
			<a class="button button-secondary" href="<?php echo esc_url($url); ?>">
				Reenviar pedido a SAIT
			</a>
		</p>
		<?php
	}

	/** @return string */
	private function delivery_status_label($status) {
		$labels = array(
			SAIT_WOOCOMMERCE_OrderDeliveryState::PENDING => 'Pendiente',
			SAIT_WOOCOMMERCE_OrderDeliveryState::SENDING => 'Enviando',
			SAIT_WOOCOMMERCE_OrderDeliveryState::SENT => 'Enviado',
			SAIT_WOOCOMMERCE_OrderDeliveryState::FAILED => 'Fallido',
		);

		return isset($labels[$status]) ? $labels[$status] : 'Sin programar';
	}

	public function handle_resend_order() {
		$order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
		if (!$order_id || !current_user_can('edit_shop_orders')) {
			wp_die('No tienes permisos para reenviar este pedido.');
		}
		check_admin_referer('sait_reenviar_pedido_admin_' . $order_id);

		require_once plugin_dir_path(__FILE__) . 'SAIT_WOOCOMMERCE-orders.php';

		$response = SAIT_WOOCOMMERCE_Orders::SAIT_reenviarPedido($order_id);
		$status = $response instanceof WP_REST_Response ? (int) $response->get_status() : 500;
		$data = $response instanceof WP_REST_Response ? $response->get_data() : 'Respuesta inesperada.';

		$type = ($status >= 200 && $status < 300) ? 'success' : 'warning';
		$message = 'Pedido #' . $order_id . ': ' . self::format_response_message($data);
		self::set_notice($type, $message);

		wp_safe_redirect(self::get_order_redirect_url($order_id));
		exit;
	}

	public function show_notice() {
		$notice = self::get_notice();
		if (!$notice) {
			return;
		}

		echo '<div class="notice notice-' . esc_attr($notice['type']) . ' is-dismissible"><p>' . esc_html($notice['message']) . '</p></div>';
	}

	private static function format_response_message($data) {
		if (is_array($data)) {
			$estado = isset($data['estado']) ? $data['estado'] : '';
			$status_code = isset($data['status_code']) ? $data['status_code'] : '';
			$message = isset($data['message']) ? $data['message'] : '';
			return trim('estado=' . $estado . ' status=' . $status_code . ' ' . wp_strip_all_tags((string) $message));
		}

		return wp_strip_all_tags((string) $data);
	}

	private static function get_order_redirect_url($order_id) {
		$referer = wp_get_referer();
		if ($referer) {
			return $referer;
		}

		return admin_url('post.php?post=' . absint($order_id) . '&action=edit');
	}

	private static function set_notice($type, $message) {
		set_transient(self::NOTICE_TRANSIENT_PREFIX . get_current_user_id(), array(
			'type' => $type,
			'message' => substr((string) $message, 0, 1000),
		), 60);
	}

	private static function get_notice() {
		$key = self::NOTICE_TRANSIENT_PREFIX . get_current_user_id();
		$notice = get_transient($key);
		delete_transient($key);
		return is_array($notice) ? $notice : null;
	}
}

if (is_admin()) {
	new SAIT_WOOCOMMERCE_OrderAdmin();
}
