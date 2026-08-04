<?php

/**
 * Bootstrap y registro central de hooks de SAIT WooCommerce.
 */
class SAIT_WOOCOMMERCE_Plugin
{
	/** @var string */
	private $plugin_file;

	/** @var SAIT_WOOCOMMERCE_REST_Controller|null */
	private $rest_controller = null;

	/** @var SAIT_WOOCOMMERCE_Settings|null */
	private $settings = null;

	/** @var SAITSettingsPage|null */
	private $settings_page = null;

	/** @var SAIT_WOOCOMMERCE_SaitClientInterface|null */
	private $sait_client = null;

	/** @var SAIT_WOOCOMMERCE_MappingRepository|null */
	private $mapping_repository = null;

	/** @var SAIT_WOOCOMMERCE_Logger|null */
	private $logger = null;

	/**
	 * @param string $plugin_file Archivo principal del plugin.
	 */
	public function __construct($plugin_file)
	{
		$this->plugin_file = $plugin_file;
	}

	/**
	 * Carga dependencias y registra hooks.
	 *
	 * @return void
	 */
	public function run()
	{
		$this->load_dependencies();
		$this->register_hooks();
	}

	/**
	 * @return SAIT_WOOCOMMERCE_REST_Controller
	 */
	public function rest_controller()
	{
		if ($this->rest_controller === null) {
			$this->rest_controller = new SAIT_WOOCOMMERCE_REST_Controller();
		}

		return $this->rest_controller;
	}

	/**
	 * @return SAIT_WOOCOMMERCE_Settings
	 */
	public function settings()
	{
		if ($this->settings === null) {
			$this->settings = new SAIT_WOOCOMMERCE_Settings();
		}

		return $this->settings;
	}

	/**
	 * @return SAIT_WOOCOMMERCE_SaitClientInterface
	 */
	public function sait_client()
	{
		if ($this->sait_client === null) {
			$this->sait_client = new SAIT_WOOCOMMERCE_SaitClient($this->settings());
		}

		return $this->sait_client;
	}

	/**
	 * Permite sustituir el transporte por un cliente falso en pruebas.
	 *
	 * @param SAIT_WOOCOMMERCE_SaitClientInterface $client Cliente inyectado.
	 * @return void
	 */
	public function set_sait_client(SAIT_WOOCOMMERCE_SaitClientInterface $client)
	{
		$this->sait_client = $client;
	}

	/**
	 * @return SAIT_WOOCOMMERCE_MappingRepository
	 */
	public function mapping_repository()
	{
		if ($this->mapping_repository === null) {
			$this->mapping_repository = new SAIT_WOOCOMMERCE_MappingRepository();
		}

		return $this->mapping_repository;
	}

	/**
	 * @return SAIT_WOOCOMMERCE_Logger
	 */
	public function logger()
	{
		if ($this->logger === null) {
			$this->logger = new SAIT_WOOCOMMERCE_Logger();
		}

		return $this->logger;
	}

	/**
	 * Registra las rutas REST.
	 *
	 * @return void
	 */
	public function register_rest_routes()
	{
		$this->rest_controller()->register_routes();
	}

	/**
	 * Envia una orden pagada con forma de pago 1.
	 *
	 * @param int $order_id ID WooCommerce.
	 * @return void
	 */
	public function send_order_payment($order_id)
	{
		$this->load_orders();
		SAIT_WOOCOMMERCE_Orders::SAIT_sendOrder($order_id, '1');
	}

	/**
	 * Envia una orden sin pago confirmado con forma de pago 2.
	 *
	 * @param int $order_id ID WooCommerce.
	 * @return void
	 */
	public function send_order_thankyou($order_id)
	{
		$this->load_orders();
		SAIT_WOOCOMMERCE_Orders::SAIT_sendOrder($order_id, '2');
	}

	/**
	 * Carga assets del selector de sucursal conservando handles historicos.
	 *
	 * @return void
	 */
	public function enqueue_assets()
	{
		if (!$this->settings()->is_enabled('SAITNube_Sucursal_enabled') || is_admin()) {
			return;
		}

		wp_enqueue_style(
			'font-awesome',
			'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
			array(),
			'6.4.0'
		);
		wp_enqueue_style('modal-style', plugin_dir_url($this->plugin_file) . 'assets/css/modal.css');
		wp_enqueue_script(
			'sait-modal-script',
			plugin_dir_url($this->plugin_file) . 'assets/js/modal.js',
			array('jquery'),
			'1.0',
			true
		);
		wp_enqueue_script(
			'sait-personalizado-script',
			plugins_url('../assets/js/personalizado.js', $this->plugin_file),
			array('jquery'),
			'1.0',
			true
		);
		wp_register_script(
			'modal-script',
			false,
			array('sait-personalizado-script'),
			'1.0',
			true
		);
		wp_enqueue_script('modal-script');
		wp_localize_script(
			'sait-modal-script',
			'sait_woocommerce_ajax',
			array(
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce'    => wp_create_nonce('sait-woocommerce_nonce'),
			)
		);
	}

	/**
	 * @return void
	 */
	private function load_dependencies()
	{
		$includes = plugin_dir_path($this->plugin_file) . 'includes/';
		require_once $includes . 'SAIT_WOOCOMMERCE-settings.php';
		require_once $includes . 'SAIT_WOOCOMMERCE-sait-client.php';
		require_once $includes . 'SAIT_WOOCOMMERCE-mapping-repository.php';
		require_once $includes . 'SAIT_WOOCOMMERCE-logger.php';
		require_once $includes . 'SAIT_UTILS.php';
		require_once $includes . 'SAIT_WOOCOMMERCE-art-sync.php';
		require_once $includes . 'SAIT_WOOCOMMERCE-personalizado.php';
		require_once $includes . 'SAIT_WOOCOMMERCE-cart.php';
		require_once $includes . 'rest/SAIT_WOOCOMMERCE-rest-controller.php';

		if (is_admin()) {
			require_once $includes . 'SAIT_WOOCOMMERCE-options.php';
			require_once $includes . 'SAIT_WOOCOMMERCE-order-admin.php';
			$this->settings_page = new SAITSettingsPage($this->settings());
		}
	}

	/**
	 * @return void
	 */
	private function register_hooks()
	{
		add_action('plugins_loaded', array('SAIT_WOOCOMMERCE_Lifecycle', 'maybe_upgrade'), 5);
		add_action('rest_api_init', array($this, 'register_rest_routes'));
		add_action('woocommerce_payment_complete', array($this, 'send_order_payment'), 10, 2);
		add_action('woocommerce_thankyou', array($this, 'send_order_thankyou'), 10, 2);
		add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
	}

	/**
	 * @return void
	 */
	private function load_orders()
	{
		require_once plugin_dir_path($this->plugin_file) . 'includes/SAIT_WOOCOMMERCE-orders.php';
	}
}
