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

	public static function SAIT_PostNube($uri,$bodyObject, $wait = false){
		$SAIT_options=get_option( 'opciones_sait' );
		$url = $SAIT_options['SAITNube_URL'].$uri;
		$apikey = $SAIT_options['SAITNube_APIKey'];
		$args = array(
		'method' => 'POST',
		'timeout' => 45,
		'redirection' => 5,
		'httpversion' => '1.0',
		'sslverify' => false,
		'blocking' => $wait ? true : false,
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

	public static function SAIT_codigo_valido($codigo) {
		$codigo = trim($codigo);

		// Debe contener solo dígitos
		if (!preg_match('/^\d+$/', $codigo)) {
		 return "";
		}
		// Longitudes válidas para GTIN/UPC/EAN/ISBN
		$longitudes_validas = [8, 10, 12, 13, 14];
		$len = strlen($codigo);
		if (in_array($len, $longitudes_validas, true)) {
			return $codigo;
		}
		return "";
	}

 }

// Agregar select de almacen al menu principal.
function agregar_boton_al_menu($items, $args) {
    $SAIT_options = get_option('opciones_sait');
	$Sucursal_activo = isset($SAIT_options['SAITNube_Sucursal_enabled']) && $SAIT_options['SAITNube_Sucursal_enabled'] === '1';

	if (!$Sucursal_activo) {
		return $items;
	}
	// Solo para el menú principal 
	if ($args->theme_location == 'primary' ) {
			$numalm = get_user_meta(get_current_user_id(), 'sucursal_seleccionada', true);
			$texto_boton = 'Seleccionar Sucursal'; // Texto por defecto
			
			// Si hay sucursal seleccionada, obtener su nombre
			if (!empty($numalm)) {
					$SAIT_options = get_option('opciones_sait');
					$almacen_default = $SAIT_options['SAITNube_NumAlm'] ?? '';
					$numalm = !empty($numalm) ? $numalm : $almacen_default;
					
					if (!empty($numalm)) {
							$response = SAIT_UTILS::SAIT_GetNube("/api/v3/almacenes");
							$sucursales = is_string($response) ? json_decode($response, true)['result'] ?? [] : $response['result'] ?? [];
							
							foreach ($sucursales as $sucursal) {
									if (trim($sucursal['numalm']) == $numalm) {
											$texto_boton = $sucursal['nomalm'];
											break;
									}
							}
					}
			}

			// Agregar el botón al menú
			$items .= '<li class="menu-item menu-item-sucursal">';
			$items .= '<a href="#" id="sucursal-button" class="sait-sucursal-btn">';
			$items .= '<i class="fa-solid fa-location-dot"></i> ';
			$items .= esc_html($texto_boton);
			$items .= '</a></li>';
	}
	return $items;
}
add_filter('wp_nav_menu_items', 'agregar_boton_al_menu', 10, 2);

/* Agregar el modal al footer */
function agregar_modal_sucursal() {
    $SAIT_options = get_option('opciones_sait');
	$Sucursal_activo = isset($SAIT_options['SAITNube_Sucursal_enabled']) && $SAIT_options['SAITNube_Sucursal_enabled'] === '1';

	if (!$Sucursal_activo) {
		return ;
	}
    $response = SAIT_UTILS::SAIT_GetNube("/api/v3/almacenes");
    $sucursales = isset($response['result']) ? $response['result'] : [];
    ?>
    <div id="sucursal-modal">
        <div class="modal-content">
            <h2 style="margin-top: 0; color: #2c3e50; text-align: center;">Selecciona tu Sucursal</h2>
            <ul class="lista-sucursales">
                <?php foreach ($sucursales as $item): ?>
                    <li>
                        <div class="sucursal-opcion" data-id="<?php echo esc_attr(trim($item['numalm'])); ?>">
                            <strong><?php echo esc_html($item['nomalm']); ?></strong>
                            <small>
                                <?php echo esc_html($item['calle'] . ' ' . $item['numext']); ?><br>
                                <?php echo esc_html($item['colonia'] . ', ' . $item['ciudad']); ?> <br>
																<?php echo esc_html($item['estado'] . ', ' . $item['cp']); ?> <br>
                            </small>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <button id="cerrar-modal" class="button">Cerrar</button>
        </div>
    </div>
    <?php
}
add_action('wp_footer', 'agregar_modal_sucursal');

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


add_action('woocommerce_single_product_summary', 'mostrar_tabla_almacenes', 25);

function mostrar_tabla_almacenes_prueba() {
    echo '<p style="color: red;">Hook funcionando: tabla de almacenes aparecería aquí.</p>';
}

function mostrar_tabla_almacenes() {
    $SAIT_options = get_option('opciones_sait');
	$ExistAlm_activo = isset($SAIT_options['SAITNube_ExistAlm_enabled']) && $SAIT_options['SAITNube_ExistAlm_enabled'] === '1';

	if (!$ExistAlm_activo) {
		return ;
	}
    global $product;
    $numart = $product->get_sku();

    $ruta_api = "/api/v3/existencias/" . trim($numart);

    // Llamada a tu función que consulta la API
    $respuesta = SAIT_UTILS::SAIT_GetNube($ruta_api);
    
	if (is_wp_error($respuesta)) {
		$error_message = $respuesta->get_error_message();
		echo '<p>Error al obtener existencias: ' . esc_html($error_message) . '</p>';
		return;
	}

	if (empty($respuesta) || !isset($respuesta['result']) || empty($respuesta['result'])) {
		echo '<p>No hay información de existencias (respuesta vacía o sin resultados).</p>';
		return;
	}

	if (!empty($respuesta['error'])) {
		echo '<p>Error en la respuesta de la API: ' . esc_html($respuesta['error']) . '</p>';
		return;
	}
	$almacenes = $respuesta['result'];

	echo '<h3>Existencias por sucursal</h3>';

	echo '<style>
	.tabla-almacenes {
		width: auto;
		border-collapse: collapse;
		margin-top: 10px;
	}
	.tabla-almacenes th, .tabla-almacenes td {
		border: 1px solid #ccc;
		padding: 4px 8px;
		text-align: left;
	}
	.tabla-almacenes th {
		background-color: #f0f0f0;
	}
	</style>';

	echo '<table class="tabla-almacenes">';
	echo '<tr><th>Sucursal</th><th>Existencia</th></tr>';

	$almacenes_a_mostrar = array_map('trim', explode(',', $SAIT_options['SAITNube_ExistAlm']));

	foreach ($almacenes as $almacen) {
		if (in_array($almacen['numalm'], $almacenes_a_mostrar)) {
			echo '<tr>';
			echo '<td>' . esc_html(trim($almacen['nomalm'])) . '</td>';
			echo '<td>' . esc_html(round($almacen['existencia'], 2)) . '</td>';
			echo '</tr>';
		}
	}

	echo '</table>';
}

add_action( 'woocommerce_product_query', 'ocultar_productos_sin_precio' );
function ocultar_productos_sin_precio( $query ) {
   
		$SAIT_options = get_option('opciones_sait');
		$OcultarSinPrecio = isset($SAIT_options['SAITNube_OcultarSinPrecio']) && $SAIT_options['SAITNube_OcultarSinPrecio'] === '1';

		if (!$OcultarSinPrecio) {
			return ;
		}
	
    // Solo front evitar Admin/REST
    if ( is_admin() || ( defined('REST_REQUEST') && REST_REQUEST ) ) return;

    // Aplicar solo al catálogo
    if ( ! is_shop() && ! is_product_taxonomy() && ! is_search() ) return;

    $meta_query = $query->get( 'meta_query' );

    if ( ! $meta_query ) {
        $meta_query = [];
    }

    // Precio mayor a 0
    $meta_query[] = array(
        'key' => '_price',
        'value' => 0,
        'compare' => '>',
        'type' => 'NUMERIC'
    );

    $query->set( 'meta_query', $meta_query );
}



add_filter('woocommerce_get_price_html', 'sait_precio_promocional_en_producto', 30, 2);
function sait_precio_promocional_en_producto($price_html, $product) {

    $SAIT_options = get_option('opciones_sait');
    $Promo_activo = isset($SAIT_options['SAITNube_PromoGlobal_enabled']) && $SAIT_options['SAITNube_PromoGlobal_enabled'] === '1';

    if (!$Promo_activo) {
        return $price_html;
    }

		if (is_admin()) {
				return $price_html;
		}
	
		// SKU
		$numart = $product->get_sku();
		if (!$numart) {
				return $price_html;
		}

		// Cliente
		$current_user = wp_get_current_user();
		// CACHE Datos del cliente (media hora)
		$current_user_id = get_current_user_id();
		$cache_cli = 'sait_cli_' . $current_user_id;
		$cli_cache = get_transient($cache_cli);

		if ($cli_cache !== false) {
			$numcli = $cli_cache;
		} else {
			$clave = SAIT_UTILS::SAIT_getClaves("clientes", null, $current_user_id);
			$numcli = (isset($clave->clave))
				? str_pad($clave->clave, 5, " ", STR_PAD_LEFT)
				: SAIT_UTILS::SAIT_getClientebyemail($current_user->user_email);

			if (empty($numcli) || strpos($numcli, '-') !== false) {
				$numcli = "    0";
			}else{
				SAIT_UTILS::SAIT_insertClaves("clientes",trim($numcli),$current_user_id);
			}

			set_transient($cache_cli, $numcli, 1800); // cache media hora
		}


    // Sucursal
    $sucursal_id = get_user_meta($current_user_id, 'sucursal_seleccionada', true);
    if (empty($sucursal_id)) {
        $sucursal_id = $SAIT_options['SAITNube_NumAlm'];
    }
    $sucursal_id = str_pad($sucursal_id, 2, " ", STR_PAD_LEFT);

    // Unidad
		// CACHE Artículo por SKU (24 horas)
		$cache_art = 'sait_art_' . $numart;
		$api_art = get_transient($cache_art);

		if ($api_art === false) {
			$api_art = SAIT_UTILS::SAIT_GetNube("/api/v3/articulos/" . $numart);
			if (!empty($api_art)) {
				set_transient($cache_art, $api_art, 86400); // 24 hrs
			}
		}
    $unidad = $api_art["result"]["unidad"];

    // --------------------------------------------
    //  TRANSIENT KEY (cache por producto + cliente + sucursal)
    // --------------------------------------------
    $cache_key = 'sait_precio_' . md5($numart . '_' . $numcli . '_' . $sucursal_id);
    $cached = get_transient($cache_key);

    if ($cached !== false) {
        $preciopub = $cached['preciopub'];
        $pje_api = $cached['pje_api'];
    } else {
        // Consulta REAL a la API
        $api_calc = SAIT_UTILS::SAIT_GetNube(
            "/api/v3/calcularprecios?numart=$numart&unidad=$unidad&cant=1&divisadoc=P&numalm=$sucursal_id&formapago=1&numcli=$numcli"
        );

        if (!isset($api_calc["result"])) {
            return $price_html;
        }

        $preciopub = floatval($api_calc["result"]["preciopub"]);
        $pje_api   = floatval($api_calc["result"]["pjedesc"]);

        // Guardar en caché 15 minutos
        set_transient($cache_key, [
            'preciopub' => $preciopub,
            'pje_api'   => $pje_api
        ], 900); // 900 = 15 minutos
    }

    $precio_regular = floatval($product->get_regular_price());
    $precio_promocional = round($preciopub,2);

    // Si API regresa precio en 0
    if ($preciopub <= 0) {
        return $price_html;
    }

    //   LÓGICA DE DESCUENTO
    if ($pje_api > 0) {

        // API trae descuento: SE USA
        $pjedesc = round($pje_api);
        $precio_promocional = $preciopub * (1 - ($pjedesc / 100));

    } else {

        // API no trae, calcular si aplica
        if ($precio_regular > 0 && $precio_promocional < $precio_regular) {
            $pjedesc = round((1 - ($precio_promocional / $precio_regular)) * 100);
        } else {
            return $price_html;
        }
    }

    if ($pje_api == 0 && $precio_promocional >= $precio_regular) {
        return $price_html;
    }

    // Nuevo HTML para el PRECIO
    $nuevo_html = '
    <div style="display:flex;align-items:center;gap:6px;margin-bottom:3px;">
        <span style="font-size:22px;color:#cc0000;font-weight:bold;">' . wc_price($precio_promocional) . '</span>
        <span style="background:#cc0000;color:white;padding:2px 6px;font-size:11px;border-radius:4px;font-weight:bold;">
            -' . $pjedesc . '%
        </span>
    </div>
    <small style="opacity:0.9;font-size:13px;">
        Antes: <del style="color:#3c3636;" >' . wc_price($precio_regular) . '</del>
    </small>
		';
	
   // Solo en página de producto
    if (is_product()) {
	 //HTML final 
		 $product_html = ' <span class="precio-promocion-principal" style="font-size:28px;color:#cc0000;font-weight:bold;"> ' . wc_price($precio_promocional) . ' </span><br> <span style="opacity:0.9; font-size:15px;"> Antes: <del  style="color:#3c3636;"  >' . wc_price($precio_regular) . '</del> </span><br> <span style="background:#cc0000;color:white;padding:3px 8px;border-radius:6px;font-size:13px;"> -' . $pjedesc . '% OFF </span> ';
		return $product_html;
    }
 
    return $nuevo_html;
}