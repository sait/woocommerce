<?php

if (!defined('ABSPATH')) {
	throw new RuntimeException('WordPress no esta cargado.');
}

function sait_calculator_assert_same($expected, $actual, $message)
{
	if ($expected !== $actual) {
		throw new RuntimeException(
			$message . ' Esperado: ' . var_export($expected, true)
			. '; recibido: ' . var_export($actual, true)
		);
	}
}

$price_calculator = new SAIT_WOOCOMMERCE_PriceCalculator();

$public_price = $price_calculator->calculate(array('preciopub' => 116), '', 18.5);
sait_calculator_assert_same(true, $public_price['valid'], 'Precio publico valido.');
sait_calculator_assert_same(116.0, $public_price['price'], 'Precio publico.');
sait_calculator_assert_same('preciopub', $public_price['source'], 'Fuente precio publico.');

$list_price = $price_calculator->calculate(
	array('preciopub' => 90, 'precio1' => 100, 'impuesto1' => 16, 'impuesto2' => 0),
	'1',
	18.5
);
sait_calculator_assert_same(116.0, $list_price['price'], 'Precio de lista con impuestos.');
sait_calculator_assert_same('precio1', $list_price['source'], 'Fuente lista configurada.');

$dollar_price = $price_calculator->calculate(
	array('preciopub' => 11.6, 'precio1' => 10, 'impuesto1' => 16, 'impuesto2' => 0, 'divisa' => 'D'),
	'1',
	18.5
);
sait_calculator_assert_same(214.6, $dollar_price['price'], 'Conversion de precio publico en dolares.');
sait_calculator_assert_same('preciopub_tc', $dollar_price['source'], 'Fuente convertida por tipo de cambio.');

$zero_price = $price_calculator->calculate(array('preciopub' => 0, 'precio1' => 0), '1', 18.5);
sait_calculator_assert_same(false, $zero_price['valid'], 'Precio cero no reemplaza el actual.');
sait_calculator_assert_same(0.0, $zero_price['price'], 'Precio cero normalizado.');

$stock_calculator = new SAIT_WOOCOMMERCE_StockCalculator();
$rows = array(
	array('numalm' => '1', 'existencia' => 7.5),
	array('numalm' => '2', 'existencia' => 2.25),
	array('numalm' => '3', 'existencia' => 100),
);

$single_stock = $stock_calculator->calculate($rows, '1');
sait_calculator_assert_same(true, $single_stock['matched'], 'Almacen unico encontrado.');
sait_calculator_assert_same(7.5, $single_stock['stock'], 'Existencia de almacen unico.');

$multiple_stock = $stock_calculator->calculate($rows, '1', true, array('1', '2'));
sait_calculator_assert_same(true, $multiple_stock['matched'], 'Almacenes multiples encontrados.');
sait_calculator_assert_same(9.75, $multiple_stock['stock'], 'Suma de almacenes.');

$zero_stock = $stock_calculator->calculate(array(array('numalm' => '1', 'existencia' => 0)), '1');
sait_calculator_assert_same(true, $zero_stock['matched'], 'Existencia cero conserva coincidencia.');
sait_calculator_assert_same(0.0, $zero_stock['stock'], 'Existencia cero valida.');

$missing_stock = $stock_calculator->calculate($rows, '9');
sait_calculator_assert_same(false, $missing_stock['matched'], 'Almacen ausente no se sincroniza.');

echo "Calculos de productos SAIT validados correctamente.\n";
