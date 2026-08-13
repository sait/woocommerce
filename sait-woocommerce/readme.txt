=== SAIT WooCommerce ===
Contributors: sait
Requires at least: 6.6
Requires PHP: 7.4
Stable tag: 2.0.6
WC requires at least: 9.3
WC tested up to: 9.3

Integra SAIT/SAITNube con WooCommerce para sincronizar catálogo y clientes, y enviar pedidos o cotizaciones.

== Description ==

SAIT WooCommerce recibe eventos XML de SAITNube para crear o actualizar
productos, precios, existencias, categorías y clientes. También construye y
envía pedidos o cotizaciones de WooCommerce a la API v3 de SAITNube.

Desde la versión 2.0.0 se requiere configuración válida de URL, API key y token de
webhook. Las personalizaciones específicas de empresas deben instalarse como
plugins complementarios independientes.

== Installation ==

1. Instala y activa WooCommerce 9.3 o superior.
2. Sube el ZIP de SAIT WooCommerce desde Plugins > Agregar plugin.
3. Activa el plugin.
4. Configura la integración en Ajustes > Configuración SAIT.
5. Confirma el webhook y realiza un pedido de prueba antes de producción.

== Changelog ==

= 2.0.6 =

* Permite desactivar por configuración la categoría y el modelo aplicados por MODART.
* Agrega contratos de extensión para controlar productos preexistentes por SKU, categorías y modelo.
* Incluye el complemento Fysson para conservar sus reglas históricas sin afectar otras instalaciones.

= 2.0.5 =

* Corrige la carga del procesador de pedidos en las solicitudes independientes de Action Scheduler.

= 2.0.4 =

* Conserva en numcli todos los identificadores encontrados por /clientes, incluidos valores con guion como WC-2.

= 2.0.3 =

* Agrega trazas saneadas del ciclo de envío de pedidos y cotizaciones en debug.log.
* Registra las excepciones de envío como fallo o reintento en lugar de conservar el estado Enviando.
* Simplifica el aviso del reenvío manual y considera exitoso solamente HTTP 201.

= 2.0.2 =

* Considera como cero la existencia negativa de cada almacén antes de sumar el total configurado.
* Evita guardar o mostrar existencias negativas en WooCommerce.
* Actualiza la sincronización de productos para persistir correctamente una existencia efectiva de cero.

= 2.0.0 =

* Reorganiza el plugin en servicios, controladores REST y manejadores de eventos.
* Protege el reenvío manual de pedidos con autenticación y permisos.
* Agrega entrega asíncrona idempotente con reintentos mediante Action Scheduler.
* Permite elegir línea, familia, categoría o departamento para categorías WooCommerce.
* Reduce consultas repetidas de precios y promociones mediante caché contextual.
* Agrega extensiones para plugins personalizados y compatibilidad HPOS declarada.
