<?php

/**
 * Gestiona activacion, actualizaciones de esquema y desactivacion del plugin.
 */
class SAIT_WOOCOMMERCE_Lifecycle
{
	const SCHEMA_VERSION = '1.0.0';
	const SCHEMA_OPTION = 'sait_woocommerce_db_version';

	/**
	 * Crea la tabla actual y registra su version.
	 *
	 * @return void
	 */
	public static function activate()
	{
		self::upgrade_schema();
	}

	/**
	 * Ejecuta una actualizacion idempotente cuando cambia la version.
	 *
	 * @return void
	 */
	public static function maybe_upgrade()
	{
		if (get_option(self::SCHEMA_OPTION) !== self::SCHEMA_VERSION) {
			self::upgrade_schema();
		}
	}

	/**
	 * La desactivacion conserva configuracion, mapeos y metadatos.
	 *
	 * @return void
	 */
	public static function deactivate()
	{
		// No se eliminan datos al desactivar el plugin.
	}

	/**
	 * Aplica el esquema mediante el activador existente.
	 *
	 * @return void
	 */
	private static function upgrade_schema()
	{
		require_once __DIR__ . '/SAIT_WOOCOMMERCE-activator.php';
		SAIT_WOOCOMMERCE_Activator::SAIT_create_db();
		update_option(self::SCHEMA_OPTION, self::SCHEMA_VERSION, false);
	}
}
