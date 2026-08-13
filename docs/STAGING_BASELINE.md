# Linea Base Manual De Staging

Esta lista se ejecuta antes y despues de cada etapa funcional del refactor. No
se deben usar credenciales ni datos de clientes reales con los fixtures.

## Preparacion

- [ ] Instalar WordPress 6.6 o superior, WooCommerce 9.3 o superior y PHP 7.4
  o superior.
- [ ] Activar HPOS antes de probar pedidos.
- [ ] Configurar una API SAITNube v3 de pruebas y un token exclusivo.
- [ ] Respaldar base de datos y registrar las opciones `opciones_sait` usadas.
- [ ] Confirmar que la tabla `sait_claves` existe.
- [ ] Activar `WP_DEBUG_LOG` sin mostrar errores al visitante.

## Eventos

- [ ] Ejecutar todos los escenarios de `tests/fixtures/README.md`.
- [ ] Registrar status HTTP, mensaje, IDs creados y cambios de metadatos.
- [ ] Repetir cada evento para observar idempotencia.
- [ ] Confirmar que un token incorrecto responde `401`.
- [ ] Confirmar que XML invalido no modifica datos.

## Pedidos Y Cotizaciones

- [ ] Probar cliente normal mapeado.
- [ ] Probar cliente normal encontrado por correo.
- [ ] Probar cliente eventual existente por correo.
- [ ] Probar cliente completamente nuevo.
- [ ] Comparar los payloads con `tests/fixtures/expected/`.
- [ ] Confirmar un solo envio automatico al dispararse ambos hooks.
- [ ] Probar reenvio manual autorizado y registrar HTTP/respuesta de SAIT.
- [ ] Repetir con `SAITNube_TipoDoc` para pedido y cotizacion.

## Cierre

- [ ] Ejecutar PHP lint.
- [ ] Revisar `debug.log` y logs de WooCommerce.
- [ ] Confirmar que no cambiaron rutas, opciones, metadatos ni tabla.
- [ ] Restaurar el snapshot antes de repetir la linea base.
- [ ] Adjuntar resultados, versiones exactas y diferencias al release.

