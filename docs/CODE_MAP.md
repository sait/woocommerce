# Mapa De Codigo

## `sait-woocommerce/SAIT_WOOCOMMERCE.php`

Archivo principal del plugin.

Responsabilidades:

- Declara metadatos del plugin.
- Carga las clases de bootstrap y ciclo de vida.
- Define constantes por defecto:
  - `SAIT_NUBE_NUMALM = "1"`
  - `SAIT_SERIE = "WO"`
- Registra activacion.
- Conserva adaptadores globales para callbacks historicos.

Funciones:

- `activate_SAIT_WOOCOMMERCE()`: crea tabla de mapeos.
- `SAIT_helloworld()`: callback REST de prueba.
- `SAIT_procesEvents($request)`: valida token, parsea XML y delega procesamiento.
- `SAIT_reenviarPedido($request)`: reenvia una orden usando `idpedido` desde la ruta REST.

## `includes/rest/SAIT_WOOCOMMERCE-rest-controller.php`

Clase `SAIT_WOOCOMMERCE_REST_Controller`.

Responsabilidades:

- Registra las rutas del namespace historico `saitplugin/v1`.
- Conserva los metodos de 1.2.3; el webhook es publico y los reenvios requieren
  la capacidad `edit_shop_orders`.
- Valida el token y parsea el XML del webhook sin cambiar su contrato.
- Delega eventos al procesador y reenvios a la clase de pedidos.

Las funciones REST globales del archivo principal permanecen como adaptadores
de compatibilidad y delegan en este controlador.
- `sendOrderSAIT_payment($order_id)`: envia orden pagada con forma de pago `1`.
- `sendOrderSAIT_thankyou($order_id)`: envia orden no pagada con forma de pago `2`.
- `registrar_estilos_scripts()`: carga Font Awesome, CSS/JS del modal y nonce AJAX cuando esta activo selector de sucursal.

## `includes/SAIT_WOOCOMMERCE-plugin.php`

Clase `SAIT_WOOCOMMERCE_Plugin`.

- Carga dependencias compartidas y limita opciones/pedidos administrativos a
  contexto `is_admin()`.
- Registra rutas REST, hooks de pedidos y assets frontend.
- Mantiene una sola instancia del controlador REST.
- Mantiene instancias compartidas de configuracion y cliente HTTP, sustituible
  por un cliente falso en pruebas.
- Mantiene una instancia compartida del repositorio de mapeos.
- Mantiene una instancia compartida del logger saneado de WooCommerce.

## `includes/SAIT_WOOCOMMERCE-lifecycle.php`

Clase `SAIT_WOOCOMMERCE_Lifecycle`.

- Crea o actualiza la tabla mediante el activador existente.
- Guarda `sait_woocommerce_db_version = 1.0.0`.
- Ejecuta actualizaciones de esquema de forma idempotente.
- Conserva todos los datos al desactivar.

## `includes/SAIT_UTILS.php`

Clase `SAIT_UTILS` y varias funciones globales frontend.

Metodos principales:

- `SAIT_getClientebyemail($email)`: busca cliente SAIT por `emailtw`.
- `SAIT_getClienteEventualbyemail($email)`: adaptador legacy que usa `/clientes`
  y devuelve el `numcli` solamente cuando contiene `-`.
- `SAIT_GetNube($uri, $reintentar = true)`: GET a SAITNube con API key y un reintento opcional.
- `SAIT_getResult($response)`: extrae `result` de una respuesta `array|null` de `SAIT_GetNube()`.
- `SAIT_PostNube($uri, $bodyObject, $wait = false)`: POST JSON a SAITNube.
- `SAIT_getClaves($tabla, $clave, $wcid)`: consulta mapeo en `{prefix}sait_claves`.
- `SAIT_insertClaves($tabla, $clave, $wcid)`: inserta mapeo.
- `SAIT_deleteClaves($id)`: elimina mapeo.
- `SAIT_response($code, $message)`: crea `WP_REST_Response`.
- `SAIT_codigo_valido($codigo)`: valida codigos GTIN/UPC/EAN/ISBN por formato y longitud.
- `getExistSAIT($SKU)`: obtiene existencia desde SAITNube; puede sumar almacenes configurados.

