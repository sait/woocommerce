<?php

/**
 * Herramientas administrativas para sincronizar precios y existencias desde SAITNube.
 *
 * @package    SAIT_WOOCOMMERCE
 * @subpackage SAIT_WOOCOMMERCE/includes
 */

class SAIT_WOOCOMMERCE_ArtSync {
	const STATUS_OPTION = 'sait_art_sync_status';
	const BATCH_ACTION = 'sait_sync_articulos_batch';
	const BATCH_SIZE = 200;
	const MAX_BATCHES = 100;

	public function __construct() {
		add_action('admin_post_sait_sync_articulo_sku', array($this, 'handle_sync_sku'));
		add_action('admin_post_sait_sync_articulo_product', array($this, 'handle_sync_product'));
		add_action('admin_post_sait_sync_articulos_start', array($this, 'handle_start_batch'));
		add_action(self::BATCH_ACTION, array($this, 'process_batch'), 10, 2);
		add_filter('post_row_actions', array($this, 'add_product_row_action'), 10, 2);
		add_action('admin_notices', array($this, 'show_product_list_notice'));
	}

	/**
	 * Renderiza los controles de sincronizacion en la pagina de ajustes.
	 *
	 * @return void
	 */
	public static function render_admin_section() {
		if (!current_user_can('manage_options')) {
			return;
		}

		$notice = self::get_notice();
		$status = self::get_status();
		?>
		<hr>
		<h2>Sincronizacion de articulos</h2>
		<p>Usa estas herramientas cuando no se generaron eventos <code>ACTPRECIO</code>/<code>ACTEXIST</code> o necesitas corregir articulos desde SAITNube.</p>

		<?php if ($notice) : ?>
			<div class="notice notice-<?php echo esc_attr($notice['type']); ?> is-dismissible">
				<p><?php echo esc_html($notice['message']); ?></p>
			</div>
		<?php endif; ?>

		<table class="widefat striped" style="max-width: 760px; margin-bottom: 16px;">
			<tbody>
				<tr>
					<th scope="row">Estado</th>
					<td><?php echo esc_html(isset($status['estado']) ? $status['estado'] : 'sin ejecutar'); ?></td>
				</tr>
				<tr>
					<th scope="row">Procesados</th>
					<td><?php echo esc_html(isset($status['procesados']) ? $status['procesados'] : 0); ?></td>
				</tr>
				<tr>
					<th scope="row">Actualizados</th>
					<td><?php echo esc_html(isset($status['actualizados']) ? $status['actualizados'] : 0); ?></td>
				</tr>
				<tr>
					<th scope="row">Existencias actualizadas</th>
					<td><?php echo esc_html(isset($status['existencias_actualizadas']) ? $status['existencias_actualizadas'] : 0); ?></td>
				</tr>
				<tr>
					<th scope="row">Ignorados</th>
					<td><?php echo esc_html(isset($status['ignorados']) ? $status['ignorados'] : 0); ?></td>
				</tr>
				<tr>
					<th scope="row">Errores</th>
					<td><?php echo esc_html(isset($status['errores']) ? $status['errores'] : 0); ?></td>
				</tr>
				<tr>
					<th scope="row">Lotes</th>
					<td><?php echo esc_html((isset($status['lotes']) ? $status['lotes'] : 0) . ' / ' . (isset($status['max_lotes']) ? $status['max_lotes'] : self::MAX_BATCHES)); ?></td>
				</tr>
				<?php if (!empty($status['ultimo_error'])) : ?>
					<tr>
						<th scope="row">Ultimo aviso</th>
						<td><?php echo esc_html($status['ultimo_error']); ?></td>
					</tr>
				<?php endif; ?>
				<tr>
					<th scope="row">Ultima ejecucion</th>
					<td><?php echo esc_html(isset($status['actualizado_at']) ? $status['actualizado_at'] : ''); ?></td>
				</tr>
			</tbody>
		</table>

		<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-bottom: 16px;">
			<input type="hidden" name="action" value="sait_sync_articulo_sku">
			<?php wp_nonce_field('sait_sync_articulo_sku'); ?>
			<label for="sait_sync_articulo_sku"><strong>Sincronizar articulo por SKU</strong></label><br>
			<input type="text" id="sait_sync_articulo_sku" name="sku" class="regular-text" placeholder="SKU / numart">
			<?php submit_button('Sincronizar SKU', 'secondary', 'submit', false); ?>
		</form>

		<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
			<input type="hidden" name="action" value="sait_sync_articulos_start">
			<?php wp_nonce_field('sait_sync_articulos_start'); ?>
			<?php submit_button('Sincronizar todos los articulos', 'primary', 'submit', false); ?>
			<p class="description">El proceso se ejecuta por lotes de <?php echo esc_html(self::BATCH_SIZE); ?> articulos. Usa Action Scheduler si esta disponible; si no, usa WP-Cron.</p>
		</form>
		<?php
	}

