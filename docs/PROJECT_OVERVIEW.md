# Resumen Del Proyecto

`SAIT WooCommerce` es un plugin de WordPress para integrar WooCommerce con SAIT/SAITNube.

Su objetivo principal es mantener sincronizados productos, precios, existencias, categorias y clientes desde SAIT hacia WooCommerce, y enviar las ordenes de WooCommerce hacia SAIT como pedidos o cotizaciones.

## Componentes Principales

El plugin vive en `sait-woocommerce/`.

- `SAIT_WOOCOMMERCE.php` es el punto de entrada del plugin.
- `includes/SAIT_WOOCOMMERCE-process-events.php` procesa eventos XML enviados por SAIT.
- `includes/SAIT_WOOCOMMERCE-orders.php` transforma ordenes de WooCommerce en documentos SAIT.
- `includes/SAIT_UTILS.php` concentra utilidades compartidas, llamadas a SAITNube, tabla de claves, existencias y parte de UI frontend.
- `includes/SAIT_WOOCOMMERCE-options.php` define la pagina de configuracion.
- `includes/SAIT_WOOCOMMERCE-cart.php` recalcula precios/promociones en carrito y bloquea checkout si no se cumple un minimo.
- `assets/` contiene CSS/JS del modal de seleccion de sucursal.

## Integracion SAIT -> WooCommerce

SAITNube llama al endpoint:

`POST /wp-json/saitplugin/v1/saitevents`

El request debe incluir el header `x-AccessToken`, que se compara contra la opcion `SAITNube_AccessToken`.

El body esperado es XML. El plugin lee el atributo `type` del nodo raiz y lo manda al manejador correspondiente.

Eventos principales:

- `MODART`: crea, actualiza, restaura o manda a papelera productos WooCommerce.
- `ACTEXIST`: actualiza existencias desde un almacen o desde una suma de almacenes.
- `ACTEXISGBL`: actualiza existencias globales, aunque su condicion actual parece restringirlo si hay almacen configurado.
- `ACTPRECIO`: actualiza precio regular.
- `ACTTC`: guarda nuevo tipo de cambio y recalcula productos en dolares.
- `MODFAMILIA`, `MODDEPTO`, `MODLINEA`, `MODCATEGO`: crean o actualizan categorias WooCommerce.
- `MODCLI`: crea, liga o actualiza clientes WooCommerce.

## Integracion WooCommerce -> SAIT

El plugin escucha:

- `woocommerce_payment_complete`: envia documento con `formapago = "1"`.
- `woocommerce_thankyou`: envia documento con `formapago = "2"`.

Segun `SAITNube_TipoDoc`, genera:

- `P`: pedido a `/api/v3/pedidos`.
- Otro valor: cotizacion a `/api/v3/cotizaciones`.

Los envios se hacen con `SAIT_UTILS::SAIT_PostNube()`, normalmente sin esperar respuesta (`blocking = false`).

## Estado General

El plugin ya tiene README con uso basico, pero la logica real esta concentrada en archivos grandes, funciones globales y metodos estaticos. Hay comentarios utiles en algunas zonas, aunque varios no explican las reglas de negocio o el motivo de validaciones importantes.

Para mantenimiento futuro conviene priorizar:

- Documentar reglas de negocio por evento.
- Normalizar nombres de opciones.
- Agregar validaciones y preparacion SQL.
- Separar UI frontend, API SAITNube y logica de sincronizacion.
- Agregar pruebas o al menos fixtures XML de eventos.
