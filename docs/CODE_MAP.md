# Mapa De Codigo

## `sait-woocommerce/SAIT_WOOCOMMERCE.php`

Archivo principal del plugin.

Responsabilidades:

- Declara metadatos del plugin.
- Incluye opciones, utilidades, funciones personalizadas y carrito.
- Define constantes por defecto:
  - `SAIT_NUBE_NUMALM = "1"`
  - `SAIT_SERIE = "WO"`
- Registra activacion.
- Registra endpoints REST.
- Registra hooks de envio de ordenes.
- Registra assets del modal de sucursal.

Funciones:

- `activate_SAIT_WOOCOMMERCE()`: crea tabla de mapeos.
- `SAIT_helloworld()`: callback REST de prueba.
- `SAIT_procesEvents($request)`: valida token, parsea XML y delega procesamiento.
- `SAIT_reenviarPedido($request)`: reenvia una orden usando `idpedido` desde la ruta REST.
- `sendOrderSAIT_payment($order_id)`: envia orden pagada con forma de pago `1`.
- `sendOrderSAIT_thankyou($order_id)`: envia orden no pagada con forma de pago `2`.
- `registrar_estilos_scripts()`: carga Font Awesome, CSS/JS del modal y nonce AJAX cuando esta activo selector de sucursal.

## `includes/SAIT_UTILS.php`

Clase `SAIT_UTILS` y varias funciones globales frontend.

Metodos principales:

- `SAIT_getClientebyemail($email)`: busca cliente SAIT por `emailtw`.
- `SAIT_getClienteEventualbyemail($email)`: busca cliente eventual SAIT por email.
- `SAIT_GetNube($uri, $reintentar = true)`: GET a SAITNube con API key y un reintento opcional.
- `SAIT_PostNube($uri, $bodyObject, $wait = false)`: POST JSON a SAITNube.
- `SAIT_getClaves($tabla, $clave, $wcid)`: consulta mapeo en `{prefix}sait_claves`.
- `SAIT_insertClaves($tabla, $clave, $wcid)`: inserta mapeo.
- `SAIT_deleteClaves($id)`: elimina mapeo.
- `SAIT_response($code, $message)`: crea `WP_REST_Response`.
- `SAIT_codigo_valido($codigo)`: valida codigos GTIN/UPC/EAN/ISBN por formato y longitud.
- `getExistSAIT($SKU)`: obtiene existencia desde SAITNube; puede sumar almacenes configurados.

Funciones globales:

- `agregar_boton_al_menu($items, $args)`: agrega boton de sucursal al menu `primary`.
- `agregar_modal_sucursal()`: imprime modal con sucursales desde SAITNube.
- `guardar_sucursal()`: handler AJAX para guardar sucursal seleccionada.
- `mostrar_tabla_almacenes()`: muestra existencias por sucursal en producto.
- `ocultar_productos_sin_precio($query)`: filtra catalogo por `_price > 0`.
- `sait_precio_promocional_en_producto($price_html, $product)`: reemplaza HTML de precio con precio promocional consultado a SAITNube.

## `includes/SAIT_WOOCOMMERCE-process-events.php`

Clase `SAIT_WOOCOMMERCE_ProcessEvents`.

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
- `SAIT_sendOrder($id_pedido, $formapago)`: envio automatico con idempotencia; decide pedido/cotizacion segun configuracion.
- `SAIT_envioAutomaticoDisparado($order)`: revisa si la orden ya disparo envio automatico a SAIT.
- `SAIT_marcarEnvioAutomaticoDisparado($order, $formapago, $tipo)`: guarda metadata del envio automatico disparado.
- `SAIT_reenviarPedido($id_pedido)`: reenvia la orden indicada como pedido o cotizacion.
- `SAIT_sendPedidoTest($id_pedido)`: alias interno de compatibilidad.
- `SAIT_registrarResultadoEnvio($order, $response, $tipo, $formapago, $modo)`: guarda metadata del ultimo resultado recibido de SAIT.
- `SAIT_responderResultadoEnvio($resultado)`: genera respuesta REST del reenvio manual.
- `SAIT_calcularPjeDescuentoItem($cantidad, $total, $precio)`: calcula descuento porcentual.
- `SAIT_getDirEnvio($order)`: genera cadena `direnvio` para SAIT.

## `includes/SAIT_WOOCOMMERCE-cart.php`

Funciones:

- `calcularpreciosCarrito($cart)`: consulta SAITNube y reemplaza precio de productos en carrito si hay promocion.
- `display_discounted_price_in_cart($price, $cart_item, $cart_item_key)`: muestra precio con tachado del regular.
- `sait_minimo_total_carrito()`: agrega error si subtotal no cumple minimo.
- `sait_bloquear_botones_checkout()`: imprime JS para bloquear botones si subtotal no cumple minimo.

## `includes/SAIT_WOOCOMMERCE-options.php`

Clase `SAITSettingsPage`.

Responsabilidades:

- Agrega pagina `Configuracion SAIT` en Ajustes.
- Registra la opcion `opciones_sait`.
- Define campos de configuracion.
- Sanitiza valores.
- Renderiza inputs y radios.

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
