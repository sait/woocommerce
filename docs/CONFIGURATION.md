# Configuracion Del Plugin

Las opciones se guardan en WordPress bajo la opcion `opciones_sait`.

El servicio `SAIT_WOOCOMMERCE_Settings` centraliza su lectura sin cambiar los
nombres historicos. Las banderas ausentes se interpretan como desactivadas y
las listas de almacenes se normalizan sin vacios ni duplicados.
Los modulos del plugin acceden a esta opcion mediante el mismo servicio.

La pagina de administracion esta en:

`Ajustes -> Configuracion SAIT`

## Opciones Principales

| Opcion | Uso observado |
| --- | --- |
| `SAITNube_APIKey` | Header `X-sait-api-key` para llamadas a SAITNube. |
| `SAITNube_URL` | URL base de SAITNube. Se concatena con rutas como `/api/v3/pedidos`. |
| `SAITNube_AccessToken` | Token esperado en el header entrante `x-AccessToken` para eventos SAIT. |
| `SAITNube_TipoDoc` | `P` envia pedidos; otros valores envian cotizaciones. |
| `SAITNube_CategoriaFuente` | Clasificacion SAIT usada como categoria WooCommerce: `linea`, `familia`, `categoria` o `departamento`. El valor predeterminado es `linea`. |
| `SAITNube_NumAlm` | Almacen base para existencias y pedidos. |
| `SAITNube_TipoCambio` | Tipo de cambio guardado por evento `ACTTC`; campo readonly en admin. |
| `SAITNube_PrecioLista` | Lista de precio SAIT alternativa para actualizar precio WooCommerce. |

### Fuente de categoria de productos

Los nombres del atributo de `MODART` y la clave del evento que crea el mapeo
no siguen el mismo patron en todos los casos:

| Valor configurado | Atributo en `MODART` | Tabla en `sait_claves` | Clave del evento de catalogo |
| --- | --- | --- | --- |
| `linea` | `linea` | `lineas` | `MODLINEA.keys.numlin` |
| `familia` | `familia` | `familia` | `MODFAMILIA.keys.numfam` |
| `categoria` | `categoria` | `catego` | `MODCATEGO.keys.numcat` |
| `departamento` | `numdep` | `deptos` | `MODDEPTO.keys.valdep` |

Si una instalacion actualizada no tiene la opcion, se usa `linea` sin necesidad
de modificar los datos guardados. Si el atributo seleccionado viene vacio o
todavia no existe su mapeo, `MODART` conserva las categorias actuales del
producto.

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
| `SAITNube_FuncionPersonalizadaPedido_enabled` | Activa el adaptador heredado `SAIT_PERSONALIZADO`; para código nuevo se usan plugins complementarios. |

El comportamiento esperado es que estas opciones se apliquen cuando estan en `1`:

```php
if ($Obs_activo) {
    $pedido->obs = trim($order->get_customer_note());
}
```

## Herramientas Administrativas

La pagina `Ajustes -> Configuracion SAIT` tambien muestra `Sincronizacion de articulos`.

- `Sincronizar articulo por SKU`: consulta un articulo puntual en SAITNube con `/api/v3/articulos/{sku}` y actualiza su precio regular/existencia en WooCommerce.
- `Sincronizar todos los articulos`: programa un proceso por lotes para articulos `statusweb=1`.
- Accion `Sincronizar SAIT` en el listado de productos: sincroniza el producto de esa fila usando su SKU.

La accion del listado muestra un aviso al regresar: precio/existencia actualizados, sin cambios, producto no encontrado, falta de SKU, error HTTP, JSON invalido o existencia no encontrada para el almacen configurado.

El estado del proceso masivo se guarda en la opcion `sait_art_sync_status`.

La pantalla muestra avance como `N de X articulos estimados`; `X` se calcula con productos WooCommerce que tienen SKU, por lo que funciona como referencia operativa del avance local.
