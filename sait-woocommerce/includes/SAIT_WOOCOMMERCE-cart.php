<?php
add_action('woocommerce_before_calculate_totals', 'calcularpreciosCarrito');

function calcularpreciosCarrito($cart) {
    // Verificar si estamos en el área de administración o no es una solicitud AJAX
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }
    $SAIT_options = get_option('opciones_sait');
	$Promo_activo = isset($SAIT_options['SAITNube_Promo_enabled']) && $SAIT_options['SAITNube_Promo_enabled'] === '1';

	if (!$Promo_activo) {
		return ;
	}
    // Recorrer cada artículo en el carrito
    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        // Obtener el producto
        $product = $cart_item['data'];
		$numart = $product->get_sku();
		$api_response = SAIT_UTILS::SAIT_GetNube("/api/v3/articulos/".$numart);
		$unidad = $api_response["result"]["unidad"];
		$current_user = wp_get_current_user();
		$clave = SAIT_UTILS::SAIT_getClaves("clientes",null,get_current_user_id());		 
		$numcli = "    0";
        $original_price = $product->get_regular_price();

		if (isset($clave->clave)){
		 	$numcli =  str_pad($clave->clave,5, " ", STR_PAD_LEFT);
		}else{
			if (isset($current_user->user_email)){
				$numcli = SAIT_UTILS::SAIT_getClientebyemail($current_user->user_email);
			}
		}
		if (strpos($numcli, '-') !== false) {
			$numcli = "    0";
		} 
		
		if (empty($numcli) || strpos($numcli, '-') !== false) {
			$numcli = "    0";
		} 
		$cantidad = $cart_item['quantity'];
		$sucursal_id = get_user_meta(get_current_user_id(), 'sucursal_seleccionada', true);
		// Si no hay sucursal seleccionada, tomar la sucursal por defecto
        if (empty($sucursal_id)) {
            $SAIT_options = get_option('opciones_sait');
            $sucursal_id = isset($SAIT_options['SAITNube_NumAlm']) ? $SAIT_options['SAITNube_NumAlm'] : '';
        }
		$sucursal_id =  str_pad( $sucursal_id,2, " ", STR_PAD_LEFT);
		$api_response = SAIT_UTILS::SAIT_GetNube("/api/v3/calcularprecios?numart=".$numart."&unidad=".$unidad."&cant=".$cantidad."&divisadoc=P&numalm=".$sucursal_id."&formapago=1&numcli=".$numcli);
        // Validar errores en respuesta
        if (
            !isset($api_response["result"]) ||
            !isset($api_response["result"]["preciopub"]) ||
            !isset($api_response["result"]["pjedesc"])
        ) {
            $product->set_price($original_price);
            continue;
        }

        $preciopub = floatval($api_response["result"]["preciopub"]);
        $pjedesc   = floatval($api_response["result"]["pjedesc"]);

        // Si precio API viene en 0 → dejar precio regular
        if ($preciopub <= 0) {
            $product->set_price($original_price);
            continue;
        }

        // Calcular nuevo precio
        $discounted_price = $preciopub * (1 - ($pjedesc / 100));

        // Si el resultado es inválido o 0 dejar precio regular
        if ($discounted_price <= 0 || empty($discounted_price)) {
            $product->set_price($original_price);
            continue;
        }

        // Solo aplicar si el precio con descuento es menor al precio normal
        if (round($discounted_price,2) < $original_price) {
            $product->set_price($discounted_price);
        }
    }
}


add_filter('woocommerce_cart_item_price', 'display_discounted_price_in_cart', 10, 3);

function display_discounted_price_in_cart($price, $cart_item, $cart_item_key) {
    $product = $cart_item['data'];
    $original_price = $product->get_regular_price();
    $discounted_price = $product->get_price();

    if ($discounted_price < $original_price) {
        $price = wc_price($discounted_price) . ' <del>' . wc_price($original_price) . '</del>';
    }

    return $price;
}



//  Establecer monto mínimo de carrito antes de permitir checkout
add_action( 'woocommerce_checkout_process', 'sait_minimo_total_carrito' );
add_action( 'woocommerce_before_cart', 'sait_minimo_total_carrito' );

function sait_minimo_total_carrito() {
	// Si no esta activo no hace nada
	$SAIT_options=get_option( 'opciones_sait' );
	$Minimo_activo = isset($SAIT_options['SAITNube_MinimoCarrito_Enabled']) && $SAIT_options['SAITNube_MinimoCarrito_Enabled'] === '1';

	if (!$Minimo_activo) {
		return ;
	}
    $minimo = floatval($SAIT_options['SAITNube_MinimoCarrito']); //  Cambia el monto mínimo

    $subtotal = WC()->cart->get_subtotal();

    if ( WC()->cart && $subtotal < $minimo ) {
        if ( is_cart() ) {
            wc_print_notice( 
                sprintf( 'Tu pedido actual es de %s — el monto mínimo para comprar es %s.', 
                    wc_price( $subtotal ), 
                    wc_price( $minimo )
                ), 'error' 
            );
        } else {
            wc_add_notice( 
                sprintf( 'Tu pedido actual es de %s — el monto mínimo para comprar es %s.', 
                    wc_price( $subtotal ), 
                    wc_price( $minimo )
                ), 'error' 
            );
        }
    }
}

// Bloquear botones de checkout y PayPal si no se cumple el mínimo
add_action( 'wp_footer', 'sait_bloquear_botones_checkout' );
function sait_bloquear_botones_checkout() {
    if ( is_cart() || is_checkout() ) :
		// Si no esta activo no hace nada
		$SAIT_options=get_option( 'opciones_sait' );
		$Minimo_activo = isset($SAIT_options['SAITNube_MinimoCarrito_Enabled']) && $SAIT_options['SAITNube_MinimoCarrito_Enabled'] === '1';

		if (!$Minimo_activo) {
			return ;
		}
		$minimo = floatval($SAIT_options['SAITNube_MinimoCarrito']); //  Cambia el monto mínimo
        ?>
        <script type="text/javascript">
        jQuery(function($){
            function bloquearSiNoCumple() {
                // WooCommerce muestra el total en diferentes lugares según el theme
                var totalText = $("tr.cart-subtotal td .woocommerce-Price-amount bdi, td[data-title='Subtotal'] bdi").last().text();
                if (!totalText) return;

                // Limpieza de texto -> convertir a número
                var total = parseFloat(totalText.replace(/[^0-9.,]/g, '').replace(",", ""));
                var minimo = <?php echo $minimo; ?>;

                if ( total < minimo ) {
                    // Deshabilitar botones de checkout y métodos de pago
                    $(".checkout-button, #place_order, .wc-proceed-to-checkout a, .paypal-button, .wc-stripe-checkout-button")
                        .prop("disabled", true)
                        .css({"opacity":"0.5","pointer-events":"none"});
                } else {
                    // Habilitar botones
                    $(".checkout-button, #place_order, .wc-proceed-to-checkout a, .paypal-button, .wc-stripe-checkout-button")
                        .prop("disabled", false)
                        .css({"opacity":"1","pointer-events":"auto"});
                }
            }

            bloquearSiNoCumple();

            // Reevaluar cuando WooCommerce actualice totales del carrito o checkout
            $(document.body).on("updated_cart_totals updated_checkout", bloquearSiNoCumple);
        });
        </script>
        <?php
    endif;
}

