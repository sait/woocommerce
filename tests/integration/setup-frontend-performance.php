<?php
/**
 * Prepara datos deterministas para medir el frontend desde Docker.
 */

if (!defined('ABSPATH')) {
	exit(1);
}

if (!class_exists('WooCommerce') || !class_exists('WC_Product_Simple')) {
	throw new RuntimeException('WooCommerce debe estar activo para preparar la medicion.');
}

if (class_exists('WC_Install')) {
	WC_Install::create_pages();
}

$product_id = wc_get_product_id_by_sku('FIX-ART-001');
$product = $product_id ? wc_get_product($product_id) : new WC_Product_Simple();

if (!$product) {
	throw new RuntimeException('No se pudo cargar el producto fixture de rendimiento.');
}

$product->set_name('Producto Fixture Rendimiento');
$product->set_slug('producto-fixture-rendimiento');
$product->set_sku('FIX-ART-001');
$product->set_status('publish');
$product->set_catalog_visibility('visible');
$product->set_regular_price('116');
$product->set_price('116');
$product->set_manage_stock(true);
$product->set_stock_quantity(20);
$product->set_stock_status('instock');
$product_id = $product->save();

$term = term_exists('sait-performance', 'product_cat');
if (!$term) {
	$term = wp_insert_term(
		'Catálogo Fixture Rendimiento',
		'product_cat',
		array('slug' => 'sait-performance')
	);
}
if (is_wp_error($term)) {
	throw new RuntimeException($term->get_error_message());
}
$term_id = is_array($term) ? (int) $term['term_id'] : (int) $term;
wp_set_object_terms($product_id, array($term_id), 'product_cat', false);

$options = get_option('opciones_sait', array());
$options = is_array($options) ? $options : array();
$options = array_merge(
	$options,
	array(
		'SAITNube_URL'                    => 'https://sait-api.invalid',
		'SAITNube_APIKey'                 => 'fixture-api-key',
		'SAITNube_AccessToken'            => 'fixture-access-token',
		'SAITNube_NumAlm'                 => '1',
		'SAITNube_ExistAlm'               => '1,2',
		'SAITNube_Promo_enabled'          => '1',
		'SAITNube_PromoGlobal_enabled'    => '1',
		'SAITNube_Sucursal_enabled'       => '1',
		'SAITNube_ExistAlm_enabled'       => '1',
		'SAITNube_MinimoCarrito_Enabled' => '1',
		'SAITNube_MinimoCarrito'         => '50',
	)
);
update_option('opciones_sait', $options);

$catalog_url = get_term_link($term_id, 'product_cat');
if (is_wp_error($catalog_url)) {
	throw new RuntimeException($catalog_url->get_error_message());
}
$cart_url = wc_get_cart_url();
$checkout_url = wc_get_checkout_url();
$urls = array(
	'catalogo'      => $catalog_url,
	'producto'      => get_permalink($product_id),
	'carrito'       => $cart_url,
	'checkout'      => $checkout_url,
	'agregar_carrito' => add_query_arg('add-to-cart', $product_id, home_url('/')),
);

update_option('sait_test_performance_urls', $urls, false);
echo wp_json_encode($urls);
