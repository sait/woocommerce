<?php

/**
 * Repositorio unico para relaciones entre entidades SAIT y WooCommerce.
 */
class SAIT_WOOCOMMERCE_MappingRepository
{
	const PRODUCTS = 'arts';
	const CUSTOMERS = 'clientes';

	/** @var wpdb */
	private $wpdb;

	/** @var string */
	private $table_name;

	/**
	 * @param wpdb|null $database Instancia inyectable de base de datos.
	 */
	public function __construct($database = null)
	{
		if ($database === null) {
			global $wpdb;
			$database = $wpdb;
		}

		$this->wpdb = $database;
		$this->table_name = $database->prefix . 'sait_claves';
	}

	/**
	 * @param string $entity Nombre historico de tabla logica.
	 * @param string $sait_key Clave SAIT.
	 * @return object{id:int,tabla:string,clave:string,wcid:int}|null
	 */
	public function find_by_sait_key($entity, $sait_key)
	{
		$sql = $this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE tabla = %s AND clave = %s ORDER BY id ASC LIMIT 1",
			$entity,
			$sait_key
		);

		return $this->wpdb->get_row($sql, OBJECT);
	}

	/**
	 * @param string $entity Nombre historico de tabla logica.
	 * @param int    $woocommerce_id ID WordPress/WooCommerce.
	 * @return object{id:int,tabla:string,clave:string,wcid:int}|null
	 */
	public function find_by_woocommerce_id($entity, $woocommerce_id)
	{
		$sql = $this->wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE tabla = %s AND wcid = %d ORDER BY id ASC LIMIT 1",
			$entity,
			$woocommerce_id
		);

		return $this->wpdb->get_row($sql, OBJECT);
	}

	/**
	 * @param string $numart Numero de articulo SAIT.
	 * @return object{id:int,tabla:string,clave:string,wcid:int}|null
	 */
	public function find_product($numart)
	{
		return $this->find_by_sait_key(self::PRODUCTS, $numart);
	}

	/**
	 * @param string $numcli Numero de cliente SAIT.
	 * @return object{id:int,tabla:string,clave:string,wcid:int}|null
	 */
	public function find_customer($numcli)
	{
		return $this->find_by_sait_key(self::CUSTOMERS, $numcli);
	}

	/**
	 * @param int $user_id ID de usuario WordPress.
	 * @return object{id:int,tabla:string,clave:string,wcid:int}|null
	 */
	public function find_customer_by_user_id($user_id)
	{
		return $this->find_by_woocommerce_id(self::CUSTOMERS, $user_id);
	}

	/**
	 * @param string $mapping_table Tabla logica: lineas, familia, catego o deptos.
	 * @param string $category_key Clave SAIT de la clasificacion.
	 * @return object{id:int,tabla:string,clave:string,wcid:int}|null
	 */
	public function find_category($mapping_table, $category_key)
	{
		return $this->find_by_sait_key($mapping_table, $category_key);
	}

	/**
	 * Inserta una relacion sólo cuando no existe la misma tabla y clave SAIT.
	 * No depende de una restriccion unica porque los datos existentes aun deben auditarse.
	 *
	 * @param string $entity Nombre historico de tabla logica.
	 * @param string $sait_key Clave SAIT.
	 * @param int    $woocommerce_id ID WordPress/WooCommerce.
	 * @return int|false ID existente/nuevo, o false si la insercion falla.
	 */
	public function add($entity, $sait_key, $woocommerce_id)
	{
		$existing = $this->find_by_sait_key($entity, $sait_key);
		if ($existing) {
			return (int) $existing->id;
		}

		$inserted = $this->wpdb->insert(
			$this->table_name,
			array(
				'tabla' => $entity,
				'clave' => $sait_key,
				'wcid'  => $woocommerce_id,
			),
			array('%s', '%s', '%d')
		);

		return $inserted === false ? false : (int) $this->wpdb->insert_id;
	}

	/**
	 * @param int $id ID de la relacion.
	 * @return bool
	 */
	public function delete($id)
	{
		$deleted = $this->wpdb->delete(
			$this->table_name,
			array('id' => (int) $id),
			array('%d')
		);

		return $deleted !== false;
	}
}
