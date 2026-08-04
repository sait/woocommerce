<?php

if (!defined('ABSPATH')) {
	exit(1);
}

function sait_settings_assert_same($expected, $actual, $message)
{
	if ($expected !== $actual) {
		throw new RuntimeException(
			$message . ' Esperado: ' . var_export($expected, true) .
			' Actual: ' . var_export($actual, true)
		);
	}
}

function sait_settings_assert_true($condition, $message)
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

$settings = SAIT_WOOCOMMERCE()->settings();
sait_settings_assert_true(
	$settings instanceof SAIT_WOOCOMMERCE_Settings,
	'El plugin debe compartir una instancia del servicio Settings.'
);
sait_settings_assert_same('opciones_sait', SAIT_WOOCOMMERCE_Settings::OPTION_NAME, 'Nombre historico de la opcion.');

$original_options = get_option(SAIT_WOOCOMMERCE_Settings::OPTION_NAME, array());
update_option(SAIT_WOOCOMMERCE_Settings::OPTION_NAME, array());
sait_settings_assert_same(false, $settings->has_saved_options(), 'Una opcion vacia no cuenta como configurada.');
update_option(
	SAIT_WOOCOMMERCE_Settings::OPTION_NAME,
	array(
		'SAITNube_APIKey'           => 'fixture-key',
		'SAITNube_Promo_enabled'    => '1',
		'SAITNube_ExistAlm'         => '1, 2,1,, 3',
		'opcion_historica_cliente'  => 'se conserva al leer',
	)
);
sait_settings_assert_same(true, $settings->has_saved_options(), 'Debe detectar configuracion persistida.');

$all = $settings->all();
sait_settings_assert_same('fixture-key', $settings->get('SAITNube_APIKey'), 'Lectura tipada de API key.');
sait_settings_assert_same('0', $all['SAITNube_Sucursal_enabled'], 'Default de bandera ausente.');
sait_settings_assert_same(true, $settings->is_enabled('SAITNube_Promo_enabled'), 'Bandera activa.');
sait_settings_assert_same(false, $settings->is_enabled('SAITNube_Sucursal_enabled'), 'Bandera inactiva.');
sait_settings_assert_same(array('1', '2', '3'), $settings->warehouses(), 'Lista normalizada de almacenes.');
sait_settings_assert_same(
	'se conserva al leer',
	$all['opcion_historica_cliente'],
	'Las claves adicionales existentes no deben perderse durante la lectura.'
);

$settings->set('SAITNube_TipoCambio', '18.5000');
$stored = get_option(SAIT_WOOCOMMERCE_Settings::OPTION_NAME, array());
sait_settings_assert_same('18.5000', $stored['SAITNube_TipoCambio'], 'Escritura interna de una clave.');
sait_settings_assert_same(
	'se conserva al leer',
	$stored['opcion_historica_cliente'],
	'La escritura interna no debe descartar claves adicionales.'
);

$sanitized = $settings->sanitize(
	array(
		'SAITNube_APIKey'                    => " clave\nfixture ",
		'SAITNube_ExistAlm'                  => ' 1, 2,1, ,3 ',
		'SAITNube_Promo_enabled'             => '1',
		'SAITNube_PromoGlobal_enabled'       => 'si',
		'SAITNube_PedidoDirenvio_enabled'    => '0',
		'campo_desconocido'                  => 'no guardar',
	)
);
sait_settings_assert_same('clave fixture', $sanitized['SAITNube_APIKey'], 'Sanitizacion de texto.');
sait_settings_assert_same('1,2,3', $sanitized['SAITNube_ExistAlm'], 'Sanitizacion de almacenes.');
sait_settings_assert_same('1', $sanitized['SAITNube_Promo_enabled'], 'Booleano permitido.');
sait_settings_assert_same('0', $sanitized['SAITNube_PromoGlobal_enabled'], 'Booleano invalido debe desactivarse.');
sait_settings_assert_same('0', $sanitized['SAITNube_PedidoDirenvio_enabled'], 'Booleano desactivado.');
sait_settings_assert_same(false, isset($sanitized['campo_desconocido']), 'No aceptar claves desconocidas.');

require_once WP_PLUGIN_DIR . '/sait-woocommerce/includes/SAIT_WOOCOMMERCE-options.php';
$page = new SAITSettingsPage($settings);
$page->page_init();
$registered = get_registered_settings();
sait_settings_assert_true(isset($registered['opciones_sait']), 'Settings API debe registrar opciones_sait.');
sait_settings_assert_same('array', $registered['opciones_sait']['type'], 'Tipo registrado en Settings API.');
sait_settings_assert_true(
	is_callable($registered['opciones_sait']['sanitize_callback']),
	'Settings API debe registrar sanitizacion por campo.'
);

update_option(SAIT_WOOCOMMERCE_Settings::OPTION_NAME, $original_options);

echo "Configuracion SAIT validada correctamente.\n";
