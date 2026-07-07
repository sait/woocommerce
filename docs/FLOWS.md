# Flujos Principales

## Activacion Del Plugin

1. WordPress carga `sait-woocommerce/SAIT_WOOCOMMERCE.php`.
2. Se registra `activate_SAIT_WOOCOMMERCE()` con `register_activation_hook`.
3. En activacion se ejecuta `SAIT_WOOCOMMERCE_Activator::SAIT_create_db()`.
4. Se crea o actualiza la tabla `{prefix}sait_claves`.

La tabla `sait_claves` guarda relaciones entre claves SAIT y IDs de WooCommerce:

- `tabla`: tipo de entidad (`arts`, `clientes`, `lineas`, etc.).
- `clave`: identificador SAIT.
- `wcid`: ID en WooCommerce/WordPress.

## Endpoints REST

El plugin registra tres rutas bajo `saitplugin/v1`:

- `GET /hello`: prueba simple, regresa `hello world!`.
- `POST /saitevents`: recibe XML de eventos SAIT.
- `POST /reenviar-pedido-sait/{idpedido}`: reenvia el pedido/cotizacion usando el ID de orden indicado. Es la ruta recomendada para recuperar envios cuando SAITNube/API no estuvo disponible.
- `GET /testpedido/{idpedido}`: alias historico para compatibilidad.

Todas usan `permission_callback => __return_true`; la proteccion real de `/saitevents` depende del header `x-AccessToken`.

## Evento `MODART`

Archivo: `includes/SAIT_WOOCOMMERCE-process-events.php`

1. Lee `numart` desde `keys`.
2. Lee campos como `codigo`, `desc`, `linea`, `modelo`, `statusweb`, `obs`.
3. Si `statusweb` viene vacio, responde `statusweb null`.
4. Busca categoria por tabla `lineas` en `sait_claves`.
5. Busca producto mapeado en `sait_claves` con tabla `arts`.
6. Si `statusweb` es `0`, manda el producto a papelera si existe.
7. Si ya existe mapeo:
   - Restaura de papelera.
   - Actualiza nombre, SKU, GTIN/codigo global, categoria, descripcion corta y descripcion.
   - Si stock actual es cero o vacio, consulta existencia SAIT y actualiza stock.
8. Si no existe mapeo:
   - Crea `WC_Product_Simple`.
   - Lo deja en `draft`.
   - Activa manejo de stock.
   - Asigna precio regular `0`.
   - Guarda relacion en `sait_claves`.

## Evento `ACTPRECIO`

1. Toma `numart`.
2. Ignora eventos que no traen precio normal positivo en `preciopub` o `precio1` a `precio5`.
3. Busca producto por `sait_claves` o por SKU.
4. Si `preciopub` viene en XML y es positivo, actualiza precio regular.
5. Si `SAITNube_PrecioLista` o `SAITNube_TipoCambio` estan configurados, consulta `/api/v3/articulos/{numart}`.
6. Puede recalcular con impuestos desde la lista configurada.
7. Si el articulo esta en dolares (`divisa === "D"`) y hay tipo de cambio, multiplica por TC.

## Evento `ACTEXIST`

1. Lee almacen base `SAITNube_NumAlm`.
2. Revisa si esta activo `SAITNube_ExistAlm_enabled`.
3. Si no esta activo multi-almacen, solo procesa si el almacen del evento coincide con el configurado.
4. Busca producto por mapeo o SKU.
5. Si multi-almacen esta activo, consulta SAITNube y suma existencias de `SAITNube_ExistAlm`.
6. Si multi-almacen no esta activo, usa la existencia del evento.

Nota: en la rama multi-almacen se usa `$sku`, pero no se ve definido en la funcion. Conviene revisar antes de depender de esta ruta.

## Evento `ACTTC`

1. Lee el nuevo tipo de cambio desde XML.
2. Si no cambio, responde `same TC`.
3. Guarda `SAITNube_TipoCambio`.
4. Consulta articulos en dolares con `statusweb=1`.
5. Actualiza precio regular en WooCommerce multiplicando `preciopub * nuevo TC`.

