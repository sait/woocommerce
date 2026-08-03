# Contratos Base De SAIT WooCommerce 1.2.3

Este documento congela la superficie observable del plugin antes de la
refactorizacion. Describe el comportamiento actual; no implica que todas las
decisiones sean correctas o seguras.

## Punto De Entrada Y Ciclo De Vida

- Archivo principal: `sait-woocommerce/SAIT_WOOCOMMERCE.php`.
- Nombre del plugin: `SAIT WooCommerce`.
- Version declarada: `1.2.3`.
- Activacion: `activate_SAIT_WOOCOMMERCE()` crea o actualiza la tabla de
  mapeos mediante `dbDelta()`.
- No hay hook de desactivacion.
- No hay rutina de desinstalacion.
- No hay version de esquema persistida.

Constantes publicas actuales:

| Constante | Valor predeterminado | Uso |
| --- | --- | --- |
| `SAIT_NUBE_NUMALM` | `1` | Almacen cuando no hay opcion configurada. |
| `SAIT_SERIE` | `WO` | Prefijo de `numdoc` enviado a SAIT. |

## Persistencia

### Tabla `sait_claves`

Nombre fisico: `{$wpdb->prefix}sait_claves`.

| Columna | Tipo actual | Restricciones |
| --- | --- | --- |
| `id` | `INT` | `PRIMARY KEY`, `AUTO_INCREMENT`. |
| `tabla` | `VARCHAR(20)` | Permite `NULL`. |
| `clave` | `VARCHAR(20)` | Permite `NULL`. |
| `wcid` | `INT(12)` | Permite `NULL`. |

No existen indices ni restricciones unicas para `tabla`, `clave` o `wcid`.
Los valores logicos conocidos de `tabla` incluyen `arts`, `clientes`,
`familia`, `deptos`, `lineas` y `catego`.

### Opciones

Configuracion principal: `opciones_sait`.

| Clave | Contrato actual |
| --- | --- |
| `SAITNube_URL` | URL base de SAITNube. |
| `SAITNube_APIKey` | Header saliente `X-sait-api-key`. |
| `SAITNube_AccessToken` | Token entrante `x-AccessToken`. |
| `SAITNube_TipoDoc` | `P` crea pedido; otro valor crea cotizacion. |
| `SAITNube_NumAlm` | Almacen base. |
| `SAITNube_PrecioLista` | Numero de lista SAIT. |
| `SAITNube_TipoCambio` | Tipo de cambio actualizado por `ACTTC`. |
| `SAITNube_Sucursal_enabled` | Selector frontend de sucursal. |
| `SAITNube_ExistAlm_enabled` | Suma de multiples almacenes. |
| `SAITNube_ExistAlm` | Almacenes separados por coma. |
| `SAITNube_OcultarSinPrecio_enabled` | Filtra productos con precio cero. |
| `SAITNube_Promo_enabled` | Recalcula precios del carrito. |
| `SAITNube_PromoGlobal_enabled` | Cambia precio mostrado en tienda. |
| `SAITNube_MinimoCarrito_Enabled` | Activa monto minimo. |
| `SAITNube_MinimoCarrito` | Monto minimo configurado. |
| `SAITNube_PedidoObs_enabled` | Agrega `obs` al documento. |
| `SAITNube_PedidoDirenvio_enabled` | Agrega `direnvio`. |
| `SAITNube_FuncionPersonalizadaPedido_enabled` | Modifica payload con clase personalizada. |

Estado de sincronizacion masiva: opcion no autoload
`sait_art_sync_status`.

### Metadatos De Pedidos

Idempotencia automatica:

- `_sait_envio_disparado`
- `_sait_envio_disparado_at`
- `_sait_envio_formapago`
- `_sait_envio_tipodoc`

Ultimo reenvio con respuesta:

- `_sait_ultimo_envio_estado`
- `_sait_ultimo_status_code`
- `_sait_ultimo_envio_at`
- `_sait_ultimo_envio_formapago`
- `_sait_ultimo_envio_tipodoc`
- `_sait_ultimo_envio_modo`
- `_sait_ultimo_error`

### Metadatos De Productos

- `_sait_art_sync_at`
- `_sait_art_sync_source`
- `_sait_art_sync_status`
- `_sait_precio_anterior`
- `_sait_precio_sait`
- `_sait_existencia_sync_at`
- `_sait_existencia_sync_source`
- `_sait_existencia_sync_status`
- `_sait_existencia_anterior`
- `_sait_existencia_sait`

### Metadato De Usuario

- `sucursal_seleccionada`

Para visitantes no autenticados el ID de usuario es `0`; la persistencia de la
sucursal no queda aislada por visitante.

## Superficie REST

Namespace: `saitplugin/v1`.

| Metodo | Ruta | Autorizacion actual | Resultado principal |
| --- | --- | --- | --- |
| `GET` | `/hello` | Publica. | Texto de prueba. |
| `POST` | `/saitevents` | Ruta publica; token validado dentro del callback. | Procesa XML SAIT. |
| `POST` | `/reenviar-pedido-sait/{idpedido}` | Publica. | Reenvia pedido/cotizacion. |
| `GET` | `/testpedido/{idpedido}` | Publica. | Alias legacy de reenvio. |

Las cuatro rutas usan `permission_callback => __return_true`. Los endpoints de
reenvio no comprueban usuario, capacidad, nonce ni token. Esto se conserva aqui
como linea base y esta identificado para una correccion de seguridad separada.

