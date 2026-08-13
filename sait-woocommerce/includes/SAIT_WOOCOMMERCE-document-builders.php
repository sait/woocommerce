<?php

/**
 * Construccion comun y sin transporte HTTP para documentos SAIT.
 */
abstract class SAIT_WOOCOMMERCE_DocumentBuilder
{
	/** @var array<string,mixed> */
	protected $settings;

	/** @var int */
	protected $timestamp;

	/**
	 * @param array<string,mixed> $settings Configuracion ya resuelta.
	 * @param int|null $timestamp Momento estable para entrega.
	 */
	public function __construct($settings, $timestamp = null)
	{
		$this->settings = $settings;
		$this->timestamp = $timestamp === null ? time() : (int) $timestamp;
	}

	/**
	 * @param WC_Order $order Orden WooCommerce.
	 * @param string $formapago Forma de pago SAIT.
	 * @param array<int|string,string> $units Unidades por ID de partida.
	 * @param array{numcli:string,numcliev:string,clievent:object|null} $customer Cliente resuelto.
	 * @return object
	 */
	abstract public function build($order, $formapago, $units, $customer);

	/**
	 * @return object
	 */
	protected function common_document($order, $formapago, $units, $customer)
	{
		$document = new stdClass();
		$document->numdoc = SAIT_SERIE . strval($order->get_id());
		$document->numcli = $customer['numcli'];
		$document->numcliev = $customer['numcliev'];
		$document->numalm = str_pad(SAIT_NUBE_NUMALM, 2, ' ', STR_PAD_LEFT);
		if (isset($this->settings['SAITNube_NumAlm']) && $this->settings['SAITNube_NumAlm'] !== null) {
			$document->numalm = str_pad($this->settings['SAITNube_NumAlm'], 2, ' ', STR_PAD_LEFT);
		}
		$document->formapago = $formapago;
		$document->divisa = 'P';
		$document->tc = 1;
		$document->items = $this->build_items($order, $units);
		$document->fentrega = date('Ymd', $this->timestamp);
		$document->hentrega = date('H:i', $this->timestamp);

		if ($this->is_enabled('SAITNube_PedidoObs_enabled')) {
			$document->obs = trim($order->get_customer_note());
		}
		if ($this->is_enabled('SAITNube_PedidoDirenvio_enabled')) {
			$document->direnvio = self::shipping_address($order);
		}
		if ($customer['clievent'] !== null) {
			$document->clievent = $customer['clievent'];
		}

		return $document;
	}

	/**
	 * @return array<int,object>
	 */
	private function build_items($order, $units)
	{
		$items = array();
		foreach ($order->get_items() as $item_id => $item) {
			$product = $item->get_product();
			$article = new stdClass();
			$article->cant = $item->get_quantity();
			$article->numart = $product->get_sku();
			$article->unidad = isset($units[$item_id]) ? $units[$item_id] : '';
			$article->preciopub = (float) $product->get_regular_price();
			$article->precio = (float) $product->get_regular_price();
			$article->pjedesc1 = self::discount_percentage(
				$article->cant,
				(float) $item->get_total(),
				$article->preciopub
			);
			$items[] = $article;
		}

		return $items;
	}

	/**
	 * @param string $key Opcion booleana historica.
	 * @return bool
	 */
	private function is_enabled($key)
	{
		return isset($this->settings[$key]) && $this->settings[$key] === '1';
	}

	/**
	 * @return float
	 */
	public static function discount_percentage($quantity, $total, $price)
	{
		return round((($price - ($total / $quantity)) / $price) * 100, 2);
	}

	/**
	 * Construye DIR ENVIO con shipping y fallback a billing.
	 *
	 * @param WC_Order $order Orden WooCommerce.
	 * @return string
	 */
	public static function shipping_address($order)
	{
		$address_1 = trim($order->get_shipping_address_1());
		$address_2 = trim($order->get_shipping_address_2());
		$city = trim($order->get_shipping_city());
		$state = trim($order->get_shipping_state());
		$postcode = trim($order->get_shipping_postcode());
		$phone = trim($order->get_billing_phone());

		if (empty($address_1)) $address_1 = trim($order->get_billing_address_1());
		if (empty($address_2)) $address_2 = trim($order->get_billing_address_2());
		if (empty($city)) $city = trim($order->get_billing_city());
		if (empty($state)) $state = trim($order->get_billing_state());
		if (empty($postcode)) $postcode = trim($order->get_billing_postcode());

		if (empty($address_1)) $address_1 = 'SIN CALLE';
		if (empty($address_2)) $address_2 = 'SN';
		if (empty($city)) $city = 'SIN CIUDAD';
		if (empty($state)) $state = 'SIN ESTADO';
		if (empty($postcode)) $postcode = '00000';
		if (empty($phone)) $phone = 'SIN TELEFONO';

		return strtoupper(
			'1^WEB^' . $address_1 . '^' . $address_2 . '^^'
			. $city . '^' . $state . '^' . $postcode . '^' . $phone
		);
	}
}

/** Construye pedidos SAIT. */
class SAIT_WOOCOMMERCE_OrderBuilder extends SAIT_WOOCOMMERCE_DocumentBuilder
{
	public function build($order, $formapago, $units, $customer)
	{
		return $this->common_document($order, $formapago, $units, $customer);
	}
}

/** Construye cotizaciones SAIT. */
class SAIT_WOOCOMMERCE_QuoteBuilder extends SAIT_WOOCOMMERCE_DocumentBuilder
{
	public function build($order, $formapago, $units, $customer)
	{
		$document = $this->common_document($order, $formapago, $units, $customer);
		$date = $order->get_date_created();
		$document->fecha = $date->date_i18n();
		$document->hora = date('H:i:s', $date->getTimestamp());

		return $document;
	}
}
