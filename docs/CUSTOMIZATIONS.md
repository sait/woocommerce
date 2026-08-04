# Personalizaciones De Clientes

Las reglas exclusivas de una empresa no deben reemplazar archivos de `SAIT
WooCommerce`. Cada empresa puede tener un plugin complementario versionado y
empacado por separado bajo `personalizados/`.

## Contratos Del Plugin Principal

| Filtro | Parámetros | Uso |
| --- | --- | --- |
| `sait_woocommerce_order_payload` | `object $document`, `WC_Order $order` | Modificar únicamente pedidos. |
| `sait_woocommerce_quote_payload` | `object $document`, `WC_Order $order` | Modificar únicamente cotizaciones. |
| `sait_woocommerce_document_payload` | `object $document`, `WC_Order $order`, `string $type` | Modificar ambos documentos; `P` es pedido y `Q` cotización. |

Los filtros se ejecutan después del constructor y del adaptador heredado, pero
antes del POST. Deben devolver el mismo objeto o un reemplazo serializable y no
deben enviar el documento por su cuenta.

El acceso compartido a configuración y API se realiza mediante
`SAIT_WOOCOMMERCE()->settings()` y `SAIT_WOOCOMMERCE()->sait_client()`. Así las
pruebas pueden inyectar el mismo cliente simulado que utiliza el núcleo.

## Inventario Heredado Del Núcleo

`sait-woocommerce/includes/SAIT_WOOCOMMERCE-personalizado.php` contiene la
clase histórica `SAIT_PERSONALIZADO` y registra un JavaScript vacío. La opción
`SAITNube_FuncionPersonalizadaPedido_enabled` decide si el servicio llama su
método para pedidos y cotizaciones.

Este adaptador se conserva durante una versión de transición para no romper
instalaciones que todavía reemplazan ese archivo. No debe recibir reglas de
clientes nuevas. Antes de retirarlo hay que localizar instalaciones que tengan
la opción activa y entregarles su plugin complementario.

## Referencia Papelía

El directorio local `papelia/` se revisó como referencia y no forma parte del
control de versiones. Su archivo PHP reúne estas reglas:

- agrega `otrosdatos` y `obs` al pedido SAIT;
- muestra sucursales para `local_pickup:4` y excluye el almacén `4`;
- guarda sucursal y faltantes en el pedido;
- ajusta campos del checkout clásico y correos administrativos;
- consulta existencias por AJAX y permite confirmar faltantes;
- reemplaza el stock de WooCommerce con el total remoto;
- limita cantidades de carrito contra SAIT.

También contiene riesgos que no se trasladaron: funciones globales genéricas,
AJAX sin nonce, entrada `POST` sin `wp_unslash()`, un nombre de JavaScript que no
coincide con el archivo y filtros que fuerzan globalmente existencias y
backorders. Estos últimos podrían permitir compras sin las validaciones de
otros plugins.

## Complemento Papelía

La implementación mantenida está en
`personalizados/sait-woocommerce-papelia/`. Tiene un bootstrap único y separa
payload, recogida y stock. Requiere WooCommerce y SAIT WooCommerce activos.

Conserva los metadatos históricos:

- `_sait_sucursal`;
- `_sait_sucursal_nombre`;
- `_sait_pedido_sin_existencias`.

La tarifa predeterminada y almacenes excluidos se pueden cambiar con
`sait_papelia_pickup_shipping_method` y
`sait_papelia_excluded_pickup_warehouses`. El complemento soporta actualmente
el checkout clásico; Checkout Blocks queda pendiente de una implementación y
prueba separadas.

Para migrar una instalación Papelía:

1. Instalar y activar `SAIT WooCommerce - Papelía` junto al plugin principal.
2. Confirmar la instancia real de recogida y los almacenes configurados.
3. Desactivar `SAITNube_FuncionPersonalizadaPedido_enabled` para no ejecutar el
   adaptador heredado además del filtro nuevo.
4. Probar pedido con entrega, recogida con stock y recogida con faltantes.
5. Empacar el núcleo y el complemento en ZIP separados.
