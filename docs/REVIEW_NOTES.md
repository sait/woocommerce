# Notas De Revision

Estas notas son hallazgos iniciales al leer el codigo. No se hicieron cambios funcionales.

## Riesgos Altos

### SQL construido por concatenacion

Archivo: `includes/SAIT_UTILS.php`

`SAIT_getClaves()` arma SQL concatenando `$tabla`, `$clave` y `$wcid`.

Riesgo:

- Inyeccion SQL si algun valor llega contaminado desde XML, request o datos externos.

Siguiente paso sugerido:

- Usar `$wpdb->prepare`.
- Manejar `null` explicitamente para no producir condiciones ambiguas.

### Endpoint de reenvio expuesto sin autenticacion

Archivo: `sait-woocommerce/SAIT_WOOCOMMERCE.php`

Rutas:

- `POST /wp-json/saitplugin/v1/reenviar-pedido-sait/{idpedido}`.
- `GET /wp-json/saitplugin/v1/testpedido/{idpedido}` como alias historico de compatibilidad.

Ambas tienen `permission_callback => __return_true`.

Riesgo:

- Cualquier visitante podria disparar intento de reenvio de pedido/cotizacion.

Siguiente paso sugerido:

- Remover en produccion o restringir a administradores.

### REST abierto con seguridad solo por token propio

Archivo: `sait-woocommerce/SAIT_WOOCOMMERCE.php`

`/saitevents` permite acceso anonimo y valida `x-AccessToken`.

Esto puede ser aceptable para webhook, pero conviene:

- Validar que el token exista y no este vacio.
- Responder 401 si el plugin no esta configurado.
- Agregar logging moderado para intentos fallidos.

## Bugs Probables

### Opciones de pedido con comportamiento corregido

Archivos:

- `includes/SAIT_WOOCOMMERCE-options.php`
- `includes/SAIT_WOOCOMMERCE-orders.php`

Las claves de configuracion ya coinciden con las que guarda la pantalla. Tambien se corrigio el comportamiento para que una opcion activa (`1`) aplique la accion que describe el admin.

Ejemplo:

```php
if ($Obs_activo) {
    $pedido->obs = trim($order->get_customer_note());
}
```

### Variable de articulo en multi-almacen corregida

Archivo: `includes/SAIT_WOOCOMMERCE-process-events.php`

En `ACTEXIST()`, la rama `SAITNube_ExistAlm_enabled` usaba:

```php
$cache_key = 'sait_stock_' . md5($sku ?? 'total');
$respuesta = SAIT_UTILS::SAIT_GetNube("/api/v3/existencias/" . trim($sku));
```

Pero `$sku` no se define antes en esa funcion.

Resultado probable antes de corregir:

- Consulta incorrecta o vacia.
- Cache key generica `total`.
- Warnings/notices segun configuracion.

Se corrigio usando `$numart`, tomado del XML una sola vez al inicio del loop.

### `$original_price` puede usarse antes de definirse

Archivo: `includes/SAIT_WOOCOMMERCE-cart.php`

En `calcularpreciosCarrito()`, si no se obtiene `unidad`, se llama:

```php
$product->set_price($original_price);
```

Pero `$original_price` se asigna despues.

### Doble enqueue con el mismo handle `modal-script`

Archivo: `sait-woocommerce/SAIT_WOOCOMMERCE.php`

`registrar_estilos_scripts()` encola dos scripts con el mismo handle `modal-script`, uno para `modal.js` y otro para `personalizado.js`.

Resultado probable:

- WordPress puede ignorar el segundo o reemplazar/mezclar datos de forma no obvia.
- `wp_localize_script('modal-script', ...)` queda asociado a un handle ambiguo.

### Hook de assets no visible en archivo principal

`registrar_estilos_scripts()` esta definido, pero en el fragmento revisado no aparece `add_action('wp_enqueue_scripts', 'registrar_estilos_scripts')`.

Si no se registra en otro lugar, el modal no cargara assets desde esa funcion.

## Riesgos Medios

### SSL desactivado en llamadas HTTP

Archivos:

- `includes/SAIT_UTILS.php`

`SAIT_GetNube()` y `SAIT_PostNube()` usan `sslverify => false`.

Riesgo:

- Menor seguridad en conexiones HTTPS.

Puede tener una razon operativa, pero deberia documentarse o convertirlo en opcion controlada.

### Envio de pedidos sin esperar respuesta

`SAIT_PostNube(..., false)` usa `blocking = false`.

Ventaja:

- Checkout no se bloquea esperando SAITNube.

Riesgo:

- WooCommerce no sabe si SAITNube acepto o rechazo el pedido.
- Dificulta reintentos y trazabilidad.

