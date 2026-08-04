<?php

/**
 * Calcula precios SAIT sin depender de WordPress ni WooCommerce.
 */
class SAIT_WOOCOMMERCE_PriceCalculator
{
	/**
	 * Un precio cero o negativo se considera ausente y no debe reemplazar el actual.
	 *
	 * @param array<string,mixed> $article Fila de articulo SAIT.
	 * @param string $price_list Numero de lista configurado.
	 * @param float $exchange_rate Tipo de cambio configurado.
	 * @return array{valid:bool,price:float,source:string}
	 */
	public function calculate($article, $price_list = '', $exchange_rate = 0)
	{
		$price = 0.0;
		$source = 'none';

		if (isset($article['preciopub']) && is_numeric($article['preciopub']) && (float) $article['preciopub'] > 0) {
			$price = (float) $article['preciopub'];
			$source = 'preciopub';
		}

		$price_list = trim((string) $price_list);
		$list_field = 'precio' . $price_list;
		if ($price_list !== '' && isset($article[$list_field]) && is_numeric($article[$list_field])) {
			$base = (float) $article[$list_field];
			if ($base > 0) {
				$tax_1 = isset($article['impuesto1']) ? (float) $article['impuesto1'] : 0;
				$tax_2 = isset($article['impuesto2']) ? (float) $article['impuesto2'] : 0;
				$price = round($base * (1 + ($tax_1 + $tax_2) / 100), 2);
				$source = $list_field;
			}
		}

		if (
			isset($article['divisa'])
			&& $article['divisa'] === 'D'
			&& (float) $exchange_rate > 0
			&& isset($article['preciopub'])
			&& is_numeric($article['preciopub'])
		) {
			$converted = round((float) $article['preciopub'] * (float) $exchange_rate, 2);
			if ($converted > 0) {
				$price = $converted;
				$source = 'preciopub_tc';
			}
		}

		$price = round($price, 2);

		return array(
			'valid'  => $price > 0,
			'price'  => $price,
			'source' => $source,
		);
	}
}

/**
 * Calcula existencias SAIT sin depender de WordPress ni WooCommerce.
 */
class SAIT_WOOCOMMERCE_StockCalculator
{
	/**
	 * Una existencia cero es valida si la respuesta contiene el almacen solicitado.
	 *
	 * @param array<int,array<string,mixed>> $stock_rows Existencias por almacen.
	 * @param string $base_warehouse Almacen usado en modo unico.
	 * @param bool $multiple_warehouses Activa la suma de almacenes.
	 * @param array<int,string> $warehouses Almacenes incluidos en la suma.
	 * @return array{matched:bool,stock:float}
	 */
	public function calculate($stock_rows, $base_warehouse, $multiple_warehouses = false, $warehouses = array())
	{
		$base_warehouse = trim((string) $base_warehouse);
		$warehouses = array_values(array_filter(array_map('trim', (array) $warehouses), 'strlen'));
		$quantity = 0.0;
		$matched = false;

		foreach ((array) $stock_rows as $warehouse) {
			if (!is_array($warehouse)) {
				continue;
			}

			$warehouse_number = isset($warehouse['numalm']) ? trim((string) $warehouse['numalm']) : '';
			$stock = isset($warehouse['existencia']) && is_numeric($warehouse['existencia'])
				? (float) $warehouse['existencia']
				: 0.0;

			if ($multiple_warehouses) {
				if (in_array($warehouse_number, $warehouses, true)) {
					$quantity += $stock;
					$matched = true;
				}
				continue;
			}

			if ($warehouse_number === $base_warehouse) {
				$quantity = $stock;
				$matched = true;
				break;
			}
		}

		return array(
			'matched' => $matched,
			'stock'   => round($quantity, 2),
		);
	}
}
