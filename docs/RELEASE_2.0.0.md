# Liberación SAIT WooCommerce 2.0.0

## Artefactos

La liberación genera dos paquetes independientes:

- `sait-woocommerce-2.0.0.zip`: núcleo para todas las instalaciones.
- `sait-woocommerce-papelia-1.0.0.zip`: reglas exclusivas de Papelía.

Los ZIP se construyen desde el commit actual mediante `git archive`; no toman
archivos sin seguimiento ni herramientas del repositorio. `SHA256SUMS` permite
verificar que el archivo desplegado sea exactamente el validado.

```sh
sh scripts/build-release.sh 2.0.0 1.0.0
```

## Validación Local

La suite general no accede a endpoints reales:

```sh
docker compose -f tests/docker-compose.yml run --rm -T composer run phpstan
sh tests/test-all.sh
```

La instalación y actualización se validan en volúmenes Docker aislados. La
prueba instala primero la copia histórica 1.2.3 del commit `1afa911`, conserva
marcadores en `opciones_sait` y `sait_claves`, actualiza al ZIP 2.0.0 y después
prueba una instalación limpia:

```sh
sh tests/test-release-install.sh
```

Los volúmenes temporales usados por esa prueba tienen el prefijo
`sait_woocommerce_release_` y se eliminan al terminar. El entorno habitual de
`tests/docker-compose.yml` no se modifica.

## Previo A Producción

Estos pasos requieren una copia de staging con datos reales y no se sustituyen
con fixtures:

1. Respaldar la base de datos completa.
2. Copiar el directorio del plugin actualmente instalado.
3. Registrar versión de WordPress, WooCommerce, PHP y plugin anterior.
4. Registrar el conteo de filas de `{prefix}sait_claves` y exportar
   `opciones_sait` sin publicar credenciales.
5. Confirmar que no haya entregas SAIT en estado `sending` ni tareas duplicadas
   pendientes en Action Scheduler.
6. Instalar `sait-woocommerce-2.0.0.zip` sin activar el complemento Papelía en
   empresas que no lo requieren.
7. Ejecutar `docs/STAGING_BASELINE.md` con credenciales controladas.
8. Comparar opciones, mapeos y pedidos antes/después.
9. Revisar logs y la cola de Action Scheduler antes de aprobar producción.

## Papelía

Papelía usa el checkout clásico `[woocommerce_checkout]`. Después del núcleo se
instala su ZIP separado y se validan:

- entrega normal;
- recogida `local_pickup:4` con existencias;
- recogida con faltantes aceptados;
- pedido y cotización con `otrosdatos` y `obs`;
- correo administrativo y metadatos de sucursal.

## Rollback

Si la validación posterior falla:

1. Poner la tienda en mantenimiento para evitar pedidos durante la reversión.
2. Desactivar SAIT WooCommerce 2.0.0 y el complemento personalizado.
3. Restaurar el directorio exacto del plugin anterior.
4. Restaurar la base de datos sólo si hubo escrituras incompatibles o pedidos
   de prueba que deban eliminarse. La desactivación por sí misma no borra datos.
5. Verificar `opciones_sait`, `sait_claves` y estados de entrega de órdenes.
6. Revisar Action Scheduler antes de reactivar para no duplicar documentos.
7. Reactivar la versión anterior y ejecutar un webhook/pedido controlado.

No se debe reintentar manualmente un pedido cuyo estado en SAIT sea incierto;
primero se confirma en SAIT para evitar duplicados.
