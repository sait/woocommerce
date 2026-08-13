# Extensibilidad De SAIT WooCommerce

## Personalización De MODART

El procesamiento de artículos expone estos filtros para complementos de
clientes. Los valores predeterminados conservan el comportamiento del núcleo:

- `sait_woocommerce_modart_existing_sku_mode`: recibe
  `link_and_sync`, el producto encontrado, el SKU y el XML. Puede devolver
  `ignore` para no relacionar ni modificar un producto que existe por SKU pero
  no tiene mapeo en `sait_claves`.
- `sait_woocommerce_modart_sync_category`: recibe la decisión configurada, el
  SKU y el XML. `false` conserva las categorías actuales y crea productos sin
  categoría.
- `sait_woocommerce_modart_sync_model`: recibe la decisión configurada, el SKU
  y el XML. `false` conserva la descripción corta y no escribe `Modelo: ...`.

El complemento Fysson utiliza estos tres contratos; no reemplaza la clase que
procesa eventos.

## Personalización De Documentos SAIT

Los payloads se pueden modificar con filtros de WordPress despues de construir
el documento y aplicar la personalizacion legacy, pero antes del POST a SAIT.

## Filtros Disponibles

- `sait_woocommerce_order_payload`: recibe el pedido y la orden WooCommerce.
- `sait_woocommerce_quote_payload`: recibe la cotizacion y la orden WooCommerce.
- `sait_woocommerce_document_payload`: recibe cualquier documento, la orden y
  el tipo `P` o `Q`. Se ejecuta despues del filtro especifico.

El primer argumento debe devolverse como objeto. Los filtros no deben enviar el
documento ni guardar credenciales; el servicio del plugin conserva esa
responsabilidad.

```php
add_filter(
	'sait_woocommerce_order_payload',
	function ($document, $order) {
		$observations = isset($document->obs) ? $document->obs : '';
		$document->obs = trim($observations . ' REFERENCIA ' . $order->get_order_number());

		return $document;
	},
	10,
	2
);
```

La opcion `SAITNube_FuncionPersonalizadaPedido_enabled` y
`SAIT_PERSONALIZADO::SAIT_FuncionPersonalizaPostPedido()` se mantienen por
compatibilidad. Para codigo nuevo se recomiendan estos filtros porque no exige
editar archivos del plugin.

El adaptador está marcado como obsoleto y no se retirará antes de la versión
3.0.0. Un plugin complementario que reemplaza completamente esa lógica debe
desactivarlo sin cambiar la opción histórica:

```php
add_filter('sait_woocommerce_legacy_customizer_enabled', '__return_false');
```

Este filtro recibe también la orden y el tipo de documento (`P` o `Q`). No se
debe usar para modificar el payload; para eso permanecen los tres filtros
anteriores.
