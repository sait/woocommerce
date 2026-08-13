# Compatibilidad De SAIT WooCommerce

Este documento separa lo declarado por el plugin de lo observado en el codigo.
La matriz definitiva debe aprobarse antes de introducir sintaxis o APIs nuevas.

## Compatibilidad Declarada Actualmente

La versión 2.0.0 declara en el encabezado y en `readme.txt`:

- WordPress 6.6 o superior.
- PHP 7.4 o superior.
- WooCommerce 9.3 o superior.
- WooCommerce probado localmente hasta la serie 9.3.
- Compatibilidad con HPOS mediante `FeaturesUtil::declare_compatibility()`.

## Compatibilidad Observada

- El codigo usa sintaxis disponible desde PHP 7, como el operador `??`.
- Composer se usa únicamente para herramientas de desarrollo y fija PHP 7.4
  como plataforma de análisis.
- El plugin usa REST API, Settings API, transients y HTTP API de WordPress.
- El acceso a pedidos se realiza principalmente con `WC_Order` y sus metodos
  CRUD, lo cual es favorable para HPOS.
- La sincronizacion manual cuenta productos mediante `wp_posts` y
  `wp_postmeta`; esta consulta es de productos, no de pedidos, y no impide HPOS.
- El plugin usa `WC_Product::set_global_unique_id()`, por lo que la version
  minima real de WooCommerce debe confirmarse antes de declarar soporte.
- El changelog contiene una referencia explicita a comportamiento requerido
  por WooCommerce 9.3, pero eso no constituye por si solo una matriz soportada.
- La suite local cubre WordPress 6.6.2, WooCommerce 9.3.3 y PHP 7.4; versiones
  superiores requieren validación de release adicional.
- No hay evidencia de pruebas multisite.

## Matriz Aprobada Para La Refactorizacion

Esta matriz fue aprobada como objetivo de la refactorizacion. No debe añadirse
al encabezado del plugin ni publicarse como soporte verificado hasta validarla
en staging o CI.

| Componente | Version o politica | Estado |
| --- | --- | --- |
| PHP | 7.4 o superior | Validado localmente en 7.4. |
| WordPress | 6.6 o superior | Validado localmente en 6.6.2. |
| WooCommerce | 9.3 o superior | Validado localmente en 9.3.3. |
| HPOS | Soportado y obligatorio en pruebas | Declarado y validado localmente. |
| Multisite | No soportado oficialmente | Aprobado. |
| SAITNube API | v3 | Confirmado por rutas del codigo. |

## Decisiones Aprobadas

- [x] PHP minimo: 7.4.
- [x] WordPress minimo: 6.6.
- [x] WooCommerce minimo: 9.3.
- [x] Multisite no forma parte del soporte oficial actual.
- [x] HPOS debe incluirse en la validacion de cada release.
- [x] Agregar los headers aprobados al archivo principal después de validar la
  matriz local.

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
