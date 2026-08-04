<?php

/**
 * Selector de sucursal mostrado en el frontend.
 */
class SAIT_WOOCOMMERCE_BranchSelector
{
	/** @var SAIT_WOOCOMMERCE_Settings */
	private $settings;

	/** @var string */
	private $plugin_file;

	/** @var string */
	private $template_path;

	public function __construct($settings, $plugin_file)
	{
		$this->settings = $settings;
		$this->plugin_file = $plugin_file;
		$this->template_path = plugin_dir_path($plugin_file) . 'templates/branch-modal.php';
	}

	/** @return void */
	public function register_hooks()
	{
		if (!$this->settings->is_enabled('SAITNube_Sucursal_enabled')) {
			return;
		}

		add_filter('wp_nav_menu_items', array($this, 'add_menu_item'), 10, 2);
		add_action('wp_footer', array($this, 'render_modal'));
		add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
		add_action('wp_ajax_guardar_sucursal', array($this, 'save_branch'));
		add_action('wp_ajax_nopriv_guardar_sucursal', array($this, 'save_branch'));
	}

	/**
	 * @param string $items HTML del menú.
	 * @param object $args Argumentos del menú.
	 * @return string
	 */
	public function add_menu_item($items, $args)
	{
		if (!$this->settings->is_enabled('SAITNube_Sucursal_enabled')) {
			return $items;
		}

		if (!isset($args->theme_location) || $args->theme_location !== 'primary') {
			return $items;
		}

		$warehouse = get_user_meta(get_current_user_id(), 'sucursal_seleccionada', true);
		$label = 'Seleccionar Sucursal';

		if (!empty($warehouse)) {
			$response = SAIT_UTILS::SAIT_GetNube('/api/v3/almacenes');
			$branches = SAIT_UTILS::SAIT_getResult($response);
			$branches = is_array($branches) ? $branches : array();

			foreach ($branches as $branch) {
				if (isset($branch['numalm']) && trim($branch['numalm']) == $warehouse) {
					$label = isset($branch['nomalm']) ? $branch['nomalm'] : $label;
					break;
				}
			}
		}

		$items .= '<li class="menu-item menu-item-sucursal">';
		$items .= '<a href="#" id="sucursal-button" class="sait-sucursal-btn">';
		$items .= '<i class="fa-solid fa-location-dot"></i> ' . esc_html($label);
		$items .= '</a></li>';

		return $items;
	}

	/** @return void */
	public function render_modal()
	{
		if (!$this->settings->is_enabled('SAITNube_Sucursal_enabled')) {
			return;
		}

		$response = SAIT_UTILS::SAIT_GetNube('/api/v3/almacenes');
		$branches = SAIT_UTILS::SAIT_getResult($response);
		$branches = is_array($branches) ? $branches : array();

		include $this->template_path;
	}

	/** @return void */
	public function save_branch()
	{
		check_ajax_referer('sait-woocommerce_nonce', 'nonce');

		if (!isset($_POST['sucursal_id'])) {
			wp_send_json_error('Error al guardar la sucursal.');
		}

		$branch_id = absint(wp_unslash($_POST['sucursal_id']));
		update_user_meta(get_current_user_id(), 'sucursal_seleccionada', $branch_id);
		$branch_id = get_user_meta(get_current_user_id(), 'sucursal_seleccionada', true);

		wp_send_json_success($branch_id);
	}

	/** @return void */
	public function enqueue_assets()
	{
		if (is_admin() || !$this->settings->is_enabled('SAITNube_Sucursal_enabled')) {
			return;
		}

		$base_url = plugin_dir_url($this->plugin_file);
		wp_enqueue_style(
			'font-awesome',
			'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
			array(),
			'6.4.0'
		);
		wp_enqueue_style('modal-style', $base_url . 'assets/css/modal.css', array(), '1.0');
		wp_enqueue_script(
			'sait-modal-script',
			$base_url . 'assets/js/modal.js',
			array('jquery'),
			'1.0',
			true
		);
		wp_localize_script(
			'sait-modal-script',
			'sait_woocommerce_ajax',
			array(
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce'    => wp_create_nonce('sait-woocommerce_nonce'),
			)
		);
	}
}
