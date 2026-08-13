<?php

if (!defined('ABSPATH')) {
	throw new RuntimeException('WordPress no esta cargado.');
}
if (!class_exists('SAIT_WOOCOMMERCE_ArtSync', false)) {
	throw new RuntimeException('SAIT_WOOCOMMERCE_ArtSync no se cargo en el proceso aislado.');
}
if (has_action(SAIT_WOOCOMMERCE_ArtSync::BATCH_ACTION) === false) {
	throw new RuntimeException('El hook del lote de articulos no esta registrado.');
}

delete_option(SAIT_WOOCOMMERCE_ArtSync::STATUS_OPTION);
delete_option('sait_test_request_counts');
do_action(SAIT_WOOCOMMERCE_ArtSync::BATCH_ACTION, 0, SAIT_WOOCOMMERCE_ArtSync::BATCH_SIZE);

echo "Worker aislado de articulos ejecutado.\n";
