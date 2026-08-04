<?php

if (!defined('ABSPATH')) {
	exit(1);
}

function sait_logger_assert_same($expected, $actual, $message)
{
	if ($expected !== $actual) {
		throw new RuntimeException(
			$message . ' Esperado: ' . var_export($expected, true) .
			' Actual: ' . var_export($actual, true)
		);
	}
}

function sait_logger_assert_true($condition, $message)
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

class SAIT_Test_Log_Collector
{
	public $records = array();

	public function log($level, $message, $context = array())
	{
		$this->records[] = array(
			'level' => $level,
			'message' => $message,
			'context' => $context,
		);
	}
}

sait_logger_assert_true(
	SAIT_WOOCOMMERCE()->logger() instanceof SAIT_WOOCOMMERCE_Logger,
	'El plugin debe compartir el logger saneado.'
);

$collector = new SAIT_Test_Log_Collector();
$logger = new SAIT_WOOCOMMERCE_Logger($collector);
$logger->warning(
	'Contexto operativo de prueba.',
	array(
		'event'         => 'MODART',
		'order_id'      => '42',
		'sku'           => ' FIX-ART-001 ',
		'attempt'       => '2',
		'status_code'   => '503',
		'operation'     => 'GET',
		'error_code'    => 'transport_error',
		'mode'          => 'manual',
		'document_type' => 'P',
		'item_count'    => '3',
		'api_key'       => 'fixture-api-key-no-registrar',
		'token'         => 'fixture-token-no-registrar',
		'email'         => 'persona@example.test',
		'name'          => 'Persona Privada',
		'payload'       => array('direccion' => 'Dato privado'),
	)
);

sait_logger_assert_same(1, count($collector->records), 'Debe escribir un registro.');
$record = $collector->records[0];
sait_logger_assert_same('warning', $record['level'], 'Nivel del registro.');
sait_logger_assert_same('Contexto operativo de prueba.', $record['message'], 'Mensaje saneado.');
sait_logger_assert_same('sait-woocommerce', $record['context']['source'], 'Source estable de WooCommerce.');
sait_logger_assert_same('MODART', $record['context']['event'], 'Contexto de evento.');
sait_logger_assert_same(42, $record['context']['order_id'], 'Contexto de orden.');
sait_logger_assert_same('FIX-ART-001', $record['context']['sku'], 'Contexto de SKU.');
sait_logger_assert_same(2, $record['context']['attempt'], 'Contexto de intento.');
sait_logger_assert_same(503, $record['context']['status_code'], 'Contexto de status HTTP.');
sait_logger_assert_same(3, $record['context']['item_count'], 'Cantidad de partidas.');

foreach (array('api_key', 'token', 'email', 'name', 'payload') as $forbidden_key) {
	sait_logger_assert_same(
		false,
		array_key_exists($forbidden_key, $record['context']),
		'El logger no debe aceptar contexto sensible: ' . $forbidden_key
	);
}

$serialized = wp_json_encode($record);
foreach (
	array(
		'fixture-api-key-no-registrar',
		'fixture-token-no-registrar',
		'persona@example.test',
		'Persona Privada',
		'Dato privado',
	) as $private_value
) {
	sait_logger_assert_same(
		false,
		strpos($serialized, $private_value) !== false,
		'El registro contiene un valor privado.'
	);
}

echo "Logger saneado de SAIT validado correctamente.\n";