## Eventos De Categorias

Los eventos `MODFAMILIA`, `MODDEPTO`, `MODLINEA` y `MODCATEGO` llaman a `MODCATEGORIAWC()`.

La funcion:

- Crea una categoria `product_cat` si no existe mapeo.
- Actualiza nombre si ya existe.
- Si el term mapeado ya no existe, intenta encontrarlo por nombre o recrearlo.
- Guarda relaciones en `sait_claves`.

## Evento `MODCLI`

1. Lee `emailtw`.
2. Si no hay `emailtw`, no crea cliente web.
3. Si el cliente SAIT ya esta ligado a usuario WooCommerce:
   - Si cambio el correo, valida que no pertenezca a otro usuario.
   - Actualiza email.
   - Dispara correo de nueva cuenta.
4. Si no hay liga pero ya existe usuario con ese correo:
   - Inserta relacion en `sait_claves`.
5. Si no existe usuario:
   - Activa generacion automatica de password y username.
   - Crea cliente con `wc_create_new_customer`.
   - Guarda relacion en `sait_claves`.

## Orden WooCommerce -> SAIT

Archivo: `includes/SAIT_WOOCOMMERCE-orders.php`

1. `SAIT_sendOrder($id_pedido, $formapago)` carga la orden.
2. Si la orden ya tiene `_sait_envio_disparado = yes`, omite el envio automatico para evitar duplicados.
3. Lee `SAITNube_TipoDoc`.
4. Marca la orden con metadata de envio automatico disparado:
   - `_sait_envio_disparado`
   - `_sait_envio_disparado_at`
   - `_sait_envio_formapago`
   - `_sait_envio_tipodoc`
5. Genera pedido o cotizacion.
6. Para cada item:
   - Obtiene SKU como `numart`.
   - Consulta unidad en `/api/v3/articulos/{sku}`.
   - Usa precio regular como `preciopub` y `precio`.
   - Calcula descuento con total del item.
7. Busca cliente SAIT por mapeo o por email.
8. Si no hay cliente, agrega objeto de cliente eventual.
9. Aplica funcion personalizada si la bandera esta activa.
10. Envia a SAITNube.

La metadata indica que WooCommerce disparo el envio automatico; no confirma que SAITNube lo haya recibido, porque el POST se hace sin esperar respuesta.

El endpoint manual `reenviar-pedido-sait/{idpedido}` no usa este bloqueo, para permitir recuperacion cuando SAITNube/API no estuvo disponible. Ese reenvio espera respuesta de SAITNube y guarda metadata del ultimo resultado:

- `_sait_ultimo_envio_estado`: `enviado`, `error` o `reintento_requerido`.
- `_sait_ultimo_status_code`: status HTTP recibido. `201` significa aceptado por SAIT.
- `_sait_ultimo_envio_at`
- `_sait_ultimo_envio_formapago`
- `_sait_ultimo_envio_tipodoc`
- `_sait_ultimo_envio_modo`
- `_sait_ultimo_error`, solo cuando el resultado no fue exitoso.

## Sucursales En Frontend

Si `SAITNube_Sucursal_enabled === "1"`:

- Se agregan CSS/JS del modal.
- Se agrega boton en menu `primary`.
- Se imprime modal en `wp_footer`.
- La seleccion se guarda en user meta `sucursal_seleccionada` por AJAX.

El AJAX tambien esta habilitado para usuarios no logueados, pero usa `get_current_user_id()`. En visitantes anonimos esto normalmente sera `0`, por lo que puede no persistir por usuario real.

## Precios Promocionales Y Carrito

`SAIT_WOOCOMMERCE-cart.php` recalcula precios en el hook `woocommerce_before_calculate_totals` si `SAITNube_Promo_enabled === "1"`.

`SAIT_UTILS.php` tambien cambia el HTML de precios en catalogo/producto si `SAITNube_PromoGlobal_enabled === "1"`.

Ambas rutas consultan SAITNube para obtener unidad y calcular precios.
