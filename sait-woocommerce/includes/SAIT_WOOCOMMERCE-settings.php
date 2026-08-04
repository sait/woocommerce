<?php

/**
 * Acceso centralizado a la configuracion persistida de SAIT WooCommerce.
 */
class SAIT_WOOCOMMERCE_Settings
{
	const OPTION_NAME = 'opciones_sait';
	const CATEGORY_SOURCE_KEY = 'SAITNube_CategoriaFuente';
	const DEFAULT_CATEGORY_SOURCE = 'linea';

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
			self::CATEGORY_SOURCE_KEY                    => self::DEFAULT_CATEGORY_SOURCE,
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
	 * Correspondencia exacta entre MODART y los eventos que crean cada mapeo.
	 *
	 * @return array<string,array<string,string>>
	 */
	public function category_sources()
	{
		return array(
			'linea' => array(
				'label'             => 'Línea',
				'article_attribute' => 'linea',
				'mapping_table'     => 'lineas',
				'event_key'         => 'numlin',
			),
			'familia' => array(
				'label'             => 'Familia',
				'article_attribute' => 'familia',
				'mapping_table'     => 'familia',
				'event_key'         => 'numfam',
			),
			'categoria' => array(
				'label'             => 'Categoría',
				'article_attribute' => 'categoria',
				'mapping_table'     => 'catego',
				'event_key'         => 'numcat',
			),
			'departamento' => array(
				'label'             => 'Departamento',
				'article_attribute' => 'numdep',
				'mapping_table'     => 'deptos',
				'event_key'         => 'valdep',
			),
		);
	}

	/**
	 * @return string
	 */
	public function category_source()
	{
		return $this->sanitize_category_source($this->get(self::CATEGORY_SOURCE_KEY));
	}

	/**
	 * @return array<string,string>
	 */
	public function category_source_config()
	{
		$sources = $this->category_sources();

		return $sources[$this->category_source()];
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

		if (isset($input[self::CATEGORY_SOURCE_KEY])) {
			$sanitized[self::CATEGORY_SOURCE_KEY] = $this->sanitize_category_source(
				wp_unslash($input[self::CATEGORY_SOURCE_KEY])
			);
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

	/**
	 * @param mixed $value Fuente recibida.
	 * @return string
	 */
	private function sanitize_category_source($value)
	{
		$value = sanitize_key((string) $value);
		$sources = $this->category_sources();

		return isset($sources[$value]) ? $value : self::DEFAULT_CATEGORY_SOURCE;
	}
}
