<?php

if (!defined('ABSPATH')) {
	throw new RuntimeException('WordPress no esta cargado.');
}

$status = get_option(SAIT_WOOCOMMERCE_ArtSync::STATUS_OPTION, array());
$request_counts = get_option('sait_test_request_counts', array());
$get_count = isset($request_counts['GET /api/v3/articulos'])
	? absint($request_counts['GET /api/v3/articulos'])
	: 0;

if (!is_array($status) || !isset($status['estado']) || $status['estado'] !== 'finalizado') {
	throw new RuntimeException('El worker aislado de articulos no termino correctamente.');
}
if (!isset($status['lotes']) || absint($status['lotes']) !== 1 || $get_count !== 1) {
	throw new RuntimeException(
		'Worker de articulos inesperado: lotes='
		. (isset($status['lotes']) ? absint($status['lotes']) : 0)
		. ', consultas=' . $get_count
	);
}

delete_option(SAIT_WOOCOMMERCE_ArtSync::STATUS_OPTION);
delete_option('sait_test_request_counts');

echo "Worker aislado de articulos validado correctamente.\n";
