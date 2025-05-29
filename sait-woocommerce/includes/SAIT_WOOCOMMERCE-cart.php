<?php
add_action('woocommerce_before_calculate_totals', 'calcularpreciosCarrito');

function calcularpreciosCarrito($cart) {
    // Verificar si estamos en el área de administración o no es una solicitud AJAX
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
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
		if (isset($clave->clave)){
		 	$numcli =  str_pad($clave->clave,5, " ", STR_PAD_LEFT);
		}else{
			$numcli = SAIT_UTILS::SAIT_getClientebyemail($current_user->user_email);
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
		$preciopub = $api_response["result"]["preciopub"];
		$pjedesc = $api_response["result"]["pjedesc"];
		
        $discounted_price = $preciopub * (1 - ($pjedesc / 100));

        // Establecer el nuevo precio en el producto
        $product->set_price($discounted_price);
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