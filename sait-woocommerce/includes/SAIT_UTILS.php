<?php

/**
 * Utilidades compartidas de integración con SAIT.
 *
 * @since 1.0.3
 */
class SAIT_UTILS
{
	public static function SAIT_getClientebyemail($email)
	{
		if (empty($email) || !is_string($email) || !is_email($email)) {
			return '';
		}

		$api_response = self::SAIT_GetNube('/api/v3/clientes?emailtw=' . urlencode($email));
		$result = self::SAIT_getResult($api_response);
		if (empty($result)) {
			return '';
		}

		return str_pad($result[0]['numcli'], 5, ' ', STR_PAD_LEFT);
	}

	public static function SAIT_getClienteEventualbyemail($email)
	{
		$numcli = self::SAIT_getClientebyemail($email);

		return strpos($numcli, '-') !== false ? $numcli : '';
	}

	/**
	 * Ejecuta una consulta GET contra la API configurada de SAIT Nube.
	 *
	 * @param string $uri Ruta relativa de la API.
	 * @param bool   $reintentar Permite un segundo intento corto.
	 * @return array|null
	 */
	public static function SAIT_GetNube($uri, $reintentar = true)
	{
		return SAIT_WOOCOMMERCE()->sait_client()->get_legacy($uri, $reintentar);
	}

	/**
	 * @param array|null $response Respuesta decodificada.
	 * @return mixed|null
	 */
	public static function SAIT_getResult($response)
	{
		return is_array($response) && isset($response['result']) ? $response['result'] : null;
	}

	/**
	 * @param string       $uri Ruta relativa de la API.
	 * @param object|array $bodyObject Payload.
	 * @param bool         $wait Esperar respuesta.
	 * @return array|WP_Error
	 */
	public static function SAIT_PostNube($uri, $bodyObject, $wait = false)
	{
		return SAIT_WOOCOMMERCE()->sait_client()->post($uri, $bodyObject, $wait);
	}

	/**
	 * @param string      $tabla Entidad lógica.
	 * @param string|null $clave Clave SAIT.
	 * @param int|null    $wcid ID WooCommerce.
	 * @return object|null
	 */
	public static function SAIT_getClaves($tabla, $clave, $wcid)
	{
		if ($clave !== null) {
			$mapping = SAIT_WOOCOMMERCE()->mapping_repository()->find_by_sait_key($tabla, $clave);
			if ($mapping || $wcid === null) {
				return $mapping;
			}
		}

		if ($wcid !== null) {
			return SAIT_WOOCOMMERCE()->mapping_repository()->find_by_woocommerce_id($tabla, $wcid);
		}

		return null;
	}

	public static function SAIT_insertClaves($tabla, $clave, $wcid)
	{
		return SAIT_WOOCOMMERCE()->mapping_repository()->add($tabla, $clave, $wcid);
	}

	public static function SAIT_deleteClaves($id)
	{
		return SAIT_WOOCOMMERCE()->mapping_repository()->delete($id);
	}

	public static function SAIT_response($code, $message)
	{
		$response = new WP_REST_Response();
		$response->set_status($code);
		$response->set_data($message);

		return $response;
	}

	public static function SAIT_codigo_valido($codigo)
	{
		$codigo = trim($codigo);
		if (!preg_match('/^\d+$/', $codigo)) {
			return '';
		}

		return in_array(strlen($codigo), array(8, 10, 12, 13, 14), true) ? $codigo : '';
	}

	/**
	 * Calcula la existencia disponible para un SKU usando la API SAIT.
	 *
	 * @param string $SKU SKU/numart del artículo.
	 * @return float
	 */
	public static function getExistSAIT($SKU)
	{
		$settings = SAIT_WOOCOMMERCE()->settings();
		if (!$settings->has_saved_options()) {
			return 0;
		}

		$default_warehouse = $settings->get('SAITNube_NumAlm', '');
		$multiple_warehouses = $settings->is_enabled('SAITNube_ExistAlm_enabled');
		$allowed_warehouses = $multiple_warehouses ? $settings->warehouses() : array();
		$response = self::SAIT_GetNube('/api/v3/existencias/' . trim($SKU));
		$result = self::SAIT_getResult($response);
		if (empty($result)) {
			return 0;
		}

		$quantity = 0;
		foreach ($result as $warehouse) {
			$warehouse_number = isset($warehouse['numalm']) ? $warehouse['numalm'] : '';
			$stock = isset($warehouse['existencia']) ? (float) $warehouse['existencia'] : 0;

			if ($multiple_warehouses && in_array($warehouse_number, $allowed_warehouses)) {
				$quantity += $stock;
			} elseif (!$multiple_warehouses && $warehouse_number == $default_warehouse) {
				$quantity = $stock;
				break;
			}
		}

		return round($quantity, 2);
	}
}