	public function handle_sync_sku() {
		if (!current_user_can('manage_options')) {
			wp_die('No tienes permisos para ejecutar esta accion.');
		}
		check_admin_referer('sait_sync_articulo_sku');

		$sku = isset($_POST['sku']) ? sanitize_text_field(wp_unslash($_POST['sku'])) : '';
		if ($sku === '') {
			self::set_notice('error', 'Captura un SKU para sincronizar.');
			$this->redirect_settings();
		}

		$result = self::sync_sku($sku, 'manual_sku');
		$type = $result['estado'] === 'actualizado' || $result['estado'] === 'sin_cambio' ? 'success' : 'warning';
		self::set_notice($type, 'SKU ' . $sku . ': ' . $result['mensaje']);
		$this->redirect_settings();
	}

	public function handle_sync_product() {
		$product_id = isset($_GET['product_id']) ? absint($_GET['product_id']) : 0;
		if (!$product_id || !current_user_can('edit_post', $product_id)) {
			wp_die('No tienes permisos para sincronizar este articulo.');
		}
		check_admin_referer('sait_sync_articulo_product_' . $product_id);

		$product = wc_get_product($product_id);
		if (!$product) {
			self::set_notice('error', 'No se encontro el producto de WooCommerce.');
			$this->redirect_products();
		}

		$sku = $product->get_sku();
		if (empty($sku)) {
			self::set_notice('warning', 'El producto no tiene SKU/numart para consultar SAITNube.');
			$this->redirect_products();
		}

		$result = self::sync_sku($sku, 'product_row');
		$type = $result['estado'] === 'actualizado' || $result['estado'] === 'sin_cambio' ? 'success' : 'warning';
		self::set_notice($type, 'Producto ' . $product_id . ' / SKU ' . $sku . ': ' . $result['mensaje']);
		$this->redirect_products();
	}

