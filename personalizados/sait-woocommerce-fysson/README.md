# SAIT WooCommerce - Fysson

Complemento para conservar las reglas de sincronización `MODART` solicitadas por Fysson sin cambiar el comportamiento general de SAIT WooCommerce.

## Requisitos

- WooCommerce activo.
- SAIT WooCommerce 2.0.6 o posterior activo.

## Comportamiento

Mientras el complemento está activo:

- un artículo recibido cuyo SKU ya existe en WooCommerce, pero todavía no está relacionado en `sait_claves`, se ignora y no se modifica ni se relaciona automáticamente;
- `MODART` no asigna ni reemplaza categorías de productos;
- `MODART` no escribe el modelo en la descripción corta;
- los artículos ya relacionados siguen recibiendo nombre, SKU, código global, descripción larga y existencia;
- los artículos nuevos enviados por SAIT se siguen creando y relacionando, sin categoría ni descripción corta derivada del modelo.

No agrega opciones propias. Activarlo habilita todas estas reglas y desactivarlo restaura el comportamiento configurado en el plugin base.
