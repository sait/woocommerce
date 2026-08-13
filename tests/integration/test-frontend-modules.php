<?php

if (!defined('ABSPATH')) {
	exit(1);
}

function sait_frontend_assert_true($condition, $message)
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

class SAIT_Test_Frontend_Settings
{
	/** @var array<string, bool> */
	private $enabled;

	public function __construct($enabled = array())
	{
		$this->enabled = $enabled;
	}

	public function is_enabled($key)
	{
		return !empty($this->enabled[$key]);
	}

	public function get($key, $default = '')
	{
		return $default;
	}

	public function warehouses()
	{
		return array('1', '2');
	}
}

class SAIT_Test_Frontend_Session
{
	/** @var array<string, mixed> */
	public $data = array();

	/** @var bool */
	public $cookie_requested = false;

	public function get($key, $default = null)
	{
		return isset($this->data[$key]) ? $this->data[$key] : $default;
	}

	public function set($key, $value)
	{
		$this->data[$key] = $value;
	}

	public function set_customer_session_cookie($set)
	{
		$this->cookie_requested = (bool) $set;
	}
}

$plugin_file = WP_PLUGIN_DIR . '/sait-woocommerce/SAIT_WOOCOMMERCE.php';
$disabled_settings = new SAIT_Test_Frontend_Settings();
$disabled_selector = new SAIT_WOOCOMMERCE_BranchSelector($disabled_settings, $plugin_file);
$disabled_selector->register_hooks();
sait_frontend_assert_true(
	has_filter('wp_nav_menu_items', array($disabled_selector, 'add_menu_item')) === false,
	'El selector desactivado no debe registrar hooks.'
);

wp_dequeue_style('modal-style');
wp_deregister_style('modal-style');
wp_dequeue_script('sait-modal-script');
wp_deregister_script('sait-modal-script');
$disabled_selector->enqueue_assets();
sait_frontend_assert_true(
	!wp_style_is('modal-style', 'registered') && !wp_script_is('sait-modal-script', 'registered'),
	'El selector desactivado no debe registrar assets.'
);

$selector_settings = new SAIT_Test_Frontend_Settings(array('SAITNube_Sucursal_enabled' => true));
$selector = new SAIT_WOOCOMMERCE_BranchSelector($selector_settings, $plugin_file);
$selector->register_hooks();
sait_frontend_assert_true(
	has_filter('wp_nav_menu_items', array($selector, 'add_menu_item')) === 10,
	'El selector activo debe registrar el filtro del menú.'
);
sait_frontend_assert_true(
	has_action('wp_footer', array($selector, 'render_modal')) === 10,
	'El selector activo debe registrar su modal.'
);
$selector->enqueue_assets();
sait_frontend_assert_true(wp_style_is('modal-style', 'enqueued'), 'Debe encolar el estilo del modal.');
sait_frontend_assert_true(wp_script_is('sait-modal-script', 'enqueued'), 'Debe encolar el script del modal.');
sait_frontend_assert_true(
	!wp_script_is('sait-personalizado-script', 'enqueued'),
	'El selector no debe cargar el script de personalizaciones de checkout.'
);

$original_user_id = get_current_user_id();
$original_session = WC()->session;
$guest_session = new SAIT_Test_Frontend_Session();
wp_set_current_user(0);
WC()->session = $guest_session;
global $wpdb;
$anonymous_meta_before = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT umeta_id, meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s ORDER BY umeta_id",
		0,
		'sucursal_seleccionada'
	),
	ARRAY_A
);
$stored_branch = $selector->persist_selected_branch(2);
sait_frontend_assert_true($stored_branch === 2, 'Debe devolver la sucursal guardada para el invitado.');
sait_frontend_assert_true(
	$guest_session->get(SAIT_WOOCOMMERCE_BranchSelector::SESSION_KEY) === 2,
	'Debe guardar la sucursal en la sesión WooCommerce del invitado.'
);
sait_frontend_assert_true(
	$guest_session->cookie_requested,
	'Debe solicitar la cookie de sesión WooCommerce para persistir al invitado.'
);
$anonymous_meta_after = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT umeta_id, meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s ORDER BY umeta_id",
		0,
		'sucursal_seleccionada'
	),
	ARRAY_A
);
sait_frontend_assert_true(
	$anonymous_meta_after === $anonymous_meta_before,
	'Nunca debe crear ni modificar metadatos de sucursal para el usuario 0.'
);
sait_frontend_assert_true(
	$selector->get_selected_branch() === 2,
	'Debe leer para promociones la misma sucursal guardada en sesión.'
);
WC()->session = $original_session;
wp_set_current_user($original_user_id);

$stock_settings = new SAIT_Test_Frontend_Settings(
	array(
		'SAITNube_ExistAlm_enabled'        => true,
		'SAITNube_OcultarSinPrecio_enabled' => true,
	)
);
$stock = new SAIT_WOOCOMMERCE_StockDisplay($stock_settings, $plugin_file);
$stock->register_hooks();
sait_frontend_assert_true(
	has_action('woocommerce_single_product_summary', array($stock, 'render_stock_table')) === 25,
	'Existencias debe registrar la tabla en producto.'
);
sait_frontend_assert_true(
	has_action('woocommerce_product_query', array($stock, 'hide_products_without_price')) === 10,
	'Catálogo debe registrar el filtro de productos sin precio.'
);

$promotion_settings = new SAIT_Test_Frontend_Settings(
	array(
		'SAITNube_PromoGlobal_enabled' => true,
		'SAITNube_Promo_enabled'       => true,
	)
);
$promotions = new SAIT_WOOCOMMERCE_Promotions($promotion_settings, $plugin_file);
$promotions->register_hooks();
sait_frontend_assert_true(
	has_filter('woocommerce_get_price_html', array($promotions, 'filter_price_html')) === 30,
	'Promociones debe registrar el precio global.'
);
sait_frontend_assert_true(
	has_action('woocommerce_before_calculate_totals', array($promotions, 'apply_cart_prices')) === 10,
	'Promociones debe registrar el precio del carrito.'
);

$minimum_settings = new SAIT_Test_Frontend_Settings(array('SAITNube_MinimoCarrito_Enabled' => true));
$minimum = new SAIT_WOOCOMMERCE_CartMinimum($minimum_settings, $plugin_file);
$minimum->register_hooks();
sait_frontend_assert_true(
	has_action('woocommerce_checkout_process', array($minimum, 'validate')) === 10,
	'El mínimo debe validar checkout.'
);
sait_frontend_assert_true(
	has_action('wp_footer', array($minimum, 'render_button_guard')) === 10,
	'El mínimo debe registrar su guardia visual.'
);

$legacy_functions = array(
	'agregar_boton_al_menu',
	'agregar_modal_sucursal',
	'guardar_sucursal',
	'mostrar_tabla_almacenes',
	'ocultar_productos_sin_precio',
	'sait_precio_promocional_en_producto',
	'calcularpreciosCarrito',
	'display_discounted_price_in_cart',
	'sait_minimo_total_carrito',
	'sait_bloquear_botones_checkout',
);
foreach ($legacy_functions as $legacy_function) {
	sait_frontend_assert_true(function_exists($legacy_function), 'Falta adaptador global: ' . $legacy_function);
}

$templates = array('branch-modal.php', 'stock-table.php', 'promotion-price.php', 'cart-minimum-script.php');
foreach ($templates as $template) {
	sait_frontend_assert_true(
		is_readable(WP_PLUGIN_DIR . '/sait-woocommerce/templates/' . $template),
		'Falta template frontend: ' . $template
	);
}

echo "Módulos frontend SAIT registrados correctamente.\n";