## `includes/SAIT_WOOCOMMERCE-sait-client.php`

Interfaz `SAIT_WOOCOMMERCE_SaitClientInterface` y clase
`SAIT_WOOCOMMERCE_SaitClient`.

- Es el unico adaptador que llama a `wp_remote_get()` y `wp_remote_post()`.
- Centraliza URL, API key, headers, SSL compatible y timeouts.
- Normaliza GET exitoso, `result: null`, JSON invalido, `WP_Error` y estados
  HTTP no exitosos.
- Reintenta GET una vez por transporte o JSON invalido; no reintenta estados
  HTTP ni operaciones POST.
- Mantiene respuestas POST crudas para los contratos historicos de pedidos.

## `includes/SAIT_WOOCOMMERCE-mapping-repository.php`

Clase `SAIT_WOOCOMMERCE_MappingRepository`.

- Es el unico acceso del plugin a la tabla `sait_claves`.
- Separa busquedas por clave SAIT y por ID WooCommerce.
- Expone metodos para productos, clientes y categorias.
- Usa consultas preparadas y evita repetir una misma `tabla + clave`.
- Permite claves distintas con el mismo `wcid` y no agrega restricciones
  unicas antes de auditar los datos existentes.

Los metodos `SAIT_getClaves`, `SAIT_insertClaves` y `SAIT_deleteClaves` de
`SAIT_UTILS` permanecen como adaptadores de compatibilidad.

## `includes/SAIT_WOOCOMMERCE-logger.php`

Clase `SAIT_WOOCOMMERCE_Logger`.

- Escribe mediante `wc_get_logger()` con fuente `sait-woocommerce`.
- Acepta contexto operativo de evento, orden, SKU, intento, operacion, status,
  modo y tipo de documento.
- Descarta claves no permitidas para evitar API keys, tokens, correos, nombres,
  direcciones y payloads.
- Sustituye el volcado completo de partidas y los usos directos de
  `error_log()` observados.

Los nombres globales anteriores se conservan como adaptadores en
`SAIT_WOOCOMMERCE-frontend-compat.php`. Los hooks apuntan a estos módulos:

- `frontend/SAIT_WOOCOMMERCE-branch-selector.php`: menú, modal, AJAX y assets de sucursal.
- `frontend/SAIT_WOOCOMMERCE-stock-display.php`: tabla de existencias y filtro `_price > 0`.
- `frontend/SAIT_WOOCOMMERCE-promotions.php`: precios de catálogo, producto y carrito.
- `frontend/SAIT_WOOCOMMERCE-cart-minimum.php`: aviso de mínimo y bloqueo visual de checkout.
- `templates/`: modal, tabla, precio promocional y script del mínimo con escape tardío.

## `includes/SAIT_WOOCOMMERCE-process-events.php`

Clase `SAIT_WOOCOMMERCE_ProcessEvents`.

Conserva el punto de entrada y adaptadores historicos; el enrutamiento reside
en `events/SAIT_WOOCOMMERCE-event-router.php` y la logica en handlers separados.

Metodos:

- `SAIT_processEvent($oXml)`: router por atributo XML `type`.
- `MODART($oXml)`: sincroniza productos.
- `ACTEXISGBL($oXml)`: actualiza stock global.
- `ACTEXIST($oXml)`: actualiza stock por almacen o multi-almacen.
- `ACTPRECIO($oXml)`: actualiza precio regular.
- `MODCATEGORIAWC($oXml, $tabla, $numcat, $nomcat)`: crea/actualiza categorias WooCommerce.
- `MODFAMILIA($oXml)`: categoria desde familias.
- `MODDEPTO($oXml)`: categoria desde departamentos.
- `MODLINEA($oXml)`: categoria desde lineas.
- `MODCATEGO($oXml)`: categoria desde categorias.
- `ACTTC($oXml)`: actualiza tipo de cambio y precios en dolares.
- `MODCLI($oXml)`: crea/liga/actualiza clientes.
- `xml_attribute($object, $attribute)`: extrae atributo XML como string.