	public function add_product_row_action($actions, $post) {
		if (!isset($post->post_type) || $post->post_type !== 'product') {
			return $actions;
		}

		if (!current_user_can('edit_post', $post->ID)) {
			return $actions;
		}

		$url = wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'sait_sync_articulo_product',
					'product_id' => $post->ID,
				),
				admin_url('admin-post.php')
			),
			'sait_sync_articulo_product_' . $post->ID
		);

		$actions['sait_sync_articulo'] = '<a href="' . esc_url($url) . '">Sincronizar SAIT</a>';
		return $actions;
	}

	public function show_product_list_notice() {
		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		if (!$screen || $screen->id !== 'edit-product') {
			return;
		}

		$notice = self::get_notice();
		if (!$notice) {
			return;
		}

		echo '<div class="notice notice-' . esc_attr($notice['type']) . ' is-dismissible"><p>' . esc_html($notice['message']) . '</p></div>';
	}

	public function handle_start_batch() {
		if (!current_user_can('manage_options')) {
			wp_die('No tienes permisos para ejecutar esta accion.');
		}
		check_admin_referer('sait_sync_articulos_start');

		self::save_status(array(
			'estado' => 'programado',
			'offset' => 0,
			'limit' => self::BATCH_SIZE,
			'procesados' => 0,
			'actualizados' => 0,
			'existencias_actualizadas' => 0,
			'ignorados' => 0,
			'errores' => 0,
			'lotes' => 0,
			'max_lotes' => self::MAX_BATCHES,
			'iniciado_at' => current_time('mysql'),
			'actualizado_at' => current_time('mysql'),
			'ultimo_error' => '',
		));

		self::schedule_batch(0, self::BATCH_SIZE);
		self::set_notice('success', 'Sincronizacion masiva de articulos programada.');
		$this->redirect_settings();
	}

	/**
	 * Procesa un lote de articulos desde SAITNube.
	 *
	 * @param int $offset Offset solicitado a SAITNube.
	 * @param int $limit Tamano del lote.
	 * @return void
	 */
	public function process_batch($offset = 0, $limit = self::BATCH_SIZE) {
		$offset = absint($offset);
		$limit = absint($limit);
		if ($limit <= 0) {
			$limit = self::BATCH_SIZE;
		}

		$status = self::get_status();
		$status['estado'] = 'ejecutando';
		$status['offset'] = $offset;
		$status['limit'] = $limit;
		$status['lotes'] = isset($status['lotes']) ? absint($status['lotes']) + 1 : 1;
		$status['actualizado_at'] = current_time('mysql');
		self::save_status($status);

		$response = SAIT_UTILS::SAIT_GetNube('/api/v3/articulos?statusweb=1&limit=' . $limit . '&offset=' . $offset);
		$rows = SAIT_UTILS::SAIT_getResult($response);

		if (empty($rows) || !is_array($rows)) {
			$status['estado'] = 'finalizado';
			$status['actualizado_at'] = current_time('mysql');
			self::save_status($status);
			return;
		}

		foreach ($rows as $row) {
			$numart = isset($row['numart']) ? trim($row['numart']) : '';
			if ($numart === '') {
				$status['ignorados']++;
				continue;
			}

			$result = self::sync_product_from_api_row($numart, $row, 'batch');
			$status['procesados']++;

			if ($result['estado'] === 'actualizado') {
				$status['actualizados']++;
			} elseif ($result['estado'] === 'error') {
				$status['errores']++;
				$status['ultimo_error'] = $numart . ': ' . $result['mensaje'];
			} else {
				$status['ignorados']++;
			}

			if (!empty($result['existencia_actualizada'])) {
				$status['existencias_actualizadas']++;
			}
		}

		$status['offset'] = $offset + count($rows);
		$status['actualizado_at'] = current_time('mysql');

		if (count($rows) >= $limit && $status['lotes'] < self::MAX_BATCHES) {
			$status['estado'] = 'programado';
			self::save_status($status);
			self::schedule_batch($offset + $limit, $limit);
			return;
		}

		$status['estado'] = 'finalizado';
		if (count($rows) >= $limit && $status['lotes'] >= self::MAX_BATCHES) {
			$status['ultimo_error'] = 'Proceso detenido por limite de seguridad de lotes.';
		}
		self::save_status($status);
	}

	public static function sync_sku($sku, $source = 'manual') {
		$sku = trim($sku);
		if ($sku === '') {
			return array('estado' => 'error', 'mensaje' => 'SKU vacio.');
		}

		$response = SAIT_UTILS::SAIT_GetNube('/api/v3/articulos/' . rawurlencode($sku));
		$row = SAIT_UTILS::SAIT_getResult($response);

		if (empty($row) || !is_array($row)) {
			return array('estado' => 'error', 'mensaje' => 'SAITNube no regreso datos del articulo.');
		}

		return self::sync_product_from_api_row($sku, $row, $source);
	}

	public static function sync_product_from_api_row($numart, $row, $source = 'manual') {
		$product = self::get_product_by_numart($numart);
		if (!$product) {
			return array('estado' => 'ignorado', 'mensaje' => 'Producto no existe en WooCommerce.');
		}

		$current_price = (float) $product->get_regular_price();
		$price = self::calculate_price_from_api_row($row);
		$price_changed = false;
		$price_status = 'sin_precio_valido';

		if ($price > 0) {
			if (round($current_price, 2) === round($price, 2)) {
				$price_status = 'sin_cambio';
			} else {
				$product->set_regular_price($price);
				$product->set_price($price);
				$price_changed = true;
				$price_status = 'actualizado';
			}
			self::save_product_sync_meta($product, $source, $current_price, $price, $price_status);
		}

		$stock_result = self::sync_stock_for_product($product, $numart, $source);
		$product->save();

		$stock_changed = !empty($stock_result['actualizado']);
		if ($price_changed || $stock_changed) {
			return array(
				'estado' => 'actualizado',
				'mensaje' => self::build_sync_message($current_price, $price, $price_status, $stock_result),
				'existencia_actualizada' => $stock_changed,
			);
		}

		if ($price_status === 'sin_precio_valido' && empty($stock_result['sincronizado'])) {
			return array(
				'estado' => 'ignorado',
				'mensaje' => 'SAITNube no regreso precio ni existencia validos.',
				'existencia_actualizada' => false,
			);
		}

		return array(
			'estado' => 'sin_cambio',
			'mensaje' => self::build_sync_message($current_price, $price, $price_status, $stock_result),
			'existencia_actualizada' => false,
		);
	}

	private static function calculate_price_from_api_row($row) {
		$SAIT_options = get_option('opciones_sait');
		$preciolista = isset($SAIT_options['SAITNube_PrecioLista']) ? trim($SAIT_options['SAITNube_PrecioLista']) : '';
		$tc = isset($SAIT_options['SAITNube_TipoCambio']) ? (float) $SAIT_options['SAITNube_TipoCambio'] : 0;
		$price = 0;

		if ($preciolista !== '' && isset($row['precio' . $preciolista])) {
			$base = (float) $row['precio' . $preciolista];
			if ($base > 0) {
				$impuesto1 = isset($row['impuesto1']) ? (float) $row['impuesto1'] : 0;
				$impuesto2 = isset($row['impuesto2']) ? (float) $row['impuesto2'] : 0;
				$price = round($base * (1 + ($impuesto1 + $impuesto2) / 100), 2);
			}
		}

		if ($price <= 0 && isset($row['preciopub'])) {
			$price = (float) $row['preciopub'];
		}

		if (isset($row['divisa']) && $row['divisa'] === 'D' && $tc > 0 && $price > 0) {
			$price = round($price * $tc, 2);
		}

		return round((float) $price, 2);
	}

	private static function get_product_by_numart($numart) {
		$clave = SAIT_UTILS::SAIT_getClaves('arts', $numart, null);
		if (!empty($clave->wcid)) {
			$product = wc_get_product($clave->wcid);
			if ($product) {
				return $product;
			}
		}

		$product_id = wc_get_product_id_by_sku($numart);
		return $product_id ? wc_get_product($product_id) : false;
	}

	private static function sync_stock_for_product($product, $numart, $source) {
		$stock_data = self::calculate_stock_from_sait($numart);
		$old_stock = $product->get_stock_quantity();

		if (empty($stock_data['sincronizado'])) {
			self::save_stock_sync_meta($product, $source, $old_stock, null, 'sin_datos');
			return array(
				'sincronizado' => false,
				'actualizado' => false,
				'mensaje' => 'Existencia no disponible.',
			);
		}

		$new_stock = $stock_data['existencia'];
		$product->set_manage_stock(true);
		self::save_stock_sync_meta($product, $source, $old_stock, $new_stock, 'sin_cambio');

		if ((float) $old_stock === (float) $new_stock) {
			return array(
				'sincronizado' => true,
				'actualizado' => false,
				'mensaje' => 'Existencia sin cambio.',
			);
		}

		$product->set_stock_quantity($new_stock);
		self::save_stock_sync_meta($product, $source, $old_stock, $new_stock, 'actualizado');

		return array(
			'sincronizado' => true,
			'actualizado' => true,
			'mensaje' => 'Existencia actualizada de ' . (float) $old_stock . ' a ' . $new_stock . '.',
		);
	}

	private static function calculate_stock_from_sait($numart) {
		$SAIT_options = get_option('opciones_sait');
		if (empty($SAIT_options)) {
			return array('sincronizado' => false, 'existencia' => 0);
		}

		$response = SAIT_UTILS::SAIT_GetNube('/api/v3/existencias/' . rawurlencode(trim($numart)));
		$result = SAIT_UTILS::SAIT_getResult($response);
		if (!is_array($result)) {
			return array('sincronizado' => false, 'existencia' => 0);
		}

		$NumAlm = isset($SAIT_options['SAITNube_NumAlm']) ? trim($SAIT_options['SAITNube_NumAlm']) : '';
		$ExistAlm_activo = isset($SAIT_options['SAITNube_ExistAlm_enabled']) && $SAIT_options['SAITNube_ExistAlm_enabled'] === '1';
		$almacenes_a_mostrar = array();

		if ($ExistAlm_activo && !empty($SAIT_options['SAITNube_ExistAlm'])) {
			$almacenes_a_mostrar = array_filter(array_map('trim', explode(',', $SAIT_options['SAITNube_ExistAlm'])));
		}

		$quantity = 0;
		$matched = false;

		foreach ($result as $almacen) {
			$almacen_num = isset($almacen['numalm']) ? trim($almacen['numalm']) : '';
			$existencia = isset($almacen['existencia']) ? (float) $almacen['existencia'] : 0;

			if ($ExistAlm_activo) {
				if (in_array($almacen_num, $almacenes_a_mostrar, true)) {
					$quantity += $existencia;
					$matched = true;
				}
				continue;
			}

			if ($almacen_num === $NumAlm) {
				$quantity = $existencia;
				$matched = true;
				break;
			}
		}

		return array(
			'sincronizado' => $matched,
			'existencia' => round($quantity, 2),
		);
	}

	private static function save_product_sync_meta($product, $source, $old_price, $new_price, $status) {
		$product->update_meta_data('_sait_art_sync_at', current_time('mysql'));
		$product->update_meta_data('_sait_art_sync_source', $source);
		$product->update_meta_data('_sait_art_sync_status', $status);
		$product->update_meta_data('_sait_precio_anterior', $old_price);
		$product->update_meta_data('_sait_precio_sait', $new_price);
	}

	private static function save_stock_sync_meta($product, $source, $old_stock, $new_stock, $status) {
		$product->update_meta_data('_sait_existencia_sync_at', current_time('mysql'));
		$product->update_meta_data('_sait_existencia_sync_source', $source);
		$product->update_meta_data('_sait_existencia_sync_status', $status);
		$product->update_meta_data('_sait_existencia_anterior', $old_stock);
		$product->update_meta_data('_sait_existencia_sait', $new_stock);
	}

	private static function build_sync_message($old_price, $new_price, $price_status, $stock_result) {
		$messages = array();

		if ($price_status === 'actualizado') {
			$messages[] = 'Precio actualizado de ' . $old_price . ' a ' . $new_price . '.';
		} elseif ($price_status === 'sin_cambio') {
			$messages[] = 'Precio sin cambio.';
		} else {
			$messages[] = 'Precio no actualizado.';
		}

		if (!empty($stock_result['mensaje'])) {
			$messages[] = $stock_result['mensaje'];
		}

		return implode(' ', $messages);
	}

	private static function schedule_batch($offset, $limit) {
		$args = array(absint($offset), absint($limit));

		if (function_exists('as_enqueue_async_action')) {
			as_enqueue_async_action(self::BATCH_ACTION, $args, 'sait-woocommerce');
			return;
		}

		wp_schedule_single_event(time() + 10, self::BATCH_ACTION, $args);
	}

	private static function get_status() {
		$defaults = array(
			'estado' => 'sin ejecutar',
			'offset' => 0,
			'limit' => self::BATCH_SIZE,
			'procesados' => 0,
			'actualizados' => 0,
			'existencias_actualizadas' => 0,
			'ignorados' => 0,
			'errores' => 0,
			'lotes' => 0,
			'max_lotes' => self::MAX_BATCHES,
			'iniciado_at' => '',
			'actualizado_at' => '',
			'ultimo_error' => '',
		);

		$status = get_option(self::STATUS_OPTION, array());
		return wp_parse_args(is_array($status) ? $status : array(), $defaults);
	}

	private static function save_status($status) {
		update_option(self::STATUS_OPTION, $status, false);
	}

	private static function set_notice($type, $message) {
		set_transient('sait_art_sync_notice_' . get_current_user_id(), array(
			'type' => $type,
			'message' => $message,
		), 60);
	}

	private static function get_notice() {
		$key = 'sait_art_sync_notice_' . get_current_user_id();
		$notice = get_transient($key);
		delete_transient($key);
		return is_array($notice) ? $notice : null;
	}

	private function redirect_settings() {
		wp_safe_redirect(admin_url('options-general.php?page=opciones_sait_page'));
		exit;
	}

	private function redirect_products() {
		wp_safe_redirect(admin_url('edit.php?post_type=product'));
		exit;
	}
}

new SAIT_WOOCOMMERCE_ArtSync();
