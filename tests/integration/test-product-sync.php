<?php

if (!defined('ABSPATH')) {
	throw new RuntimeException('WordPress no esta cargado.');
}

function sait_product_sync_assert_same($expected, $actual, $message)
{
	if ($expected !== $actual) {
		throw new RuntimeException(
			$message . ' Esperado: ' . var_export($expected, true)
			. '; recibido: ' . var_export($actual, true)
		);
	}
}

global $wpdb;
foreach (array('FIX-ART-001', 'FIX-USD-001') as $sku) {
	$product_id = wc_get_product_id_by_sku($sku);
	if ($product_id) {
		wc_get_product($product_id)->delete(true);
	}
	$wpdb->delete(
		$wpdb->prefix . 'sait_claves',
		array('tabla' => 'arts', 'clave' => $sku),
		array('%s', '%s')
	);
}

$options = get_option('opciones_sait', array());
$options['SAITNube_URL'] = 'https://sait-api.invalid';
$options['SAITNube_APIKey'] = 'fixture-api-key';
$options['SAITNube_NumAlm'] = '1';
$options['SAITNube_ExistAlm_enabled'] = '0';
$options['SAITNube_ExistAlm'] = '';
$options['SAITNube_PrecioLista'] = '';
$options['SAITNube_TipoCambio'] = '18.5';
update_option('opciones_sait', $options);

$mapped_product = new WC_Product_Simple();
$mapped_product->set_name('Producto Sync Mapeado');
$mapped_product->set_sku('FIX-ART-001');
$mapped_product->set_regular_price(10);
$mapped_product->set_manage_stock(true);
$mapped_product->set_stock_quantity(1);
$mapped_product_id = $mapped_product->save();
SAIT_UTILS::SAIT_insertClaves('arts', 'FIX-ART-001', $mapped_product_id);

$mapped = SAIT_WOOCOMMERCE()->product_resolver()->resolve('FIX-ART-001');
sait_product_sync_assert_same('mapping', $mapped['source'], 'Resolucion por mapeo.');
sait_product_sync_assert_same($mapped_product_id, $mapped['product']->get_id(), 'Producto mapeado.');

$sku_product = new WC_Product_Simple();
$sku_product->set_name('Producto Sync SKU');
$sku_product->set_sku('FIX-USD-001');
$sku_product_id = $sku_product->save();
$by_sku = SAIT_WOOCOMMERCE()->product_resolver()->resolve('FIX-USD-001');
sait_product_sync_assert_same('sku', $by_sku['source'], 'Fallback por SKU.');
sait_product_sync_assert_same($sku_product_id, $by_sku['product']->get_id(), 'Producto resuelto por SKU.');
sait_product_sync_assert_same(null, SAIT_UTILS::SAIT_getClaves('arts', 'FIX-USD-001', null), 'Resolver no registra mapeos.');

$result = SAIT_WOOCOMMERCE()->product_sync_service()->sync_sku('FIX-ART-001', 'fixture_service');
sait_product_sync_assert_same('actualizado', $result['estado'], 'Estado de sincronizacion.');
$synced = wc_get_product($mapped_product_id);
sait_product_sync_assert_same(116.0, (float) $synced->get_regular_price(), 'Precio sincronizado.');
sait_product_sync_assert_same(7.0, (float) $synced->get_stock_quantity(), 'Existencia persistida por WooCommerce.');
sait_product_sync_assert_same(7.5, (float) $synced->get_meta('_sait_existencia_sait'), 'Existencia exacta recibida de SAIT.');
sait_product_sync_assert_same('fixture_service', $synced->get_meta('_sait_art_sync_source'), 'Auditoria de precio.');
sait_product_sync_assert_same('fixture_service', $synced->get_meta('_sait_existencia_sync_source'), 'Auditoria de existencia.');
sait_product_sync_assert_same('actualizado', $synced->get_meta('_sait_art_sync_status'), 'Estado auditado de precio.');
sait_product_sync_assert_same('actualizado', $synced->get_meta('_sait_existencia_sync_status'), 'Estado auditado de existencia.');

$synced->delete(true);
$sku_product->delete(true);
$wpdb->delete($wpdb->prefix . 'sait_claves', array('tabla' => 'arts', 'clave' => 'FIX-ART-001'), array('%s', '%s'));

echo "Servicio de sincronizacion de productos SAIT validado correctamente.\n";