## `includes/SAIT_WOOCOMMERCE-orders.php`

Clase `SAIT_WOOCOMMERCE_Orders`.

Metodos:

- `SAIT_sendPedido($order, $formapago, $wait = false)`: arma body de pedido y lo envia a `/api/v3/pedidos`.
- `SAIT_sendCotizacion($order, $formapago, $wait = false)`: arma body de cotizacion y lo envia a `/api/v3/cotizaciones`.
- `SAIT_sendOrder($id_pedido, $formapago)`: adaptador legacy que encola el envio automatico.
- `SAIT_envioAutomaticoDisparado($order)`: revisa si la orden ya disparo envio automatico a SAIT.
- `SAIT_marcarEnvioAutomaticoDisparado($order, $formapago, $tipo)`: guarda metadata del envio automatico disparado.
- `SAIT_reenviarPedido($id_pedido)`: reenvia la orden indicada como pedido o cotizacion.
- `SAIT_sendPedidoTest($id_pedido)`: alias interno de compatibilidad.
- `SAIT_registrarResultadoEnvio($order, $response, $tipo, $formapago, $modo)`: guarda metadata del ultimo resultado recibido de SAIT.
- `SAIT_responderResultadoEnvio($resultado)`: genera respuesta REST del reenvio manual.
- `SAIT_calcularPjeDescuentoItem($cantidad, $total, $precio)`: calcula descuento porcentual.
- `SAIT_getDirEnvio($order)`: genera cadena `direnvio` para SAIT.

## Entrega Asincrona De Documentos

- `SAIT_WOOCOMMERCE-order-delivery-state.php`: persiste estados `pending`,
  `sending`, `sent` y `failed`, intentos, timestamps y HTTP mediante `WC_Order`.
- `SAIT_WOOCOMMERCE-order-delivery-scheduler.php`: desduplica acciones,
  ejecuta el POST bloqueante y programa hasta tres intentos con backoff.

## `includes/SAIT_WOOCOMMERCE-document-builders.php`

- `SAIT_WOOCOMMERCE_OrderBuilder`: construye pedidos sin ejecutar HTTP.
- `SAIT_WOOCOMMERCE_QuoteBuilder`: construye cotizaciones sin ejecutar HTTP.
- Ambos comparten partidas, cliente, observaciones y direccion de envio.

## `includes/SAIT_WOOCOMMERCE-document-service.php`

- `build_order()` y `build_quote()`: resuelven dependencias y construyen sin POST.
- `send_document()`: envia un payload ya construido mediante el cliente SAIT.
- `send_order()` y `send_quote()`: coordinan ambas operaciones.
- Expone los filtros `sait_woocommerce_order_payload`,
  `sait_woocommerce_quote_payload` y `sait_woocommerce_document_payload`.

## `includes/SAIT_WOOCOMMERCE-product-calculators.php`

- `SAIT_WOOCOMMERCE_PriceCalculator`: aplica precio publico, lista con
  impuestos y conversion por tipo de cambio sin depender de WordPress.
- `SAIT_WOOCOMMERCE_StockCalculator`: selecciona un almacen o suma varios y
  distingue una existencia cero valida de una respuesta sin coincidencias.

## Resolucion Y Sincronizacion De Productos

- `SAIT_WOOCOMMERCE-product-resolver.php`: busca un producto por mapeo válido
  y usa el SKU como fallback.
- `SAIT_WOOCOMMERCE-product-sync-service.php`: consulta SAIT, aplica los
  calculadores, actualiza el producto y guarda metadatos uniformes.

## `includes/SAIT_WOOCOMMERCE-order-admin.php`

Clase `SAIT_WOOCOMMERCE_OrderAdmin`.

Responsabilidades:

