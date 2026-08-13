<?php

// Adaptadores globales para temas o personalizaciones que usen los nombres históricos.

function agregar_boton_al_menu($items, $args)
{
	return SAIT_WOOCOMMERCE()->branch_selector()->add_menu_item($items, $args);
}

function agregar_modal_sucursal()
{
	SAIT_WOOCOMMERCE()->branch_selector()->render_modal();
}

function guardar_sucursal()
{
	SAIT_WOOCOMMERCE()->branch_selector()->save_branch();
}

function mostrar_tabla_almacenes()
{
	SAIT_WOOCOMMERCE()->stock_display()->render_stock_table();
}

function ocultar_productos_sin_precio($query)
{
	SAIT_WOOCOMMERCE()->stock_display()->hide_products_without_price($query);
}

function sait_precio_promocional_en_producto($price_html, $product)
{
	return SAIT_WOOCOMMERCE()->promotions()->filter_price_html($price_html, $product);
}

function calcularpreciosCarrito($cart)
{
	SAIT_WOOCOMMERCE()->promotions()->apply_cart_prices($cart);
}

function display_discounted_price_in_cart($price, $cart_item, $cart_item_key)
{
	return SAIT_WOOCOMMERCE()->promotions()->display_cart_item_price($price, $cart_item, $cart_item_key);
}

function sait_minimo_total_carrito()
{
	SAIT_WOOCOMMERCE()->cart_minimum()->validate();
}

function sait_bloquear_botones_checkout()
{
	SAIT_WOOCOMMERCE()->cart_minimum()->render_button_guard();
}