Siguiente paso sugerido:

- Guardar metadata en la orden con estado de envio.
- Agregar accion manual de reenvio.

### Falta de idempotencia clara en envio de ordenes

Los hooks `woocommerce_payment_complete` y `woocommerce_thankyou` pueden ejecutarse en escenarios cercanos para una misma orden, dependiendo del flujo de pago.

Riesgo:

- Envio duplicado a SAIT.

Siguiente paso sugerido:

- Guardar meta `_sait_enviado` o equivalente.
- Validar antes de enviar.

### Funciones globales mezcladas con utilidades

`SAIT_UTILS.php` mezcla:

- Cliente HTTP.
- Acceso DB.
- UI frontend.
- AJAX.
- Filtros de precio.

Esto hace mas dificil probar y razonar sobre efectos secundarios.

## Observaciones De Mantenimiento

- No se detecto suite de tests.
- No se detecto `composer.json`.
- Hay bloques grandes comentados en `MODART()` que parecen historicos.
- Hay comentarios con reglas de negocio utiles, pero faltan docblocks de entradas/salidas en funciones criticas.
- Algunas respuestas REST devuelven `200` para errores operativos como `ART NO EXISTE`; puede ser intencional para webhooks, pero conviene documentarlo.
- `SAIT_GetNube()` retorna arrays, `null` o podria recibir `WP_Error`; las llamadas no siempre normalizan ese contrato.

## Checklist Por Prioridad

### Prioridad Alta

- [x] Corregir nombres de opciones inconsistentes entre admin y uso real:
  - `SAITNube_PedidoObs_enabled` vs `SAITNube_PedidoObs_Enabled`.
  - `SAITNube_PedidoDirenvio_enabled` vs `SAITNube_PedidoDirenvio_Enabled`.
  - `SAITNube_FuncionPersonalizadaPedido_enabled` vs `SAITNube_FuncionPersonalizadaPedido_Enabled`.
  - `SAITNube_OcultarSinPrecio_enabled` vs `SAITNube_OcultarSinPrecio`.
- [x] Confirmar si las banderas de pedido estan invertidas antes de corregirlas:
  - Se corrigio para que observaciones, direccion de envio y funcion personalizada se apliquen cuando la bandera esta activa.
- [x] Arreglar `$sku` no definido en `ACTEXIST()` cuando esta activo multi-almacen.
- [x] Arreglar `$original_price` usado antes de definirse en `calcularpreciosCarrito()`.
- [x] Renombrar endpoint operativo de reenvio a `POST /wp-json/saitplugin/v1/reenviar-pedido-sait/{idpedido}` y dejar `GET /testpedido/{idpedido}` como alias de compatibilidad.
- [ ] Cambiar `SAIT_UTILS::SAIT_getClaves()` para usar `$wpdb->prepare`.

### Prioridad Media

- [ ] Agregar idempotencia al envio de ordenes a SAIT:
  - Guardar metadata en la orden cuando ya se envio.
  - Evitar doble envio entre `woocommerce_payment_complete` y `woocommerce_thankyou`.
- [ ] Registrar resultado del envio a SAIT en metadata o logs consultables:
  - Pendiente.
  - Enviado.
  - Error.
  - Reintento requerido.
- [ ] Revisar `sslverify => false` en llamadas HTTP y decidir si debe ser configurable o corregirse.
- [ ] Separar el handle duplicado `modal-script` para `modal.js` y `personalizado.js`.
- [ ] Confirmar que `registrar_estilos_scripts()` este conectado a `wp_enqueue_scripts`.
- [ ] Normalizar el contrato de `SAIT_GetNube()`:
  - Definir si retorna array, `null` o `WP_Error`.
  - Ajustar llamadas que asumen respuestas distintas.

### Prioridad Baja

- [ ] Separar responsabilidades de `SAIT_UTILS.php`:
  - Cliente HTTP SAITNube.
  - Acceso a tabla `sait_claves`.
  - UI de sucursales.
  - Filtros de precios.
- [ ] Limpiar bloques historicos comentados en `MODART()`.
- [ ] Agregar docblocks a funciones criticas con entrada, salida y efectos secundarios.
- [ ] Documentar por que algunos errores operativos responden HTTP `200`.
- [ ] Crear fixtures XML para eventos principales:
  - `MODART`.
  - `ACTEXIST`.
  - `ACTPRECIO`.
  - `ACTTC`.
  - `MODCLI`.
- [ ] Crear una guia de pruebas manuales por flujo:
  - Webhook SAIT -> WooCommerce.
  - Pedido WooCommerce -> SAIT.
  - Selector de sucursal.
  - Promociones en carrito.
  - Monto minimo de checkout.
