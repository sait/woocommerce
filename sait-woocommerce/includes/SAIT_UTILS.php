<?php

/**
 * 
 * @link       http://sait.mx
 * @since      1.0.3
 *
 * @package    SAIT_WOOCOMMERCE
 * @subpackage SAIT_WOOCOMMERCE/includes
 */

/**
 *
 * En esta clase estan todas las funciones que se usaran constantemente
 * @since      1.0.3
 * @package    SAIT_WOOCOMMERCE
 * @subpackage SAIT_WOOCOMMERCE/includes
 * @author     Ali Moreno <ali@saitenlinea.com>
 */

	
 class SAIT_UTILS{
	public static function SAIT_getClientebyemail($email){
		//Consultar si el cliente existe en SAIT
		$api_response = self::SAIT_GetNube("/api/v3/clientes?emailtw=".urlencode($email));
		//return $api_response;
		if ($api_response["result"]!=null){
			return  str_pad($api_response["result"][0]["numcli"],5, " ", STR_PAD_LEFT);
		}
		return "";
	}

	public static function SAIT_getClienteEventualbyemail($email){
		//Consultar si el cliente evebntual existe en SAIT
		$api_response = self::SAIT_GetNube("/api/v3/clienteseventuales?email=".urlencode($email));
		if ($api_response["result"]!=null){
			return str_pad($api_response["result"][0]["numcliev"],5, " ", STR_PAD_LEFT);
		}
		return "";
	}


	public static function SAIT_GetNube($uri){
		$SAIT_options=get_option( 'opciones_sait' );
		$url = $SAIT_options['SAITNube_URL'].$uri;
		$apikey = $SAIT_options['SAITNube_APIKey'];
		$args = array(
			'timeout' => 5,
			'sslverify' => false,
			'blocking' => true,
			'headers' => array(
				'X-sait-api-key' => $apikey,
				'Content-Type' => 'application/json',
				'Accept' => 'application/json',
			),
		);
		$resSAIT =  wp_remote_get($url, $args);
		if ( ! is_wp_error( $resSAIT ) ) {
    		$body = wp_remote_retrieve_body( $resSAIT );
    		$data = json_decode( $body ,true );
			return $data;
		}
		return "";
	}

	public static function SAIT_PostNube($uri,$bodyObject){
		$SAIT_options=get_option( 'opciones_sait' );
		$url = $SAIT_options['SAITNube_URL'].$uri;
		$apikey = $SAIT_options['SAITNube_APIKey'];
		$args = array(
		'method' => 'POST',
		'timeout' => 45,
		'redirection' => 5,
		'httpversion' => '1.0',
		'sslverify' => false,
		'blocking' => false,
		'headers' => array(
			'X-sait-api-key' => $apikey,
			'Content-Type' => 'application/json',
			'Accept' => 'application/json',
		),
		'body' => json_encode($bodyObject),
		'cookies' => array()
		);

		return wp_remote_post($url, $args);
	}


	//
	// Funciones Claves SAIT
	// Tabla sait_claves creada en SAIT_WOOCOMMERCE-activator.php
	//
	public static function SAIT_getClaves($tabla,$clave,$wcid){
		global $wpdb;
		return $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."sait_claves WHERE tabla = '".$tabla."'and (clave = '".$clave."' or wcid ='" .$wcid."')", OBJECT);
	}
	public static function SAIT_insertClaves($tabla,$clave,$wcid){
		global $wpdb;
		$wpdb->insert( 
			$wpdb->prefix.'sait_claves', 
				array( 
						'tabla' => $tabla,
						'clave' => $clave,
						'wcid'  => $wcid
				)
		);
	}

	public static function SAIT_deleteClaves($id){
		global $wpdb;
		$wpdb->delete( $wpdb->prefix.'sait_claves', array( 'id' => $id ) );
	}

	public static function SAIT_response($code,$message){
		$res = new WP_REST_Response();
		$res->set_status($code);
		$res->set_data($message);
		return $res;
	}

 }

// Agregar select de almacen al menu principal.
function agregar_droplist_al_menu($items, $args) {
	if ($args->theme_location == 'primary' && is_user_logged_in()) {
			$response = SAIT_UTILS::SAIT_GetNube("/api/v3/almacenes");

			// Decodificar JSON si es un string
			if (is_string($response)) {
					$response = json_decode($response, true);
			}

			// Iniciar sesión para recuperar la sucursal seleccionada
			if (!session_id()) {
					session_start();
			}
			$sucursal_seleccionada = get_user_meta(get_current_user_id(), 'sucursal_seleccionada', true);

			// Verificar si existe "result" y es un array
			if (isset($response['result']) && is_array($response['result']) && !is_wp_error($response)) {
					$select_html = '<li class="menu-item custom-menu-dropdown">';
					$select_html .= '<select id="sucursal-select">';
					$select_html .= '<option value="">Selecciona una sucursal</option>';

					foreach ($response['result'] as $item) {
							if (isset($item['numalm'], $item['nomalm'])) {
									$numalm = trim($item['numalm']); // Eliminar espacios extra en numalm
									$selected = ($numalm == $sucursal_seleccionada) ? ' selected="selected"' : ''; // Marcar si coincide
									$select_html .= '<option value="' . esc_attr($numalm) . '"' . $selected . '>' . esc_html($item['nomalm']) . '</option>';
							}
					}

					$select_html .= '</select></li>';
					$items .= $select_html;
			} else {
					$items .= '<li class="menu-item">Error al cargar sucursales.</li>';
			}
	}
	return $items;
}
add_filter('wp_nav_menu_items', 'agregar_droplist_al_menu', 10, 2);

// Función para manejar la solicitud AJAX
function guardar_sucursal() {
check_ajax_referer('sait-woocommerce_nonce', 'nonce'); // Verificar nonce para seguridad

if (isset($_POST['sucursal_id'])) {
		$sucursal_id = intval($_POST['sucursal_id']);
		
		// Guardar en los metadatos del usuario (requiere que el usuario esté logueado)
		update_user_meta(get_current_user_id(), 'sucursal_seleccionada', $sucursal_id);
		// recuperarlo en alguna funcion
		$sucursal_id = get_user_meta(get_current_user_id(), 'sucursal_seleccionada', true);

		wp_send_json_success($sucursal_id);
} else {
		wp_send_json_error('Error al guardar la sucursal.');
}
wp_die();
}

add_action('wp_ajax_guardar_sucursal', 'guardar_sucursal');
add_action('wp_ajax_nopriv_guardar_sucursal', 'guardar_sucursal');