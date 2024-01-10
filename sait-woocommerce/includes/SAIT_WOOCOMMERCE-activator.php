<?php

/**
 * Fired during plugin activation
 *
 * @link       http://sait.mx
 * @since      1.0.3
 *
 * @package    SAIT_WOOCOMMERCE
 * @subpackage SAIT_WOOCOMMERCE/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.3
 * @package    SAIT_WOOCOMMERCE
 * @subpackage SAIT_WOOCOMMERCE/includes
 * @author     Ali Moreno <ali@saitenlinea.com>
 */

 class SAIT_WOOCOMMERCE_Activator {

/**
 * SAIT_create_db.
 *
 * Funcion de inicializacion que crea la base de datos de claves.
 *  https://wpmudev.com/blog/creating-database-tables-for-plugins/
 * 
 * @since    1.0.3
 */

//
// Crea la tabla sait_claves
//
//
public static  function SAIT_create_db() {
	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();
	$table_name = $wpdb->prefix . 'sait_claves';

	$sql = "CREATE TABLE $table_name (
		`id` INT NOT NULL AUTO_INCREMENT,
		`tabla` VARCHAR(20),
		`clave` VARCHAR(20),
		`wcid` INT(12),
		PRIMARY KEY (`id`)
	) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	// dbDelta crea o actualiza la tabla si ya existe
	dbDelta( $sql );
}


}