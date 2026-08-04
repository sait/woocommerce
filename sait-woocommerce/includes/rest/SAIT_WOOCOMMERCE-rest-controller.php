<?php

/**
 * Registra y atiende las rutas REST publicas del plugin.
 */
class SAIT_WOOCOMMERCE_REST_Controller extends WP_REST_Controller
{
	/**
	 * Configura el namespace historico.
	 */
	public function __construct()
	{
		$this->namespace = 'saitplugin/v1';
	}

	/**
	 * Registra las cuatro rutas existentes en 1.2.3.
	 *
	 * @return void
	 */
	public function register_routes()
	{
		register_rest_route(
			$this->namespace,
			'/hello',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'hello'),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$this->namespace,
			'/saitevents',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array($this, 'process_events'),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$this->namespace,
			'/reenviar-pedido-sait/(?P<idpedido>\d+)',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array($this, 'resend_order'),
				'permission_callback' => array($this, 'resend_order_permissions_check'),
				'args'                => $this->get_resend_order_args(),
			)
		);

		register_rest_route(
			$this->namespace,
			'/testpedido/(?P<idpedido>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array($this, 'resend_order'),
				'permission_callback' => array($this, 'resend_order_permissions_check'),
				'args'                => $this->get_resend_order_args(),
			)
		);
	}

	/**
	 * Define el esquema compartido por las rutas de reenvio.
	 *
	 * @return array
	 */
	private function get_resend_order_args()
	{
		return array(
			'idpedido' => array(
				'description' => 'ID de la orden WooCommerce que se reenviara a SAIT.',
				'type'        => 'integer',
				'minimum'     => 1,
				'required'    => true,
			),
		);
	}

	/**
	 * Restringe el reenvio a usuarios que administran ordenes WooCommerce.
	 *
	 * @param WP_REST_Request $request Peticion REST.
	 * @return true|WP_Error
	 */
	public function resend_order_permissions_check($request)
	{
		if (!is_user_logged_in()) {
			return new WP_Error(
				'rest_not_logged_in',
				'Autenticacion requerida para reenviar pedidos.',
				array('status' => 401)
			);
		}

		if (!current_user_can('edit_shop_orders')) {
			return new WP_Error(
				'rest_forbidden',
				'No tienes permisos para reenviar este pedido.',
				array('status' => 403)
			);
		}

		$order_id = absint($request['idpedido']);
		$order = $order_id ? wc_get_order($order_id) : false;
		if ($order && !current_user_can('edit_shop_order', $order_id)) {
			return new WP_Error(
				'rest_forbidden_order',
				'No tienes permisos para editar este pedido.',
				array('status' => 403)
			);
		}

		return true;
	}

	/**
	 * Devuelve la respuesta de prueba historica.
	 *
	 * @param WP_REST_Request|null $request Peticion REST, no utilizada.
	 * @return string
	 */
	public function hello($request = null)
	{
		require_once dirname(__DIR__) . '/SAIT_WOOCOMMERCE-hello.php';
		return SAIT_WOOCOMMERCE_Hello::SAIT_helloworld();
	}

	/**
	 * Procesa el XML recibido desde SAIT.
	 *
	 * Conserva literalmente la validacion, parseo y respuestas de 1.2.3.
	 *
	 * @param WP_REST_Request $request Peticion con token y XML.
	 * @return WP_REST_Response
	 */
	public function process_events($request)
	{
		$access_token = $request->get_header('x-AccessToken');
		$sait_access_token = SAIT_WOOCOMMERCE()->settings()->get('SAITNube_AccessToken', '');
		if (!hash_equals((string) $sait_access_token, (string) $access_token)) {
			$response = new WP_REST_Response();
			$response->set_status(401);
			$response->set_data('Bad token');
			return $response;
		}

		$event = SAIT_WOOCOMMERCE()->event_parser()->parse($request->get_body());
		if (is_wp_error($event)) {
			$response = new WP_REST_Response();
			$response->set_status((int) $event->get_error_data()['status']);
			$response->set_data($event->get_error_message());
			return $response;
		}

		require_once dirname(__DIR__) . '/SAIT_WOOCOMMERCE-process-events.php';
		return SAIT_WOOCOMMERCE_ProcessEvents::SAIT_processEvent($event);
	}

	/**
	 * Reenvia una orden como pedido o cotizacion.
	 *
	 * @param WP_REST_Request $request Peticion con idpedido.
	 * @return WP_REST_Response
	 */
	public function resend_order($request)
	{
		require_once dirname(__DIR__) . '/SAIT_WOOCOMMERCE-orders.php';
		$order_id = absint($request['idpedido']);
		return SAIT_WOOCOMMERCE_Orders::SAIT_reenviarPedido($order_id);
	}
}
