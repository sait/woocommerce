<?php

if (!defined('ABSPATH')) {
	exit(1);
}

function sait_mapping_assert_same($expected, $actual, $message)
{
	if ($expected !== $actual) {
		throw new RuntimeException(
			$message . ' Esperado: ' . var_export($expected, true) .
			' Actual: ' . var_export($actual, true)
		);
	}
}

function sait_mapping_assert_true($condition, $message)
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

global $wpdb;
$table_name = $wpdb->prefix . 'sait_claves';
$test_entities = array('test_arts', 'test_clientes', 'test_catego');
foreach ($test_entities as $entity) {
	$wpdb->delete($table_name, array('tabla' => $entity), array('%s'));
}
$wpdb->delete($table_name, array('tabla' => 'arts', 'clave' => 'FIX-MAP-ART'), array('%s', '%s'));
$wpdb->delete($table_name, array('tabla' => 'clientes', 'clave' => 'FIX-MAP-CLI'), array('%s', '%s'));

$repository = SAIT_WOOCOMMERCE()->mapping_repository();
sait_mapping_assert_true(
	$repository instanceof SAIT_WOOCOMMERCE_MappingRepository,
	'El plugin debe compartir el repositorio de mapeos.'
);

$product_id = $repository->add('test_arts', 'ART-001', 101);
sait_mapping_assert_true(is_int($product_id) && $product_id > 0, 'Debe insertar un producto.');
$product = $repository->find_by_sait_key('test_arts', 'ART-001');
sait_mapping_assert_same(101, (int) $product->wcid, 'Busqueda por clave SAIT.');
$product_by_wc = $repository->find_by_woocommerce_id('test_arts', 101);
sait_mapping_assert_same('ART-001', $product_by_wc->clave, 'Busqueda por ID WooCommerce.');

$duplicate_id = $repository->add('test_arts', 'ART-001', 999);
sait_mapping_assert_same($product_id, $duplicate_id, 'Una tabla y clave repetidas deben reutilizar la fila.');
$duplicate_count = (int) $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COUNT(*) FROM {$table_name} WHERE tabla = %s AND clave = %s",
		'test_arts',
		'ART-001'
	)
);
sait_mapping_assert_same(1, $duplicate_count, 'No debe insertar duplicados de tabla y clave.');

$repository->add('arts', 'FIX-MAP-ART', 910001);
sait_mapping_assert_same(910001, (int) $repository->find_product('FIX-MAP-ART')->wcid, 'Metodo de producto.');

$repository->add('clientes', 'FIX-MAP-CLI', 920001);
sait_mapping_assert_same(920001, (int) $repository->find_customer('FIX-MAP-CLI')->wcid, 'Metodo de cliente.');
sait_mapping_assert_same('FIX-MAP-CLI', $repository->find_customer_by_user_id(920001)->clave, 'Cliente por usuario.');

$repository->add('test_clientes', 'CLI-001', 201);
$repository->add('test_clientes', 'CLI-002', 201);
$shared_wc_count = (int) $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COUNT(*) FROM {$table_name} WHERE tabla = %s AND wcid = %d",
		'test_clientes',
		201
	)
);
sait_mapping_assert_same(2, $shared_wc_count, 'Claves diferentes pueden compartir wcid sin indice unico.');

$repository->add('test_catego', 'CAT-001', 301);
sait_mapping_assert_same(301, (int) $repository->find_category('test_catego', 'CAT-001')->wcid, 'Metodo de categoria.');

$legacy_by_key = SAIT_UTILS::SAIT_getClaves('test_arts', 'ART-001', null);
sait_mapping_assert_same(101, (int) $legacy_by_key->wcid, 'Adaptador legacy por clave.');
$legacy_by_wc = SAIT_UTILS::SAIT_getClaves('test_clientes', null, 201);
sait_mapping_assert_same('CLI-001', $legacy_by_wc->clave, 'Adaptador legacy por wcid.');
$legacy_fallback = SAIT_UTILS::SAIT_getClaves('test_clientes', 'NO-EXISTE', 201);
sait_mapping_assert_same('CLI-001', $legacy_fallback->clave, 'Fallback legacy cuando la clave no existe.');

$injection = $repository->find_by_sait_key("test_arts' OR 1=1 --", "ART-001' OR 1=1 --");
sait_mapping_assert_same(null, $injection, 'Las busquedas deben usar parametros preparados.');

sait_mapping_assert_same(true, $repository->delete($product_id), 'Eliminar por ID.');
sait_mapping_assert_same(null, $repository->find_by_sait_key('test_arts', 'ART-001'), 'Mapeo eliminado.');

$indexes = $wpdb->get_results("SHOW INDEX FROM {$table_name}");
foreach ($indexes as $index) {
	if ((int) $index->Non_unique === 0 && $index->Key_name !== 'PRIMARY') {
		throw new RuntimeException('No se debe agregar una restriccion unica antes de auditar datos.');
	}
}

foreach ($test_entities as $entity) {
	$wpdb->delete($table_name, array('tabla' => $entity), array('%s'));
}
$wpdb->delete($table_name, array('tabla' => 'arts', 'clave' => 'FIX-MAP-ART'), array('%s', '%s'));
$wpdb->delete($table_name, array('tabla' => 'clientes', 'clave' => 'FIX-MAP-CLI'), array('%s', '%s'));

echo "Repositorio de mapeos SAIT validado correctamente.\n";
