# Checklist De Refactorizacion Por Etapas

Plan revisado con las skills `wordpress-router`, `wp-project-triage`,
`wp-plugin-development`, `wp-rest-api`, `wp-performance` y `wp-phpstan`.

El objetivo es reorganizar `SAIT WooCommerce` sin cambiar accidentalmente el
contrato con SAIT, WooCommerce o las instalaciones existentes. Cada etapa debe
poder revisarse, probarse y revertirse de manera independiente.

## Estado De Partida

- Proyecto: plugin clásico de WordPress dependiente de WooCommerce.
- Plugin objetivo: `sait-woocommerce/`.
- Integración SAIT -> WooCommerce: eventos XML por REST.
- Integración WooCommerce -> SAIT: pedidos y cotizaciones por HTTP JSON.
- Persistencia propia: tabla `{prefix}sait_claves`.
- Trabajo asíncrono: Action Scheduler con alternativa WP-Cron en la
  sincronización masiva.
- Herramientas disponibles: Docker Compose para el entorno de integracion y
  Node `v24.19.0` mediante `nvm` cuando una etapa futura lo requiera.
- El triage ejecutado sobre `sait-woocommerce/` clasifica el proyecto como
  `wp-plugin` y detecta `SAIT WooCommerce` versión `1.2.3`.
- No se detectaron bloques Gutenberg, Abilities API ni integración WP-CLI.
- No se detectaron `composer.json`, `package.json`, PHPStan, PHPUnit, `wp-env`,
  Playwright o Jest.
- En shells que no carguen `nvm`, Node está disponible actualmente en
  `/home/ali/.nvm/versions/node/v24.19.0/bin/node`.

## Reglas Para Cada Etapa

- [ ] Mantener el prefijo de Conventional Commits (`test:`, `refactor:`,
  `fix:`, etc.) y redactar la descripcion del commit en español.

- [ ] No modificar las copias históricas dentro de `plugins/`.
- [ ] No mezclar refactorizaciones con cambios de comportamiento.
- [ ] Mantener compatibles las opciones de `opciones_sait`.
- [ ] Mantener endpoints, hooks y filtros públicos durante la transición.
- [ ] Mantener compatible la tabla `sait_claves` hasta contar con una migración.
- [ ] No añadir dependencias Composer o Node sin aprobarlas explícitamente.
- [ ] No usar endpoints, credenciales ni datos reales de SAIT en pruebas. Toda
  llamada API v3 debe usar el host reservado y fixtures JSON locales.
- [ ] Repetir el triage desde la carpeta del plugin después de cambios de
  estructura importantes:

  ```bash
  node ../.codex/skills/wp-project-triage/scripts/detect_wp_project.mjs
  node ../.codex/skills/wp-plugin-development/scripts/detect_plugins.mjs
  ```

- [ ] Ejecutar desde la raiz:

  ```bash
  sh tests/smoke-test.sh
  ```

- [ ] Ejecutar desde la raíz:

  ```bash
  git diff --check
  ```

- [ ] Revisar que el commit no incluya `.vscode/`, `plugins/` ni cambios ajenos.
- [ ] Actualizar `CHANGELOG.md` únicamente cuando exista un cambio observable.
- [ ] Realizar pruebas de integración en staging antes de desplegar.

## Etapa 0: Definir Compatibilidad Y Linea Base

Objetivo: saber qué comportamiento y versiones deben preservarse.

### Compatibilidad

- [x] WordPress minimo 6.6; la version maxima se valida por release.
- [x] WooCommerce minimo 9.3; la version maxima se valida por release.
- [x] PHP minimo 7.4.
- [x] Multisite no forma parte del soporte oficial actual.
- [x] HPOS es obligatorio en las pruebas de compatibilidad.
- [x] SAITNube API v3 confirmada por el contrato actual.
- [x] Evitar APIs de WordPress o PHP superiores a la matriz aprobada sin una
  actualizacion explicita de compatibilidad.

### Contratos actuales

