<?php

/**
 * Resuelve la representacion de cliente que debe enviarse a SAIT.
 */
class SAIT_WOOCOMMERCE_CustomerResolver
{
	/** @var SAIT_WOOCOMMERCE_MappingRepository */
	private $mapping_repository;

	/** @var SAIT_WOOCOMMERCE_SaitClientInterface */
	private $sait_client;

	/**
	 * @param SAIT_WOOCOMMERCE_MappingRepository $mapping_repository Repositorio de relaciones.
	 * @param SAIT_WOOCOMMERCE_SaitClientInterface $sait_client Cliente HTTP de SAIT.
	 */
	public function __construct($mapping_repository, $sait_client)
	{
		$this->mapping_repository = $mapping_repository;
		$this->sait_client = $sait_client;
	}

	/**
	 * Devuelve exactamente una representacion activa: numcli, numcliev o clievent.
	 *
	 * El endpoint /clientes devuelve clientes normales y eventuales. SAIT identifica
	 * los eventuales porque su numcli contiene un guion.
	 *
	 * @param WC_Order $order Orden WooCommerce.
	 * @return array{numcli:string,numcliev:string,clievent:object|null}
	 */
	public function resolve($order)
	{
		$identifier = $this->mapped_identifier($order);
		if ($identifier === '') {
			$identifier = $this->identifier_by_email($order->get_billing_email());
		}

		if ($identifier !== '') {
			return $this->existing_customer($identifier);
		}

		return array(
			'numcli'   => '',
			'numcliev' => '',
			'clievent' => $this->new_eventual_customer($order),
		);
	}

	/**
	 * @param WC_Order $order Orden WooCommerce.
	 * @return string
	 */
	private function mapped_identifier($order)
	{
		$user_id = (int) $order->get_user_id();
		if ($user_id <= 0) {
			return '';
		}

		$mapping = $this->mapping_repository->find_customer_by_user_id($user_id);

		return $mapping && isset($mapping->clave) ? trim((string) $mapping->clave) : '';
	}

	/**
	 * @param string $email Correo de facturacion.
	 * @return string
	 */
	private function identifier_by_email($email)
	{
		if (!is_string($email) || !is_email($email)) {
			return '';
		}

		$response = $this->sait_client->get('/api/v3/clientes?emailtw=' . urlencode($email));
		$result = isset($response['result']) && is_array($response['result']) ? $response['result'] : array();
		if (!isset($result[0]) || !is_array($result[0]) || !isset($result[0]['numcli'])) {
			return '';
		}

		return trim((string) $result[0]['numcli']);
	}

	/**
	 * @param string $identifier Identificador devuelto por SAIT o guardado localmente.
	 * @return array{numcli:string,numcliev:string,clievent:null}
	 */
	private function existing_customer($identifier)
	{
		$padded = str_pad($identifier, 5, ' ', STR_PAD_LEFT);
		$is_eventual = strpos($identifier, '-') !== false;

		return array(
			'numcli'   => $is_eventual ? '' : $padded,
			'numcliev' => $is_eventual ? $padded : '',
			'clievent' => null,
		);
	}

	/**
	 * @param WC_Order $order Orden WooCommerce.
	 * @return object
	 */
	private function new_eventual_customer($order)
	{
		$customer = new stdClass();
		$customer->nomcliev = $order->get_formatted_billing_full_name();
		$customer->calle = $order->get_billing_address_1();
		$customer->ciudad = $order->get_billing_city();
		$customer->estado = $order->get_billing_state();
		$customer->telefono = $order->get_billing_phone();
		$customer->email = $order->get_billing_email();

		return $customer;
	}
}
