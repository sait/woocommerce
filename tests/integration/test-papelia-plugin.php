<?php

if (!defined('ABSPATH')) {
	exit(1);
}

function sait_papelia_assert_same($expected, $actual, $message)
{
	if ($expected !== $actual) {
		throw new RuntimeException(
			$message . ' Esperado: ' . var_export($expected, true) . ' Actual: ' . var_export($actual, true)
		);
	}
}

function sait_papelia_assert_true($condition, $message)
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

class SAIT_Papelia_Test_Client implements SAIT_WOOCOMMERCE_SaitClientInterface
{
	public $requests = array();

	public function get($uri, $retry = true)
	{
		$this->requests[] = $uri;
		if ($uri === '/api/v3/almacenes') {
			$result = array(
				array('numalm' => '1', 'nomalm' => 'Matriz', 'calle' => 'Uno', 'colonia' => 'Centro'),
				array('numalm' => '2', 'nomalm' => 'Sucursal', 'calle' => 'Dos', 'colonia' => 'Norte'),
				array('numalm' => '4', 'nomalm' => 'Excluida', 'calle' => '', 'colonia' => ''),
			);
		} elseif ($uri === '/api/v3/existencias/FIX-PAPELIA-001') {
			$result = array(
				array('numalm' => '1', 'existencia' => 2),
				array('numalm' => '2', 'existencia' => 5),
				array('numalm' => '4', 'existencia' => 100),
			);
		} else {
			$result = array();
		}

		return array(
			'ok'          => true,
			'status_code' => 200,
			'data'        => array('result' => $result),
			'result'      => $result,
			'error_code'  => '',
			'mensaje'     => 'HTTP 200.',
		);
	}

	public function get_legacy($uri, $retry = true)
	{
		$response = $this->get($uri, $retry);
		return $response['data'];
	}

	public function post($uri, $body, $wait = false)
	{
		return array();
	}
}

sait_papelia_assert_true(class_exists('SAIT_Papelia_Plugin'), 'El complemento Papelía debe cargar su bootstrap.');
sait_papelia_assert_true(class_exists('SAIT_Papelia_Stock'), 'El módulo de stock Papelía debe estar disponible.');
sait_papelia_assert_true(
	has_filter('sait_woocommerce_order_payload') !== false,
	'El complemento debe registrar la personalización del payload de pedidos.'
);
sait_papelia_assert_same(
	false,
	has_filter('woocommerce_cart_has_stock', '__return_true'),
	'Papelía no debe desactivar globalmente la validación de stock de WooCommerce.'
);

$original_options = SAIT_WOOCOMMERCE()->settings()->all();
$options = $original_options;
$options['SAITNube_ExistAlm_enabled'] = '1';
$options['SAITNube_ExistAlm'] = '1,2,4';
update_option(SAIT_WOOCOMMERCE_Settings::OPTION_NAME, $options);

$client = new SAIT_Papelia_Test_Client();
SAIT_WOOCOMMERCE()->set_sait_client($client);
$stock = new SAIT_Papelia_Stock();
$product = new WC_Product_Simple();
$product->set_sku('FIX-PAPELIA-001');
$product->set_name('Producto Papelía Fixture');

delete_transient('sait_papelia_stock_' . md5('FIX-PAPELIA-001|total'));
delete_transient('sait_papelia_stock_' . md5('FIX-PAPELIA-001|1'));
sait_papelia_assert_same(107.0, $stock->get_stock($product), 'El stock total debe sumar almacenes configurados.');
sait_papelia_assert_same(2.0, $stock->get_stock($product, '1'), 'El stock por sucursal debe conservar su contexto.');
sait_papelia_assert_same(2, count($client->requests), 'Cada contexto de stock debe consultar SAIT una vez.');
$stock->get_stock($product, '1');
sait_papelia_assert_same(2, count($client->requests), 'El stock por sucursal debe reutilizar su transient.');

$order = wc_create_order();
sait_papelia_assert_true(!is_wp_error($order), 'Debe crear una orden para caracterizar el payload Papelía.');
$order->set_billing_first_name('Cliente');
$order->set_billing_last_name('Papelía');
$order->set_billing_phone('5550000000');
$order->set_billing_email('papelia.fixture@example.test');
$order->set_payment_method_title('Transferencia');
$order->set_customer_note('Llamar antes de entregar');
$shipping = new WC_Order_Item_Shipping();
$shipping->set_method_title('Recoger en sucursal');
$shipping->set_method_id('local_pickup');
$shipping->set_instance_id(4);
$order->add_item($shipping);
$order->update_meta_data('_sait_sucursal', '1');
$order->update_meta_data('_sait_sucursal_nombre', 'Matriz');
$order->update_meta_data('_sait_pedido_sin_existencias', '1');
$order->save();

$payload = apply_filters('sait_woocommerce_order_payload', (object) array(), $order);
sait_papelia_assert_true(strpos($payload->otrosdatos, 'clinum=     0') !== false, 'El payload debe conservar clinum público de Papelía.');
sait_papelia_assert_true(strpos($payload->obs, 'Sucursal: 1  Matriz') !== false, 'OBS debe incluir la sucursal elegida.');
sait_papelia_assert_true(strpos($payload->obs, 'Existencias faltantes') !== false, 'OBS debe avisar faltantes aceptados.');

$order->delete(true);
update_option(SAIT_WOOCOMMERCE_Settings::OPTION_NAME, $original_options);

echo "Plugin complementario de Papelía validado correctamente.\n";