- [x] Inventariar todos los hooks, filtros y endpoints registrados.
- [x] Inventariar opciones y valores predeterminados.
- [x] Inventariar metadatos de pedidos y productos.
- [x] Documentar estructura e índices de `sait_claves`.
- [x] Capturar la estructura actual del payload de pedido y cotización.
- [x] Documentar respuestas HTTP y mensajes esperados por SAIT.
- [x] Documentar qué operaciones son bloqueantes y cuáles no esperan respuesta.

La linea base resultante esta en `docs/BASELINE_CONTRACTS.md`. Las versiones
observadas y la matriz aprobada estan en `docs/COMPATIBILITY.md`.

### Fixtures mínimos

- [x] `MODART`: alta, actualización, baja y producto preexistente por SKU.
- [x] `ACTPRECIO`: precio público, lista configurada, dólares y precio por volumen.
- [x] `ACTEXIST`: almacén único y múltiples almacenes.
- [x] `ACTTC`.
- [x] `MODCLI`: alta, actualización, correo duplicado y usuario preexistente.
- [x] Categorías: familia, departamento, línea y categoría.
- [x] Pedido y cotización para cliente normal, eventual y nuevo.

Los fixtures estan en `tests/fixtures/` y la ejecucion manual se documenta en
`docs/STAGING_BASELINE.md`. El cliente eventual existente se conserva como una
regresion objetivo porque la version 1.2.3 no reutiliza actualmente su clave.

### Commit 0.1

Alcance: documentación de compatibilidad y contratos, sin tocar producción.

```text
docs: definir versiones soportadas y contratos de integracion
```

### Commit 0.2

Alcance: fixtures XML/JSON y checklist manual de staging.

```text
test: agregar fixtures de integracion SAIT y linea base de staging
```

## Etapa 1: Crear Una Red De Seguridad Ejecutable

Objetivo: detectar cambios de comportamiento antes de mover código.

- [x] Posponer Composer hasta extraer logica pura que justifique PHPUnit o
  analisis estatico.
- [x] Preparar un entorno reproducible con WordPress 6.6.2, WooCommerce 9.3.3
  y PHP 7.4 mediante Docker Compose.
- [x] Mantener PHP lint como validación mínima obligatoria.
- [ ] Agregar pruebas unitarias para lógica pura:
  - [ ] Cálculo de descuentos.
  - [ ] Normalización de configuración.
  - [ ] Selección de almacenes.
  - [ ] Cálculo de precios e impuestos.
  - [ ] Resolución de cliente normal/eventual/nuevo.
- [ ] Decidir en un cambio funcional separado si las existencias fraccionarias
  deben conservar decimales; WooCommerce 9.3.3 guarda actualmente `7.500` como
  `7` y `9.75` como `9` con la configuracion base.
- [ ] Agregar pruebas de integración WordPress/WooCommerce para:
  - [x] Activación y creación de tabla.
  - [x] Registro de endpoints.
  - [x] Procesamiento de fixtures XML.
  - [ ] Construcción de payloads de pedidos y cotizaciones.
- [x] Preparar dobles de prueba para evitar llamadas reales a SAIT.
- [x] Probar que el plugin se carga sin avisos ni errores fatales.

### Commit 1.1

Alcance: infraestructura de pruebas, sin modificar lógica productiva.

```text
test: agregar entorno reproducible para el plugin de WordPress
```

### Commit 1.2

Alcance: pruebas de caracterización del comportamiento actual.

```text
test: caracterizar eventos SAIT y payloads de documentos
```

No continuar si las pruebas sólo pueden pasar llamando servicios productivos.

## Etapa 2: Corregir La Superficie REST Antes Del Refactor

Objetivo: estabilizar y proteger el contrato de entrada.

- [ ] Crear controladores REST separados del archivo principal.
- [ ] Conservar temporalmente el namespace `saitplugin/v1`.
- [ ] Usar constantes `WP_REST_Server` para los métodos.
- [ ] Declarar `args` para IDs y otros parámetros.
- [ ] Validar y sanitizar mediante el objeto `WP_REST_Request`.
- [ ] Normalizar éxitos con `rest_ensure_response()` o `WP_REST_Response`.
- [ ] Normalizar fallos reales con `WP_Error` y estado explícito.
- [ ] Mantener `POST /saitevents` público a nivel WordPress únicamente porque
  utiliza token externo, pero comparar el token de forma segura.
