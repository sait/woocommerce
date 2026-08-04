<?php

/**
 * Coordina datos externos, construccion y envio de documentos SAIT.
 */
class SAIT_WOOCOMMERCE_DocumentService
{
	/** @var SAIT_WOOCOMMERCE_Settings */
	private $settings;

	/** @var SAIT_WOOCOMMERCE_CustomerResolver */
	private $customer_resolver;

	/** @var SAIT_WOOCOMMERCE_SaitClientInterface */
	private $sait_client;

	/** @var SAIT_WOOCOMMERCE_Logger */
	private $logger;

	public function __construct($settings, $customer_resolver, $sait_client, $logger)
	{
		$this->settings = $settings;
		$this->customer_resolver = $customer_resolver;
		$this->sait_client = $sait_client;
		$this->logger = $logger;
	}

	/**
	 * Construye un pedido sin enviarlo.
	 *
	 * @return object
	 */
	public function build_order($order, $payment_method)
	{
		$options = $this->settings->all();
		$builder = new SAIT_WOOCOMMERCE_OrderBuilder($options);
		$document = $builder->build(
			$order,
			$payment_method,
			$this->resolve_item_units($order, 'P'),
			$this->customer_resolver->resolve($order)
		);

		$document = $this->customize_legacy($document, $order, $options, 'P');
		/**
		 * Filtra el payload de un pedido antes de enviarlo a SAIT.
		 *
		 * @param object   $document Payload construido por el plugin.
		 * @param WC_Order $order Orden WooCommerce de origen.
		 */
		$document = apply_filters('sait_woocommerce_order_payload', $document, $order);

		/**
		 * Filtra cualquier documento antes de enviarlo a SAIT.
		 *
		 * @param object   $document Payload construido por el plugin.
		 * @param WC_Order $order Orden WooCommerce de origen.
		 * @param string   $document_type `P` para pedido o `Q` para cotización.
		 */
		return apply_filters('sait_woocommerce_document_payload', $document, $order, 'P');
	}

	/**
	 * Construye una cotizacion sin enviarla.
	 *
	 * @return object
	 */
	public function build_quote($order, $payment_method)
	{
		$options = $this->settings->all();
		$builder = new SAIT_WOOCOMMERCE_QuoteBuilder($options);
		$document = $builder->build(
			$order,
			$payment_method,
			$this->resolve_item_units($order, 'Q'),
			$this->customer_resolver->resolve($order)
		);

		$document = $this->customize_legacy($document, $order, $options, 'Q');
		/**
		 * Filtra el payload de una cotización antes de enviarla a SAIT.
		 *
		 * @param object   $document Payload construido por el plugin.
		 * @param WC_Order $order Orden WooCommerce de origen.
		 */
		$document = apply_filters('sait_woocommerce_quote_payload', $document, $order);

		/** Este filtro se documenta en build_order(). */
		return apply_filters('sait_woocommerce_document_payload', $document, $order, 'Q');
	}

	/**
	 * Envia un payload ya construido.
	 *
	 * @param string $endpoint Ruta SAIT permitida.
	 * @param object $document Payload construido.
	 * @param bool $wait Espera la respuesta cuando es true.
	 * @return array|WP_Error
	 */
	public function send_document($endpoint, $document, $wait = false)
	{
		return $this->sait_client->post($endpoint, $document, $wait);
	}

	/** @return array|WP_Error */
	public function send_order($order, $payment_method, $wait = false)
	{
		return $this->send_document(
			'/api/v3/pedidos',
			$this->build_order($order, $payment_method),
			$wait
		);
	}

	/** @return array|WP_Error */
	public function send_quote($order, $payment_method, $wait = false)
	{
		return $this->send_document(
			'/api/v3/cotizaciones',
			$this->build_quote($order, $payment_method),
			$wait
		);
	}

	/**
	 * @return array<int|string,string>
	 */
	private function resolve_item_units($order, $document_type)
	{
		$units = array();
		$this->logger->info(
			$document_type === 'P' ? 'Preparando pedido para SAIT.' : 'Preparando cotizacion para SAIT.',
			array(
				'order_id'      => $order->get_id(),
				'document_type' => $document_type,
				'item_count'    => count($order->get_items()),
			)
		);

		foreach ($order->get_items() as $item_id => $item) {
			$product = $item->get_product();
			$sku = $product->get_sku();
			$result = null;
			$attempts = 0;
			while (!isset($result['unidad']) && $attempts < 3) {
				if ($attempts > 0) {
					usleep($attempts * 500000);
				}
				$response = $this->sait_client->get('/api/v3/articulos/' . $sku, false);
				$result = isset($response['result']) && is_array($response['result'])
					? $response['result']
					: null;
				$attempts++;
			}

			if (!isset($result['unidad'])) {
				$this->logger->warning(
					'No se obtuvo la unidad del articulo para el documento.',
					array(
						'order_id'      => $order->get_id(),
						'sku'           => $sku,
						'attempt'       => $attempts,
						'document_type' => $document_type,
					)
				);
			}

			$units[$item_id] = isset($result['unidad']) ? $result['unidad'] : '';
		}

		return $units;
	}

	/**
	 * Conserva durante la transición la clase SAIT_PERSONALIZADO y su opción histórica.
	 * Los complementos nuevos deben usar los filtros públicos de payload.
	 *
	 * @return object
	 */
	private function customize_legacy($document, $order, $options, $document_type)
	{
		$enabled = isset($options['SAITNube_FuncionPersonalizadaPedido_enabled'])
			&& $options['SAITNube_FuncionPersonalizadaPedido_enabled'] === '1';
		/**
		 * Permite que un complemento sustituya el personalizador heredado.
		 *
		 * @param bool     $enabled Indica si la opción histórica está activa.
		 * @param WC_Order $order Orden WooCommerce de origen.
		 * @param string   $document_type `P` para pedido o `Q` para cotización.
		 */
		$enabled = (bool) apply_filters(
			'sait_woocommerce_legacy_customizer_enabled',
			$enabled,
			$order,
			$document_type
		);

		return $enabled
			? SAIT_PERSONALIZADO::SAIT_FuncionPersonalizaPostPedido($document, $order)
			: $document;
	}
}
