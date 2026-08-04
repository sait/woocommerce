<?php

/**
 * Localiza productos WooCommerce relacionados con un numart SAIT.
 */
class SAIT_WOOCOMMERCE_ProductResolver
{
	/** @var SAIT_WOOCOMMERCE_MappingRepository */
	private $mapping_repository;

	public function __construct($mapping_repository)
	{
		$this->mapping_repository = $mapping_repository;
	}

	/**
	 * Busca primero un mapeo valido y despues el SKU.
	 *
	 * @param string $numart Numero de articulo SAIT.
	 * @return array{product:WC_Product|false,mapping:object|null,source:string}
	 */
	public function resolve($numart)
	{
		$numart = trim((string) $numart);
		$mapping = $numart === '' ? null : $this->mapping_repository->find_product($numart);
		if ($mapping && !empty($mapping->wcid)) {
			$product = wc_get_product($mapping->wcid);
			if ($product) {
				return array('product' => $product, 'mapping' => $mapping, 'source' => 'mapping');
			}
		}

		$product_id = $numart === '' ? 0 : wc_get_product_id_by_sku($numart);
		$product = $product_id ? wc_get_product($product_id) : false;

		return array(
			'product' => $product,
			'mapping' => $mapping,
			'source'  => $product ? 'sku' : 'none',
		);
	}
}