- [ ] Proteger `POST /reenviar-pedido-sait/{idpedido}` con autenticación,
  capacidad y autorización sobre la orden.
- [ ] Conservar `/testpedido/{idpedido}` sólo como alias de transición o
  desactivarlo fuera de desarrollo.
- [ ] Agregar pruebas para 400, 401, 403, 404 y casos exitosos.
- [ ] Verificar `OPTIONS` y descubrimiento de rutas.

### Commit 2.1

Alcance: pruebas y esquemas REST sin cambiar permisos todavía.

```text
test: cubrir permisos y validacion de rutas REST de SAIT
```

### Commit 2.2

Alcance: controladores, esquemas y respuestas normalizadas.

```text
refactor: mover rutas REST de SAIT a controladores
```

### Commit 2.3

Alcance: cambio de seguridad independiente y documentado.

```text
security: restringir endpoints de reenvio de pedidos SAIT
```

## Etapa 3: Bootstrap, Ciclo De Vida Y Configuracion

Objetivo: dejar un archivo principal pequeño y una inicialización predecible.

- [ ] Mantener un único archivo principal con encabezado del plugin.
- [ ] Registrar activación y desactivación en el nivel superior.
- [ ] Crear una clase `Plugin` o `Loader` para registrar hooks.
- [ ] Evitar llamadas HTTP, HTML y trabajo pesado al cargar archivos.
- [ ] Cargar administración sólo en hooks administrativos.
- [ ] Crear una versión de esquema guardada en opciones.
- [ ] Crear rutina idempotente de actualización de esquema.
- [ ] Definir política explícita de desinstalación; no borrar datos por defecto.
- [ ] Crear un objeto `Settings` que encapsule `opciones_sait`.
- [ ] Mantener los nombres históricos de las opciones.
- [ ] Centralizar defaults, booleanos y lista de almacenes.
- [ ] Mantener Settings API con sanitización por campo.
- [ ] Verificar capacidad `manage_options` y escape tardío de HTML.

### Fuente De Categoria De Productos

- [ ] Agregar a `opciones_sait` un selector para elegir la fuente de la
  categoria WooCommerce: linea, familia, categoria o departamento.
- [ ] Definir el nombre definitivo de la opcion y sanearla mediante una lista
  cerrada de valores permitidos.
- [ ] Confirmar el valor predeterminado y la migracion antes de implementarlo:
  la version 1.2.3 observada usa `linea` de forma fija, pero instalaciones
  anteriores pueden haber esperado `numfam`.
- [ ] Centralizar la correspondencia entre la opcion, el atributo de `MODART`
  y la tabla de mapeo de `sait_claves`:
  - [ ] Linea: atributo `linea`, tabla `lineas`.
  - [ ] Familia: atributo `familia`, tabla `familia`.
  - [ ] Categoria: atributo `categoria`, tabla `catego`.
  - [ ] Departamento: atributo `numdep`, tabla `deptos`.
- [ ] Hacer que `MODART` asigne `product_cat` usando la fuente configurada.
- [ ] Definir el comportamiento cuando el atributo o el mapeo no existe, sin
  borrar accidentalmente categorias ya asignadas.
- [ ] Agregar pruebas para las cuatro fuentes, opcion invalida, valor ausente y
  mapeo inexistente.
- [ ] Documentar la opcion y su valor compatible en la guia de configuracion.

### Commit 3.1

```text
refactor: agregar cargador y servicios de ciclo de vida del plugin
```

### Commit 3.2

```text
refactor: centralizar acceso tipado a la configuracion SAIT
```

### Commit 3.3

```text
refactor: usar servicio de configuracion en los modulos del plugin
```

Hacer el commit 3.3 por módulos si el diff es grande: pedidos, eventos,
frontend y administración.

### Commit 3.4

Alcance: cambio funcional aislado para elegir la clasificacion SAIT usada como
categoria del producto.

```text
feat: permitir configurar la categoria de productos desde SAIT
```

## Etapa 4: Extraer Infraestructura Compartida

Objetivo: centralizar HTTP, mapeos y logs antes de dividir reglas de negocio.

### Cliente SAIT

