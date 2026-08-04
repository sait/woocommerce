<?php

/**
 * Servicio compartido para sincronizar precio y existencia de productos SAIT.
 */
class SAIT_WOOCOMMERCE_ProductSyncService
{
	private $resolver;
	private $sait_client;
	private $settings;
	private $price_calculator;
	private $stock_calculator;

	public function __construct($resolver, $sait_client, $settings, $price_calculator, $stock_calculator)
	{
		$this->resolver = $resolver;
		$this->sait_client = $sait_client;
		$this->settings = $settings;
		$this->price_calculator = $price_calculator;
		$this->stock_calculator = $stock_calculator;
	}

	/**
	 * Consulta un articulo y sincroniza el producto local correspondiente.
	 *
	 * @return array<string,mixed>
	 */
	public function sync_sku($sku, $source = 'manual')
	{
		$sku = trim((string) $sku);
		if ($sku === '') {
			return array('estado' => 'error', 'mensaje' => 'SKU vacio.');
		}

		$request = $this->sait_client->get('/api/v3/articulos/' . rawurlencode($sku));
		if (empty($request['ok'])) {
			$message = isset($request['status_code']) && (int) $request['status_code'] === 404
				? 'SAITNube no encontro el articulo.'
				: 'No se pudo consultar el articulo en SAITNube. ' . $request['mensaje'];

			return array('estado' => 'error', 'mensaje' => $message);
		}

		$row = isset($request['result']) && is_array($request['result']) ? $request['result'] : null;
		if (!$row) {
			return array('estado' => 'error', 'mensaje' => 'SAITNube respondio, pero no regreso datos del articulo.');
		}

		return $this->sync_from_row($sku, $row, $source);
	}

	/**
	 * Aplica una fila SAIT ya obtenida a un producto local.
	 *
	 * @param string $numart Numero de articulo SAIT.
	 * @param array<string,mixed> $row Fila observada de /articulos.
	 * @param string $source Origen de auditoria.
	 * @return array<string,mixed>
	 */
	public function sync_from_row($numart, $row, $source = 'manual')
	{
		$resolved = $this->resolver->resolve($numart);
		$product = $resolved['product'];
		if (!$product) {
			return array('estado' => 'ignorado', 'mensaje' => 'Producto no existe en WooCommerce.');
		}

		$options = $this->settings->all();
		$price_result = $this->price_calculator->calculate(
			$row,
			isset($options['SAITNube_PrecioLista']) ? $options['SAITNube_PrecioLista'] : '',
			isset($options['SAITNube_TipoCambio']) ? (float) $options['SAITNube_TipoCambio'] : 0
		);
		$old_price = (float) $product->get_regular_price();
		$price_changed = false;
		$price_status = 'sin_precio_valido';

		if ($price_result['valid']) {
			$new_price = $price_result['price'];
			$price_status = round($old_price, 2) === round($new_price, 2) ? 'sin_cambio' : 'actualizado';
			if ($price_status === 'actualizado') {
				$product->set_regular_price($new_price);
				$product->set_price($new_price);
				$price_changed = true;
			}
			$this->save_price_meta($product, $source, $old_price, $new_price, $price_status);
		}

		$stock_result = $this->sync_stock($product, $numart, $source, $options);
		$product->save();
		$stock_changed = !empty($stock_result['actualizado']);

		if ($price_changed || $stock_changed) {
			return array(
				'estado' => 'actualizado',
				'mensaje' => $this->build_message($old_price, $price_result['price'], $price_status, $stock_result),
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
			'mensaje' => $this->build_message($old_price, $price_result['price'], $price_status, $stock_result),
			'existencia_actualizada' => false,
		);
	}

	/** @return array<string,mixed> */
	private function sync_stock($product, $numart, $source, $options)
	{
		$old_stock = $product->get_stock_quantity();
		if (!$this->settings->has_saved_options()) {
			$this->save_stock_meta($product, $source, $old_stock, null, 'sin_datos');
			return array('sincronizado' => false, 'actualizado' => false, 'mensaje' => 'No hay opciones SAITNube configuradas.');
		}

		$request = $this->sait_client->get('/api/v3/existencias/' . rawurlencode(trim($numart)));
		$rows = !empty($request['ok']) && is_array($request['result']) ? $request['result'] : null;
		if ($rows === null) {
			$this->save_stock_meta($product, $source, $old_stock, null, 'sin_datos');
			return array('sincronizado' => false, 'actualizado' => false, 'mensaje' => 'Existencia no disponible.');
		}

		$multiple = isset($options['SAITNube_ExistAlm_enabled']) && $options['SAITNube_ExistAlm_enabled'] === '1';
		$warehouses = $multiple && !empty($options['SAITNube_ExistAlm'])
			? array_filter(array_map('trim', explode(',', $options['SAITNube_ExistAlm'])))
			: array();
		$stock = $this->stock_calculator->calculate(
			$rows,
			isset($options['SAITNube_NumAlm']) ? $options['SAITNube_NumAlm'] : '',
			$multiple,
			$warehouses
		);

		if (!$stock['matched']) {
			$this->save_stock_meta($product, $source, $old_stock, null, 'sin_datos');
			return array('sincronizado' => false, 'actualizado' => false, 'mensaje' => 'Existencia no disponible para los almacenes configurados.');
		}

		$new_stock = $stock['stock'];
		$product->set_manage_stock(true);
		$status = (float) $old_stock === (float) $new_stock ? 'sin_cambio' : 'actualizado';
		$this->save_stock_meta($product, $source, $old_stock, $new_stock, $status);
		if ($status === 'actualizado') {
			$product->set_stock_quantity($new_stock);
		}

		return array(
			'sincronizado' => true,
			'actualizado' => $status === 'actualizado',
			'mensaje' => $status === 'actualizado'
				? 'Existencia actualizada de ' . (float) $old_stock . ' a ' . $new_stock . '.'
				: 'Existencia sin cambio.',
		);
	}

	private function save_price_meta($product, $source, $old_price, $new_price, $status)
	{
		$product->update_meta_data('_sait_art_sync_at', current_time('mysql'));
		$product->update_meta_data('_sait_art_sync_source', $source);
		$product->update_meta_data('_sait_art_sync_status', $status);
		$product->update_meta_data('_sait_precio_anterior', $old_price);
		$product->update_meta_data('_sait_precio_sait', $new_price);
	}

	private function save_stock_meta($product, $source, $old_stock, $new_stock, $status)
	{
		$product->update_meta_data('_sait_existencia_sync_at', current_time('mysql'));
		$product->update_meta_data('_sait_existencia_sync_source', $source);
		$product->update_meta_data('_sait_existencia_sync_status', $status);
		$product->update_meta_data('_sait_existencia_anterior', $old_stock);
		$product->update_meta_data('_sait_existencia_sait', $new_stock);
	}

	private function build_message($old_price, $new_price, $price_status, $stock_result)
	{
		if ($price_status === 'actualizado') {
			$price_message = 'Precio actualizado de ' . $old_price . ' a ' . $new_price . '.';
		} elseif ($price_status === 'sin_cambio') {
			$price_message = 'Precio sin cambio.';
		} else {
			$price_message = 'Precio no actualizado.';
		}

		return trim($price_message . ' ' . $stock_result['mensaje']);
	}
}
