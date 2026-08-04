# SAIT WooCommerce - Papelía

Plugin complementario para reglas que pertenecen únicamente a Papelía. Requiere
WooCommerce y `SAIT WooCommerce`; no reemplaza archivos del plugin principal.

Incluye:

- personalización de `otrosdatos` y `obs` en pedidos y cotizaciones mediante
  los filtros públicos del núcleo;
- sustitución automática del adaptador heredado para evitar una doble
  personalización;
- selección de sucursal para la tarifa `local_pickup:4`;
- persistencia de sucursal y faltantes mediante metadatos compatibles con HPOS;
- validación AJAX de existencias por sucursal con nonce;
- stock total remoto y límites al agregar o actualizar el carrito;
- información de sucursal en administración y correo al administrador.

Los almacenes disponibles se toman de `SAITNube_ExistAlm`. Por compatibilidad
con la referencia de Papelía, el almacén `4` no aparece y la recogida usa la
instancia `local_pickup:4`. Ambos valores pueden cambiarse sin editar el plugin:

```php
add_filter('sait_papelia_pickup_shipping_method', function () {
	return 'local_pickup:7';
});

add_filter('sait_papelia_excluded_pickup_warehouses', function () {
	return array('4', '9');
});
```

El complemento está diseñado para el checkout clásico. No desactiva globalmente
las comprobaciones de stock de WooCommerce; cuando una sucursal no tiene stock
suficiente muestra una advertencia y conserva la decisión en el pedido.