- [ ] Crear `SaitClient` como único adaptador HTTP.
- [ ] Centralizar URL, API key, headers, SSL y timeouts.
- [ ] Normalizar respuesta válida, `result: null`, JSON inválido, `WP_Error` y
  estados HTTP no exitosos.
- [ ] Definir política de reintentos por operación.
- [ ] Permitir inyectar un cliente falso en pruebas.
- [ ] Conservar adaptadores temporales para `SAIT_GetNube` y `SAIT_PostNube`.

### Repositorio de mapeos

- [ ] Crear `MappingRepository` para `sait_claves`.
- [ ] Separar búsqueda por clave SAIT y por ID de WooCommerce.
- [ ] Usar `$wpdb->prepare()` en todas las consultas.
- [ ] Evitar inserciones duplicadas.
- [ ] No añadir restricción única hasta auditar datos existentes.
- [ ] Definir métodos de productos, clientes y categorías.

### Logging

- [ ] Crear logger del plugin sobre `wc_get_logger()`.
- [ ] Añadir contexto de evento, orden, SKU e intento.
- [ ] No registrar API keys, tokens ni información personal innecesaria.

### Commit 4.1

```text
refactor: agregar cliente centralizado para la API de SAIT
```

### Commit 4.2

```text
refactor: agregar repositorio de mapeos de entidades SAIT
```

### Commit 4.3

```text
refactor: centralizar logs saneados de WooCommerce
```

## Etapa 5: Centralizar Clientes Y Documentos

Objetivo: eliminar duplicación entre pedidos y cotizaciones.

### Resolución de clientes

- [ ] Crear `CustomerResolver`.
- [ ] Resolver en orden:
  - [ ] Mapeo local válido.
  - [ ] Cliente normal por correo -> `numcli`.
  - [ ] Cliente eventual por correo -> `numcliev`.
  - [ ] Cliente nuevo -> objeto `clievent`.
- [ ] Garantizar que sólo una representación quede activa.
- [ ] Probar usuarios registrados, invitados y correos inválidos.

### Construcción de documentos

- [ ] Crear builders puros para pedido y cotización.
- [ ] Compartir construcción de artículos, dirección, observaciones y cliente.
- [ ] Separar construcción del payload y envío HTTP.
- [ ] Comparar payloads con los fixtures de la etapa 0.
- [ ] Exponer filtros documentados para personalizaciones.

### Commits

```text
refactor: centralizar resolucion de clientes SAIT
refactor: extraer constructores de pedidos y cotizaciones SAIT
refactor: separar construccion y envio de documentos SAIT
feat: exponer filtros para personalizar documentos SAIT
```

Cada línea representa un commit separado.

## Etapa 6: Unificar Sincronizacion De Productos

Objetivo: aplicar las mismas reglas desde eventos, acciones manuales y lotes.

- [ ] Crear `ProductResolver` que busque por mapeo y luego por SKU.
- [ ] Crear `PriceCalculator` sin dependencias de WordPress.
- [ ] Crear `StockCalculator` sin dependencias de WordPress.
- [ ] Crear `ProductSyncService` que coordine repositorio, WooCommerce y SAIT.
- [ ] Definir explícitamente tratamiento de precios y existencias en cero.
- [ ] Unificar reglas de lista, impuestos, divisa y tipo de cambio.
- [ ] Unificar almacén único y múltiples almacenes.
- [ ] Reutilizar desde `MODART`, `ACTPRECIO`, `ACTEXIST` y sincronización admin.
- [ ] Mantener metadatos de auditoría uniformes.

### Cambio funcional aislado

- [ ] Vincular un producto WooCommerce existente por SKU cuando falte el mapeo.
- [ ] No crear un SKU duplicado.
- [ ] Registrar el mapeo sólo después de validar el producto.

### Commits

```text
refactor: centralizar calculos de precios y existencias SAIT
refactor: agregar servicio compartido de sincronizacion de productos SAIT
refactor: usar sincronizacion de productos en procesos administrativos
refactor: usar sincronizacion de productos en eventos SAIT
fix: vincular productos WooCommerce existentes por SKU en MODART
```

El último commit cambia comportamiento y no debe mezclarse con los anteriores.

## Etapa 7: Hacer Confiable El Envio De Pedidos

Objetivo: distinguir disparo, aceptación y fallo sin duplicar documentos.

