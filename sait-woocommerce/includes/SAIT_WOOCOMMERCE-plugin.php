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

	/** @var SAIT_WOOCOMMERCE_CustomerResolver|null */
	private $customer_resolver = null;

	/** @var SAIT_WOOCOMMERCE_DocumentService|null */
	private $document_service = null;

	/** @var SAIT_WOOCOMMERCE_ProductResolver|null */
	private $product_resolver = null;

	/** @var SAIT_WOOCOMMERCE_ProductSyncService|null */
	private $product_sync_service = null;

	/** @var SAIT_WOOCOMMERCE_OrderDeliveryState|null */
	private $order_delivery_state = null;

	/** @var SAIT_WOOCOMMERCE_OrderDeliveryScheduler|null */
	private $order_delivery_scheduler = null;

	/** @var SAIT_WOOCOMMERCE_EventParser|null */
	private $event_parser = null;

	/** @var SAIT_WOOCOMMERCE_EventRouter|null */
	private $event_router = null;

	/** @var SAIT_WOOCOMMERCE_Logger|null */
	private $logger = null;

	/** @var SAIT_WOOCOMMERCE_BranchSelector|null */
	private $branch_selector = null;

	/** @var SAIT_WOOCOMMERCE_StockDisplay|null */
	private $stock_display = null;

	/** @var SAIT_WOOCOMMERCE_Promotions|null */
	private $promotions = null;

	/** @var SAIT_WOOCOMMERCE_CartMinimum|null */
	private $cart_minimum = null;

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
		$this->customer_resolver = null;
		$this->document_service = null;
		$this->product_sync_service = null;
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
	 * @return SAIT_WOOCOMMERCE_CustomerResolver
	 */
	public function customer_resolver()
	{
		if ($this->customer_resolver === null) {
			$this->customer_resolver = new SAIT_WOOCOMMERCE_CustomerResolver(
				$this->mapping_repository(),
				$this->sait_client()
			);
		}

		return $this->customer_resolver;
	}

	/**
	 * @return SAIT_WOOCOMMERCE_DocumentService
	 */
	public function document_service()
	{
		if ($this->document_service === null) {
			$this->document_service = new SAIT_WOOCOMMERCE_DocumentService(
				$this->settings(),
				$this->customer_resolver(),
				$this->sait_client(),
				$this->logger()
			);
		}

		return $this->document_service;
	}

	/** @return SAIT_WOOCOMMERCE_ProductResolver */
	public function product_resolver()
	{
		if ($this->product_resolver === null) {
			$this->product_resolver = new SAIT_WOOCOMMERCE_ProductResolver($this->mapping_repository());
		}

		return $this->product_resolver;
	}

	/** @return SAIT_WOOCOMMERCE_ProductSyncService */
	public function product_sync_service()
	{
		if ($this->product_sync_service === null) {
			$this->product_sync_service = new SAIT_WOOCOMMERCE_ProductSyncService(
				$this->product_resolver(),
				$this->sait_client(),
				$this->settings(),
				new SAIT_WOOCOMMERCE_PriceCalculator(),
				new SAIT_WOOCOMMERCE_StockCalculator()
			);
		}

		return $this->product_sync_service;
	}

	/** @return SAIT_WOOCOMMERCE_OrderDeliveryState */
	public function order_delivery_state()
	{
		if ($this->order_delivery_state === null) {
			$this->order_delivery_state = new SAIT_WOOCOMMERCE_OrderDeliveryState();
		}

		return $this->order_delivery_state;
	}

	/** @return SAIT_WOOCOMMERCE_OrderDeliveryScheduler */
	public function order_delivery_scheduler()
	{
		if ($this->order_delivery_scheduler === null) {
			$this->order_delivery_scheduler = new SAIT_WOOCOMMERCE_OrderDeliveryScheduler(
				$this->order_delivery_state()
			);
		}

		return $this->order_delivery_scheduler;
	}

	/** @return SAIT_WOOCOMMERCE_EventParser */
	public function event_parser()
	{
		if ($this->event_parser === null) {
			$this->event_parser = new SAIT_WOOCOMMERCE_EventParser();
		}

		return $this->event_parser;
	}

	/** @return SAIT_WOOCOMMERCE_EventRouter */
	public function event_router()
	{
		if ($this->event_router === null) {
			$this->event_router = new SAIT_WOOCOMMERCE_EventRouter();
		}

		return $this->event_router;
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

	/** @return SAIT_WOOCOMMERCE_BranchSelector */
	public function branch_selector()
	{
		if ($this->branch_selector === null) {
			$this->branch_selector = new SAIT_WOOCOMMERCE_BranchSelector($this->settings(), $this->plugin_file);
		}

		return $this->branch_selector;
	}

	/** @return SAIT_WOOCOMMERCE_StockDisplay */
	public function stock_display()
	{
		if ($this->stock_display === null) {
			$this->stock_display = new SAIT_WOOCOMMERCE_StockDisplay($this->settings(), $this->plugin_file);
		}

		return $this->stock_display;
	}

	/** @return SAIT_WOOCOMMERCE_Promotions */
	public function promotions()
	{
		if ($this->promotions === null) {
			$this->promotions = new SAIT_WOOCOMMERCE_Promotions($this->settings(), $this->plugin_file);
		}

		return $this->promotions;
	}

	/** @return SAIT_WOOCOMMERCE_CartMinimum */
	public function cart_minimum()
	{
		if ($this->cart_minimum === null) {
			$this->cart_minimum = new SAIT_WOOCOMMERCE_CartMinimum($this->settings(), $this->plugin_file);
		}

		return $this->cart_minimum;
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
		$this->order_delivery_scheduler()->enqueue($order_id, '1');
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
		$this->order_delivery_scheduler()->enqueue($order_id, '2');
	}

	/**
	 * Carga assets del selector de sucursal conservando handles historicos.
	 *
	 * @return void
	 */
	public function enqueue_assets()
	{
		$this->branch_selector()->enqueue_assets();
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
		require_once $includes . 'SAIT_WOOCOMMERCE-customer-resolver.php';
		require_once $includes . 'SAIT_WOOCOMMERCE-document-builders.php';
		require_once $includes . 'SAIT_WOOCOMMERCE-document-service.php';
		require_once $includes . 'SAIT_WOOCOMMERCE-product-calculators.php';
		require_once $includes . 'SAIT_WOOCOMMERCE-product-resolver.php';
		require_once $includes . 'SAIT_WOOCOMMERCE-product-sync-service.php';
		require_once $includes . 'SAIT_WOOCOMMERCE-order-delivery-state.php';
		require_once $includes . 'SAIT_WOOCOMMERCE-order-delivery-scheduler.php';
		require_once $includes . 'events/SAIT_WOOCOMMERCE-event-parser.php';
		require_once $includes . 'events/SAIT_WOOCOMMERCE-product-event-handler.php';
		require_once $includes . 'events/SAIT_WOOCOMMERCE-price-stock-event-handler.php';
		require_once $includes . 'events/SAIT_WOOCOMMERCE-category-event-handler.php';
		require_once $includes . 'events/SAIT_WOOCOMMERCE-customer-event-handler.php';
		require_once $includes . 'events/SAIT_WOOCOMMERCE-exchange-rate-event-handler.php';
		require_once $includes . 'events/SAIT_WOOCOMMERCE-event-router.php';
		require_once $includes . 'SAIT_WOOCOMMERCE-logger.php';
		require_once $includes . 'SAIT_UTILS.php';
		require_once $includes . 'frontend/SAIT_WOOCOMMERCE-branch-selector.php';
		require_once $includes . 'frontend/SAIT_WOOCOMMERCE-stock-display.php';
		require_once $includes . 'frontend/SAIT_WOOCOMMERCE-promotions.php';
		require_once $includes . 'frontend/SAIT_WOOCOMMERCE-cart-minimum.php';
		require_once $includes . 'SAIT_WOOCOMMERCE-frontend-compat.php';
		require_once $includes . 'SAIT_WOOCOMMERCE-art-sync.php';
		require_once $includes . 'SAIT_WOOCOMMERCE-personalizado.php';
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
		$this->branch_selector()->register_hooks();
		$this->stock_display()->register_hooks();
		$this->promotions()->register_hooks();
		$this->cart_minimum()->register_hooks();
		add_action(
			SAIT_WOOCOMMERCE_OrderDeliveryScheduler::ACTION,
			array($this->order_delivery_scheduler(), 'process'),
			10,
			2
		);
	}

	/**
	 * @return void
	 */
	private function load_orders()
	{
		require_once plugin_dir_path($this->plugin_file) . 'includes/SAIT_WOOCOMMERCE-orders.php';
	}
}
