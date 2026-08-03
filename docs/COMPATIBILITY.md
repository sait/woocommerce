# Compatibilidad De SAIT WooCommerce

Este documento separa lo declarado por el plugin de lo observado en el codigo.
La matriz definitiva debe aprobarse antes de introducir sintaxis o APIs nuevas.

## Compatibilidad Declarada Actualmente

El encabezado 1.2.3 no declara:

- `Requires at least`
- `Requires PHP`
- `WC requires at least`
- `WC tested up to`

El README sólo exige WordPress con WooCommerce activo. Por lo tanto no existe
una version minima formal que pueda verificarse o usarse para empaquetado.

## Compatibilidad Observada

- El codigo usa sintaxis disponible desde PHP 7, como el operador `??`.
- No hay Composer ni una restriccion de plataforma PHP.
- El plugin usa REST API, Settings API, transients y HTTP API de WordPress.
- El acceso a pedidos se realiza principalmente con `WC_Order` y sus metodos
  CRUD, lo cual es favorable para HPOS.
- La sincronizacion manual cuenta productos mediante `wp_posts` y
  `wp_postmeta`; esta consulta es de productos, no de pedidos, y no impide HPOS.
- El plugin usa `WC_Product::set_global_unique_id()`, por lo que la version
  minima real de WooCommerce debe confirmarse antes de declarar soporte.
- El changelog contiene una referencia explicita a comportamiento requerido
  por WooCommerce 9.3, pero eso no constituye por si solo una matriz soportada.
- No hay pruebas automatizadas contra varias versiones de WordPress,
  WooCommerce o PHP.
- No hay evidencia de pruebas multisite.

## Matriz Aprobada Para La Refactorizacion

Esta matriz fue aprobada como objetivo de la refactorizacion. No debe añadirse
al encabezado del plugin ni publicarse como soporte verificado hasta validarla
en staging o CI.

| Componente | Version o politica | Estado |
| --- | --- | --- |
| PHP | 7.4 o superior | Aprobado; pendiente de pruebas. |
| WordPress | 6.6 o superior | Aprobado; pendiente de pruebas. |
| WooCommerce | 9.3 o superior | Aprobado; pendiente de pruebas. |
| HPOS | Soportado y obligatorio en pruebas | Aprobado; pendiente de validacion. |
| Multisite | No soportado oficialmente | Aprobado. |
| SAITNube API | v3 | Confirmado por rutas del codigo. |

## Decisiones Aprobadas

- [x] PHP minimo: 7.4.
- [x] WordPress minimo: 6.6.
- [x] WooCommerce minimo: 9.3.
- [x] Multisite no forma parte del soporte oficial actual.
- [x] HPOS debe incluirse en la validacion de cada release.
- [ ] Agregar los headers aprobados al archivo principal en un commit funcional
  posterior, una vez que existan pruebas de esta matriz.

## Verificacion Requerida

Para declarar una combinacion como soportada se debe comprobar:

- Activacion sin fatal ni avisos.
- Creacion/actualizacion de `sait_claves`.
- Guardado de todas las opciones.
- Recepcion de eventos XML.
- Creacion y actualizacion de productos y clientes.
- Pedido pagado, pedido sin pago y cotizacion.
- Cliente normal, eventual y nuevo.
- Reenvio manual desde admin.
- Sincronizacion por SKU y por lotes.
- Funcionamiento con HPOS activado.
- PHP lint y pruebas automatizadas exitosas.