- [ ] Definir estados `pending`, `sending`, `sent` y `failed`.
- [ ] Registrar intentos, timestamps, HTTP status y último error.
- [ ] No marcar como enviado un POST no bloqueante sin confirmación.
- [ ] Programar envíos automáticos con Action Scheduler.
- [ ] Hacer cada tarea idempotente.
- [ ] Desduplicar acciones programadas.
- [ ] Definir reintentos limitados y backoff.
- [ ] Mantener disparo manual protegido para recuperación.
- [ ] Considerar HPOS: usar CRUD de `WC_Order`, no acceso directo a posts.
- [ ] Probar que `woocommerce_thankyou` y
  `woocommerce_payment_complete` no generan duplicados.

### Commits

```text
refactor: agregar estados explicitos de entrega de documentos SAIT
feat: encolar envios SAIT mediante Action Scheduler
feat: reintentar envios SAIT fallidos con proteccion de idempotencia
feat: mostrar estado de entrega SAIT en la administracion WooCommerce
```

## Etapa 8: Separar Manejadores De Eventos

Objetivo: convertir la clase grande de eventos en router y handlers pequeños.

- [ ] Crear parser XML que valide estructura y produzca datos normalizados.
- [ ] Mantener un router sin reglas de negocio.
- [ ] Extraer handler de productos.
- [ ] Extraer handler de precios.
- [ ] Extraer handler de existencias.
- [ ] Extraer handler de categorías.
- [ ] Extraer handler de clientes.
- [ ] Extraer handler de tipo de cambio.
- [ ] Preservar HTTP 200 para eventos recibidos pero no aplicables.
- [ ] Reservar errores HTTP para autenticación, formato o fallos reales.
- [ ] Ejecutar todos los fixtures después de extraer cada handler.

### Commits

```text
refactor: agregar parser validado de eventos XML SAIT
refactor: extraer procesador de eventos de productos SAIT
refactor: extraer procesadores de precios y existencias SAIT
refactor: extraer procesador de categorias SAIT
refactor: extraer procesador de clientes SAIT
refactor: extraer procesador de tipo de cambio SAIT
refactor: reducir el procesador de eventos SAIT a enrutamiento
```

## Etapa 9: Separar Frontend Y Medir Rendimiento

Objetivo: retirar UI de `SAIT_UTILS.php` y reducir llamadas remotas en requests.

### Medición obligatoria

- [ ] Medir primero catálogo, producto, carrito y checkout en staging.
- [ ] Mantener misma URL, usuario, caché y datos para comparar.
- [ ] Registrar cantidad y duración de llamadas HTTP a SAIT.
- [ ] Usar varias muestras y comparar medianas.

### Extracción

- [ ] Crear módulos de selector de sucursal, promociones, existencias y mínimo.
- [ ] Mover HTML a templates con escape tardío.
- [ ] Cargar assets sólo cuando la función correspondiente esté habilitada.
- [ ] Persistir sucursal de visitantes mediante sesión/cookie, no user ID `0`.

### Rendimiento

- [ ] Evitar consultas SAIT por cada render de precio.
- [ ] Definir claves de caché con cliente, SKU, almacén y reglas relevantes.
- [ ] Definir expiración e invalidación explícitas.
- [ ] Evitar cachés sin límite.
- [ ] Agrupar o precargar consultas cuando sea posible.
- [ ] Mover trabajo remoto pesado fuera del request.
- [ ] Revisar tamaño y autoload de opciones de estado.
- [ ] Medir nuevamente antes de declarar mejora.

### Commits

```text
test: agregar lineas base de rendimiento del frontend
refactor: extraer seleccion de sucursal y modulos del frontend
fix: persistir seleccion de sucursal para clientes invitados
perf: reducir llamadas a la API SAIT durante solicitudes web
```

Cada optimización debe incluir medición antes/después en su PR o documentación.

## Etapa 10: Adoptar PHPStan Gradualmente

Objetivo: impedir nuevas regresiones de tipos sin intentar limpiar todo de golpe.

