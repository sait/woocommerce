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
function agregar_boton_al_menu($items, $args) {
    if ($args->theme_location == 'primary' && is_user_logged_in()) {
        $numalm = get_user_meta(get_current_user_id(), 'sucursal_seleccionada', true);
        $nombre_sucursal = 'Seleccionar Sucursal';
		// Si no hay sucursal seleccionada, tomar la sucursal por defecto
        if (empty($numalm)) {
            $SAIT_options = get_option('opciones_sait');
            $numalm = isset($SAIT_options['SAITNube_NumAlm']) ? $SAIT_options['SAITNube_NumAlm'] : '';
        }
        if ($numalm) {
            // Obtener el nombre de la sucursal desde la lista de sucursales
            $response = SAIT_UTILS::SAIT_GetNube("/api/v3/almacenes");
            if (is_string($response)) {
                $response = json_decode($response, true);
            }
            $sucursales = isset($response['result']) && is_array($response['result']) ? $response['result'] : [];

            foreach ($sucursales as $sucursal) {
                if (trim($sucursal['numalm']) == $numalm) {
                    $nombre_sucursal = $sucursal['nomalm'];
                    break;
                }
            }
        }

        $items .= '<li class="menu-item"><a href="#" id="sucursal-button"><i class="fas fa-map-pin"></i> ' . esc_html($nombre_sucursal) . '</a></li>';
    }
    return $items;
}
add_filter('wp_nav_menu_items', 'agregar_boton_al_menu', 10, 2);

/* Agregar el modal al footer */
function agregar_modal_sucursal() {
	if (!is_user_logged_in()) return;
	
	$response = SAIT_UTILS::SAIT_GetNube("/api/v3/almacenes");
	if (is_string($response)) {
			$response = json_decode($response, true);
	}
	
	$sucursales = isset($response['result']) && is_array($response['result']) ? $response['result'] : [];
	?>
	<div id="sucursal-modal" class="modal-overlay">
    <div class="modal-content">
        <h2>Selecciona una Sucursal</h2>
        <ul class="lista-sucursales">
            <?php foreach ($sucursales as $item): ?>
                <li>
                    <div class="sucursal-opcion" data-id="<?php echo esc_attr(trim($item['numalm'])); ?>">
                        <strong><?php echo esc_html($item['nomalm']); ?></strong>
                        <small><?php echo esc_html($item['calle'] . ' ' . $item['numext'] . ', ' . $item['ciudad'] . ', ' . $item['Estado'] . ' ' . $item['cp']); ?></small>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
        <button id="cerrar-modal">Cerrar</button>
    </div>
</div>
	<?php
}
add_action('wp_footer', 'agregar_modal_sucursal');

// Función para manejar la solicitud AJAX
function guardar_sucursal() {
check_ajax_referer('sait-woocommerce_nonce', 'nonce'); // Verificar nonce para seguridad

if (isset($_POST['sucursal_id'])) {
		$numalm = intval($_POST['sucursal_id']);
		
		// Guardar en los metadatos del usuario (requiere que el usuario esté logueado)
		update_user_meta(get_current_user_id(), 'sucursal_seleccionada', $numalm);
		// recuperarlo en alguna funcion
		$numalm = get_user_meta(get_current_user_id(), 'sucursal_seleccionada', true);

		wp_send_json_success($numalm);
} else {
		wp_send_json_error('Error al guardar la sucursal.');
}
wp_die();
}

add_action('wp_ajax_guardar_sucursal', 'guardar_sucursal');
add_action('wp_ajax_nopriv_guardar_sucursal', 'guardar_sucursal');