- Agrega el boton `Reenviar pedido a SAIT` en la pantalla de edicion de pedidos.
- Muestra estado de entrega, intentos, ultimo intento y HTTP sin exponer el cuerpo del error.
- Procesa el `admin_post` `sait_reenviar_pedido_admin`.
- Valida permisos y nonce antes de reenviar.
- Usa `SAIT_WOOCOMMERCE_Orders::SAIT_reenviarPedido()` para reutilizar el flujo manual existente.
- Muestra aviso administrativo con el resultado del reenvio.

## `includes/SAIT_WOOCOMMERCE-frontend-compat.php`

Conserva las funciones históricas del selector, existencias, promociones y
mínimo como adaptadores. El comportamiento y los hooks residen en las clases de
`includes/frontend/`.

## `includes/SAIT_WOOCOMMERCE-options.php`

Clase `SAITSettingsPage`.

Responsabilidades:

- Agrega pagina `Configuracion SAIT` en Ajustes.
- Registra la opcion `opciones_sait`.
- Define campos de configuracion.
- Sanitiza valores.
- Renderiza inputs y radios.
- Renderiza la seccion administrativa de sincronizacion de precios cuando la clase esta disponible.

## `includes/SAIT_WOOCOMMERCE-settings.php`

Clase `SAIT_WOOCOMMERCE_Settings`.

- Conserva `opciones_sait` como nombre unico de almacenamiento.
- Centraliza valores predeterminados y lecturas de claves historicas.
- Normaliza banderas y listas de almacenes.
- Sanitiza por lista cerrada los campos aceptados por Settings API.
- Define el mapa exacto entre fuente de categoria, atributo `MODART`, tabla de
  `sait_claves` y clave del evento de catalogo.

## `includes/SAIT_WOOCOMMERCE-art-sync.php`

Clase `SAIT_WOOCOMMERCE_ArtSync`.

Responsabilidades:

- Agrega handlers `admin_post` para sincronizar precio/existencia por SKU, producto y arranque masivo.
- Agrega accion de fila `Sincronizar SAIT` al listado de productos.
- Renderiza controles y estado de sincronizacion en ajustes del plugin.
- Consulta articulos en SAITNube por SKU o por lotes.
- Actualiza precio regular y stock de productos WooCommerce.
- Guarda metadata de auditoria de la ultima sincronizacion por producto.
- Agenda lotes con Action Scheduler si esta disponible; si no, usa WP-Cron.

Metodos principales:

- `render_admin_section()`: pinta botones, estado y avisos.
- `handle_sync_sku()`: procesa el formulario de SKU puntual.
- `handle_sync_product()`: procesa la accion de fila del listado de productos.
- `handle_start_batch()`: inicializa estado y agenda el primer lote.
- `process_batch($offset, $limit)`: procesa un lote de articulos desde SAITNube.
- `sync_sku($sku, $source)`: consulta y sincroniza un articulo puntual.
- `sync_product_from_api_row($numart, $row, $source)`: aplica el precio y existencia calculados al producto WooCommerce.

## `includes/SAIT_WOOCOMMERCE-activator.php`

Clase `SAIT_WOOCOMMERCE_Activator`.

- `SAIT_create_db()`: crea tabla `{prefix}sait_claves` con `dbDelta`.

## `includes/SAIT_WOOCOMMERCE-personalizado.php`

Clase `SAIT_PERSONALIZADO`.

- `SAIT_FuncionPersonalizaPostPedido($body, $order)`: hook manual para modificar body antes de enviarlo.
- `SAIT_getOtrosDatos($order)`: construye texto multilinea con envio, pago y datos del cliente.

Tambien encola `assets/js/personalizado.js` despues del formulario de checkout.

## `includes/SAIT_WOOCOMMERCE-hello.php`

Clase `SAIT_WOOCOMMERCE_Hello`.

- `SAIT_helloworld()`: regresa `hello world!`.

## Assets

- `assets/css/modal.css`: estilos del modal de sucursales.
- `assets/js/modal.js`: abre/cierra modal y guarda sucursal por AJAX.
- `assets/js/personalizado.js`: actualmente vacio.
