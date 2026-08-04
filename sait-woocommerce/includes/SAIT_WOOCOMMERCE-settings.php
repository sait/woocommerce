<?php

/**
 * Acceso centralizado a la configuracion persistida de SAIT WooCommerce.
 */
class SAIT_WOOCOMMERCE_Settings
{
	const OPTION_NAME = 'opciones_sait';

	/**
	 * @return array<string,mixed>
	 */
	public function defaults()
	{
		return array(
			'SAITNube_APIKey'                            => '',
			'SAITNube_URL'                               => '',
			'SAITNube_AccessToken'                       => '',
			'SAITNube_TipoDoc'                           => '',
			'SAITNube_Sucursal_enabled'                  => '0',
			'SAITNube_NumAlm'                            => null,
			'SAITNube_OcultarSinPrecio_enabled'          => '0',
			'SAITNube_ExistAlm_enabled'                  => '0',
			'SAITNube_ExistAlm'                          => '',
			'SAITNube_MinimoCarrito_Enabled'             => '0',
			'SAITNube_MinimoCarrito'                     => '',
			'SAITNube_TipoCambio'                        => '',
			'SAITNube_Promo_enabled'                     => '0',
			'SAITNube_PromoGlobal_enabled'               => '0',
			'SAITNube_PrecioLista'                       => '',
			'SAITNube_PedidoObs_enabled'                 => '0',
			'SAITNube_PedidoDirenvio_enabled'            => '0',
			'SAITNube_FuncionPersonalizadaPedido_enabled' => '0',
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public function all()
	{
		$options = get_option(self::OPTION_NAME, array());
		if (!is_array($options)) {
			$options = array();
		}

		return wp_parse_args($options, $this->defaults());
	}

	/**
	 * Indica si la opcion ya contiene configuracion persistida.
	 *
	 * @return bool
	 */
	public function has_saved_options()
	{
		$options = get_option(self::OPTION_NAME, array());

		return is_array($options) && !empty($options);
	}

	/**
	 * @param string $key Nombre historico de la opcion.
	 * @param mixed  $default Valor alternativo para claves no registradas.
	 * @return mixed
	 */
	public function get($key, $default = null)
	{
		$options = $this->all();

		return array_key_exists($key, $options) ? $options[$key] : $default;
	}

	/**
	 * @param string $key Nombre historico de una bandera.
	 * @return bool
	 */
	public function is_enabled($key)
	{
		return $this->get($key, '0') === '1';
	}

	/**
	 * Actualiza una clave interna sin descartar opciones historicas adicionales.
	 *
	 * @param string $key Nombre de la opcion.
	 * @param mixed  $value Valor ya validado por el flujo que lo origina.
	 * @return bool
	 */
	public function set($key, $value)
	{
		$options = get_option(self::OPTION_NAME, array());
		if (!is_array($options)) {
			$options = array();
		}

		$options[$key] = $value;

		return update_option(self::OPTION_NAME, $options);
	}

	/**
	 * Devuelve una lista normalizada de almacenes, sin valores vacios ni repetidos.
	 *
	 * @param string|null $value Lista; si se omite usa SAITNube_ExistAlm.
	 * @return string[]
	 */
	public function warehouses($value = null)
	{
		if ($value === null) {
			$value = $this->get('SAITNube_ExistAlm', '');
		}

		$warehouses = array_map('trim', explode(',', (string) $value));
		$warehouses = array_filter($warehouses, 'strlen');

		return array_values(array_unique($warehouses));
	}

	/**
	 * Sanitiza exclusivamente las claves conocidas por la pantalla de ajustes.
	 *
	 * @param mixed $input Valores enviados por Settings API.
	 * @return array<string,string>
	 */
	public function sanitize($input)
	{
		if (!is_array($input)) {
			return array();
		}

		$sanitized = array();
		$text_fields = array(
			'SAITNube_APIKey',
			'SAITNube_URL',
			'SAITNube_AccessToken',
			'SAITNube_TipoDoc',
			'SAITNube_NumAlm',
			'SAITNube_MinimoCarrito',
			'SAITNube_TipoCambio',
			'SAITNube_PrecioLista',
		);
		$boolean_fields = array(
			'SAITNube_Sucursal_enabled',
			'SAITNube_OcultarSinPrecio_enabled',
			'SAITNube_ExistAlm_enabled',
			'SAITNube_MinimoCarrito_Enabled',
			'SAITNube_Promo_enabled',
			'SAITNube_PromoGlobal_enabled',
			'SAITNube_PedidoObs_enabled',
			'SAITNube_PedidoDirenvio_enabled',
			'SAITNube_FuncionPersonalizadaPedido_enabled',
		);

		foreach ($text_fields as $key) {
			if (isset($input[$key])) {
				$sanitized[$key] = sanitize_text_field(wp_unslash($input[$key]));
			}
		}

		foreach ($boolean_fields as $key) {
			if (isset($input[$key])) {
				$sanitized[$key] = (string) $input[$key] === '1' ? '1' : '0';
			}
		}

		if (isset($input['SAITNube_ExistAlm'])) {
			$warehouse_value = sanitize_text_field(wp_unslash($input['SAITNube_ExistAlm']));
			$sanitized['SAITNube_ExistAlm'] = implode(',', $this->warehouses($warehouse_value));
		}

		return $sanitized;
	}
}
