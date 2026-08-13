# Resumen Del Proyecto

`SAIT WooCommerce` es un plugin de WordPress para integrar WooCommerce con SAIT/SAITNube.

Su objetivo principal es mantener sincronizados productos, precios, existencias, categorias y clientes desde SAIT hacia WooCommerce, y enviar las ordenes de WooCommerce hacia SAIT como pedidos o cotizaciones.

## Componentes Principales

El plugin vive en `sait-woocommerce/`.

- `SAIT_WOOCOMMERCE.php` es el punto de entrada del plugin.
- `includes/SAIT_WOOCOMMERCE-process-events.php` procesa eventos XML enviados por SAIT.
- `includes/SAIT_WOOCOMMERCE-orders.php` transforma ordenes de WooCommerce en documentos SAIT.
- `includes/SAIT_UTILS.php` conserva utilidades compartidas y adaptadores de integración con SAITNube.
- `includes/SAIT_WOOCOMMERCE-options.php` define la pagina de configuracion.
- `includes/frontend/` separa selector de sucursal, promociones, existencias y mínimo de carrito.
- `templates/` contiene la presentación PHP de los módulos frontend.
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

Los envíos se programan mediante Action Scheduler, con alternativa WP-Cron, y
el servicio centralizado registra estados e intentos antes de confirmar la
respuesta de SAITNube.

## Estado General

La versión 2.0.0 separa controladores REST, manejadores de eventos, servicios de
clientes/documentos/productos, módulos frontend y adaptadores de
infraestructura. Los contratos, configuración, flujos, extensiones y pruebas se
documentan bajo `docs/` y `tests/`.
