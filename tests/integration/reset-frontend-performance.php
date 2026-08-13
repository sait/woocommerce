<?php
/**
 * Reinicia exclusivamente métricas y cachés SAIT entre muestras.
 */

if (!defined('ABSPATH')) {
	exit(1);
}

delete_option('sait_test_request_counts');
delete_option('sait_test_request_metrics');

global $wpdb;
$transient_patterns = array(
	$wpdb->esc_like('_transient_sait_') . '%',
	$wpdb->esc_like('_transient_timeout_sait_') . '%',
);

foreach ($transient_patterns as $pattern) {
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$pattern
		)
	);
}

wp_cache_flush();