- [ ] Aprobar primero la incorporación de Composer y dependencias de desarrollo.
- [ ] Añadir stubs compatibles de WordPress y WooCommerce.
- [ ] Limitar análisis a código propio.
- [ ] Excluir vendor, assets generados y fixtures cuando corresponda.
- [ ] Empezar en un nivel conservador.
- [ ] Generar una baseline inicial sólo para deuda heredada.
- [ ] No permitir errores nuevos en la baseline.
- [ ] Agregar tipos precisos para callbacks de hooks.
- [ ] Agregar tipos de parámetros de `WP_REST_Request`.
- [ ] Documentar shapes de resultados de base de datos y Action Scheduler.
- [ ] Reducir la baseline módulo por módulo después de cada extracción.

### Commits

```text
build: agregar herramientas de analisis estatico para WordPress y WooCommerce
test: agregar linea base revisada de PHPStan para codigo legado
refactor: agregar tipos en limites REST y hooks de SAIT
```

## Etapa 11: Personalizaciones Y Compatibilidad

Objetivo: actualizar el núcleo sin sobrescribir reglas particulares.

- [ ] Inventariar personalizaciones por cliente.
- [ ] Crear hooks/filtros con contratos documentados.
- [ ] Mover personalizaciones a un plugin complementario cuando sea posible.
- [ ] Mantener adaptadores de clases y funciones históricas durante una versión.
- [ ] Marcar adaptadores obsoletos antes de retirarlos.
- [ ] No eliminar código sólo porque parezca no utilizado; buscar consumidores.

### Commits

```text
feat: agregar puntos de extension para integraciones de clientes
refactor: sacar personalizaciones de clientes del nucleo del plugin
refactor: agregar adaptadores de compatibilidad para APIs heredadas
```

## Etapa 12: Liberacion Y Despliegue

- [ ] Ejecutar PHP lint, pruebas y PHPStan.
- [ ] Instalar desde cero en staging.
- [ ] Actualizar una copia real de una instalación anterior en staging.
- [ ] Confirmar conservación de `opciones_sait` y `sait_claves`.
- [ ] Probar todos los fixtures XML.
- [ ] Probar pedido pagado, sin pago y cotización.
- [ ] Probar cliente normal, eventual y nuevo.
- [ ] Probar reenvío manual autorizado y rechazo no autorizado.
- [ ] Probar producto existente por SKU sin mapeo.
- [ ] Probar almacén único, múltiples almacenes y tipo de cambio.
- [ ] Probar promociones, sucursal y mínimo de carrito.
- [ ] Revisar logs para confirmar que no contienen secretos.
- [ ] Actualizar documentación, changelog y versión.
- [ ] Crear el ZIP desde un árbol limpio.
- [ ] Inspeccionar que el ZIP no incluya tests, `.git`, `.codex` ni copias
  históricas.
- [ ] Respaldar base de datos y plugin antes de producción.
- [ ] Definir plan de rollback.

### Commit de liberacion

```text
chore: preparar version X.Y.Z de SAIT WooCommerce
```

Crear el tag sólo después de validar exactamente el ZIP que se instalará:

```text
vX.Y.Z
```

## Criterios De Bloqueo

No avanzar de etapa si ocurre alguno de estos casos:

- [ ] No están definidas las versiones objetivo.
- [ ] El payload cambia sin decisión explícita.
- [ ] Un fixture deja de producir el resultado esperado.
- [ ] Se crea un producto, pedido o tarea duplicada.
- [ ] Un cliente normal se envía como eventual o viceversa.
- [ ] Se pierde una opción o un mapeo existente.
- [ ] Un endpoint sensible queda público.
- [ ] Una optimización no tiene medición comparable.
- [ ] PHPStan oculta errores nuevos mediante una baseline creciente.
- [ ] Una migración destructiva carece de respaldo y rollback.

## Orden Recomendado De Ejecucion

1. Etapas 0 y 1: compatibilidad, contratos y pruebas.
2. Etapa 2: seguridad y contrato REST.
3. Etapas 3 y 4: bootstrap e infraestructura.
4. Etapas 5 y 6: clientes, documentos y productos.
5. Etapas 7 y 8: entregas confiables y handlers.
6. Etapa 9: frontend y rendimiento medido.
7. Etapas 10 y 11: análisis estático y extensibilidad.
8. Etapa 12: staging, paquete y liberación.