### Contrato Del Webhook

- Header: `x-AccessToken`.
- Body: XML procesado con `simplexml_load_string()`.
- Token invalido: HTTP `401`, mensaje `Bad token`.
- XML invalido: HTTP `500`, cuerpo con errores de libxml serializados.
- Un evento recibido pero no aplicable normalmente responde HTTP `200` con un
  mensaje operativo para evitar reintentos de entrega de SAIT.

Eventos reconocidos:

- `MODART`
- `ACTEXISGBL`
- `ACTPRECIO`
- `MODFAMILIA`
- `MODDEPTO`
- `MODLINEA`
- `MODCATEGO`
- `ACTEXIST`
- `ACTTC`
- `MODCLI`

Un tipo desconocido responde HTTP `200` con `OK`.

## Hooks Y Acciones Publicas

### Pedidos

| Hook | Callback | Contrato |
| --- | --- | --- |
| `woocommerce_payment_complete` | `sendOrderSAIT_payment` | Envia con `formapago = 1`. |
| `woocommerce_thankyou` | `sendOrderSAIT_thankyou` | Envia con `formapago = 2`. |
| `woocommerce_admin_order_data_after_order_details` | Boton admin | Permite reenvio manual. |
| `admin_post_sait_reenviar_pedido_admin` | Handler admin | Reenvio con capacidad y nonce. |

### Sincronizacion De Articulos

- `admin_post_sait_sync_articulo_sku`
- `admin_post_sait_sync_articulo_product`
- `admin_post_sait_sync_articulos_start`
- `sait_sync_articulos_batch`
- Filtro `post_row_actions`

El lote usa `200` articulos, tiene limite de `100` lotes y prefiere
`as_enqueue_async_action()`. Si Action Scheduler no esta disponible programa
un evento unico de WP-Cron diez segundos despues.

### Frontend

- `woocommerce_before_calculate_totals`
- Filtro `woocommerce_cart_item_price`
- `woocommerce_checkout_process`
- `woocommerce_before_cart`
- `woocommerce_single_product_summary`
- `woocommerce_product_query`
- Filtro `woocommerce_get_price_html`
- Filtro `wp_nav_menu_items`
- `wp_footer`
- `wp_ajax_guardar_sucursal`
- `wp_ajax_nopriv_guardar_sucursal`

## API Saliente De SAITNube

Todas las rutas usan la URL de `SAITNube_URL` y el header
`X-sait-api-key`.

Lecturas conocidas:

- `GET /api/v3/articulos/{numart}`
- `GET /api/v3/articulos?...`
- `GET /api/v3/existencias/{numart}`
- `GET /api/v3/clientes?emailtw={email}`
- `GET /api/v3/clienteseventuales?email={email}`
- `GET /api/v3/almacenes`
- `GET /api/v3/calcularprecios?...`

Escrituras conocidas:

- `POST /api/v3/pedidos`
- `POST /api/v3/cotizaciones`

El GET tiene timeout de cinco segundos y un segundo intento despues de 500 ms.
El POST tiene timeout de 45 segundos, pero los envios automaticos usan
`blocking = false`. Tanto GET como POST usan actualmente `sslverify = false`.

## Contrato De Pedidos Y Cotizaciones

Campos base:

- `numdoc`: `SAIT_SERIE` seguido del ID WooCommerce.
- `numcli`
- `numcliev`
- `numalm`
- `formapago`
- `divisa = P`
- `tc = 1`
- `items`
- `fentrega`
- `hentrega`

Campos opcionales:

- `obs`
- `direnvio`
- `clievent`

Cada item incluye `cant`, `numart`, `unidad`, `preciopub`, `precio` y
`pjedesc1`. La unidad se consulta en SAITNube con hasta tres intentos.

### Resolucion Actual Del Cliente

1. Busca un mapeo `clientes` por ID de usuario WooCommerce.
2. Si no existe, busca un cliente normal en `/api/v3/clientes` por correo.
3. Si no lo encuentra, construye un objeto nuevo `clievent`.

Existe `SAIT_getClienteEventualbyemail()`, pero el flujo actual de pedidos y
cotizaciones no la invoca. Por ello un cliente eventual existente no se
reutiliza actualmente. La recuperacion de esta funcionalidad debe ser un
commit funcional separado, no parte de una extraccion mecanica.

### Entrega Automatica Y Manual

- El envio automatico marca `_sait_envio_disparado = yes` antes del POST.
- La marca indica que se disparo el intento, no que SAIT lo acepto.
- El envio automatico no espera respuesta.
- El reenvio manual espera respuesta y considera HTTP `201` como `enviado`.
- Error de transporte, status `0` o HTTP `5xx` produce
  `reintento_requerido`.
- Otros estados HTTP producen `error`.

## Contratos Que No Deben Cambiar Sin Decision Explicita

- Namespace y rutas REST.
- Nombres de opciones y metadatos.
- Nombre y contenido de `sait_claves`.
- Prefijo de documento `WO`.
- Semantica de `SAITNube_TipoDoc`.
- Formas de pago asociadas a los hooks WooCommerce.
- Respuesta HTTP `200` para eventos recibidos pero no aplicables.
- Payload de pedido/cotizacion y reglas de cliente.
- Prioridad de lista de precios, impuestos y tipo de cambio.
- Seleccion de almacenes y suma multi-almacen.
