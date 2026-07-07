# Configuracion Del Plugin

Las opciones se guardan en WordPress bajo la opcion `opciones_sait`.

La pagina de administracion esta en:

`Ajustes -> Configuracion SAIT`

## Opciones Principales

| Opcion | Uso observado |
| --- | --- |
| `SAITNube_APIKey` | Header `X-sait-api-key` para llamadas a SAITNube. |
| `SAITNube_URL` | URL base de SAITNube. Se concatena con rutas como `/api/v3/pedidos`. |
| `SAITNube_AccessToken` | Token esperado en el header entrante `x-AccessToken` para eventos SAIT. |
| `SAITNube_TipoDoc` | `P` envia pedidos; otros valores envian cotizaciones. |
| `SAITNube_NumAlm` | Almacen base para existencias y pedidos. |
| `SAITNube_TipoCambio` | Tipo de cambio guardado por evento `ACTTC`; campo readonly en admin. |
| `SAITNube_PrecioLista` | Lista de precio SAIT alternativa para actualizar precio WooCommerce. |

## Opciones De Frontend Y Stock

| Opcion | Uso observado |
| --- | --- |
| `SAITNube_Sucursal_enabled` | Activa modal y selector de sucursal. |
| `SAITNube_ExistAlm_enabled` | Activa vista/suma de existencias por multiples almacenes. |
| `SAITNube_ExistAlm` | Lista separada por comas de almacenes a mostrar/sumar. |
| `SAITNube_OcultarSinPrecio_enabled` | Activa filtro para ocultar productos con precio `0` en catalogo. |

## Opciones De Carrito Y Promociones

| Opcion | Uso observado |
| --- | --- |
| `SAITNube_Promo_enabled` | Activa recalculo de precios en carrito. |
| `SAITNube_PromoGlobal_enabled` | Activa precio promocional visible en catalogo/producto. |
| `SAITNube_MinimoCarrito_Enabled` | Activa monto minimo de carrito. |
| `SAITNube_MinimoCarrito` | Importe minimo requerido para checkout. |

## Opciones De Pedido

| Opcion | Uso observado |
| --- | --- |
| `SAITNube_PedidoObs_enabled` | Controla el comportamiento de observaciones del pedido. |
| `SAITNube_PedidoDirenvio_enabled` | Controla el comportamiento de direccion de envio en el pedido. |
| `SAITNube_FuncionPersonalizadaPedido_enabled` | Controla el comportamiento de la funcion personalizada de pedido. |

El comportamiento esperado es que estas opciones se apliquen cuando estan en `1`:

```php
if ($Obs_activo) {
    $pedido->obs = trim($order->get_customer_note());
}
```
