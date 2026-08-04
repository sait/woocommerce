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
- La etapa 10 incorpora Composer y PHPStan únicamente como herramientas de
  desarrollo; no se detectan dependencias Composer de ejecución, `package.json`,
  PHPUnit, `wp-env`, Playwright o Jest.
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
  - [x] Construcción de payloads de pedidos y cotizaciones.
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

- [x] Caracterizar namespace, discovery, metodos, permisos publicos actuales,
  token, XML invalido, alias legacy y orden inexistente antes de mover rutas.

- [x] Crear controladores REST separados del archivo principal.
- [x] Conservar temporalmente el namespace `saitplugin/v1`.
- [x] Usar constantes `WP_REST_Server` para los métodos.
- [x] Declarar `args` para IDs y otros parámetros.
- [x] Validar y sanitizar mediante el objeto `WP_REST_Request`.
- [ ] Normalizar éxitos con `rest_ensure_response()` o `WP_REST_Response`.
- [ ] Normalizar fallos reales con `WP_Error` y estado explícito.
- [x] Mantener `POST /saitevents` público a nivel WordPress únicamente porque
  utiliza token externo, pero comparar el token de forma segura.
- [x] Proteger `POST /reenviar-pedido-sait/{idpedido}` con autenticación,
  capacidad y autorización sobre la orden.
- [x] Conservar `/testpedido/{idpedido}` como alias de transicion protegido por
  los mismos permisos.
- [x] Agregar pruebas para 400, 401, 403, 404 y casos exitosos.
- [x] Verificar `OPTIONS` y descubrimiento de rutas.

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

- [x] Mantener un único archivo principal con encabezado del plugin.
- [x] Registrar activación y desactivación en el nivel superior.
- [x] Crear una clase `Plugin` o `Loader` para registrar hooks.
- [x] Evitar llamadas HTTP, HTML y trabajo pesado al cargar archivos.
- [x] Cargar administración sólo en contexto administrativo.
- [x] Crear una versión de esquema guardada en opciones.
- [x] Crear rutina idempotente de actualización de esquema.
- [x] Definir politica explicita de desactivacion; no borrar datos.
- [x] Crear un objeto `Settings` que encapsule `opciones_sait`.
- [x] Mantener los nombres históricos de las opciones.
- [x] Centralizar defaults, booleanos y lista de almacenes.
- [x] Mantener Settings API con sanitización por campo.
- [x] Verificar capacidad `manage_options` y escape tardío de HTML.
- [x] Usar el servicio compartido en pedidos, eventos, REST, frontend y administracion.

### Fuente De Categoria De Productos

- [x] Agregar a `opciones_sait` un selector para elegir la fuente de la
  categoria WooCommerce: linea, familia, categoria o departamento.
- [x] Definir el nombre definitivo de la opcion y sanearla mediante una lista
  cerrada de valores permitidos.
- [x] Confirmar el valor predeterminado y la migracion antes de implementarlo:
  una opcion ausente usa `linea` para conservar el comportamiento de 1.2.3;
  `familia` queda disponible de forma explicita para instalaciones anteriores.
- [x] Centralizar la correspondencia entre la opcion, el atributo de `MODART`
  y la tabla de mapeo de `sait_claves`:
  - [x] Linea: atributo `linea`, tabla `lineas`; evento con clave `numlin`.
  - [x] Familia: atributo `familia`, tabla `familia`; evento con clave `numfam`.
  - [x] Categoria: atributo `categoria`, tabla `catego`; evento con clave `numcat`.
  - [x] Departamento: atributo `numdep`, tabla `deptos`; evento con clave `valdep`.
- [x] Hacer que `MODART` asigne `product_cat` usando la fuente configurada.
- [x] Definir el comportamiento cuando el atributo o el mapeo no existe, sin
  borrar accidentalmente categorias ya asignadas.
- [x] Agregar pruebas para las cuatro fuentes, opcion invalida, valor ausente y
  mapeo inexistente.
- [x] Documentar la opcion y su valor compatible en la guia de configuracion.

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

- [x] Crear `SaitClient` como único adaptador HTTP.
- [x] Centralizar URL, API key, headers, SSL y timeouts.
- [x] Normalizar respuesta válida, `result: null`, JSON inválido, `WP_Error` y
  estados HTTP no exitosos.
- [x] Definir política de reintentos por operación.
- [x] Permitir inyectar un cliente falso en pruebas.
- [x] Conservar adaptadores temporales para `SAIT_GetNube` y `SAIT_PostNube`.

### Repositorio de mapeos

- [x] Crear `MappingRepository` para `sait_claves`.
- [x] Separar búsqueda por clave SAIT y por ID de WooCommerce.
- [x] Usar `$wpdb->prepare()` en todas las consultas.
- [x] Evitar inserciones duplicadas.
- [x] No añadir restricción única hasta auditar datos existentes.
- [x] Definir métodos de productos, clientes y categorías.

### Logging

- [x] Crear logger del plugin sobre `wc_get_logger()`.
- [x] Añadir contexto de evento, orden, SKU e intento.
- [x] No registrar API keys, tokens ni información personal innecesaria.

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

Contrato confirmado: `/clientes` devuelve clientes normales y eventuales en
el campo `numcli`. Un valor que contiene `-` identifica a un cliente eventual;
no se consulta `/clienteseventuales`.

- [x] Crear `CustomerResolver`.
- [x] Resolver en orden:
  - [x] Mapeo local válido.
  - [x] Cliente normal por correo -> `numcli`.
  - [x] Cliente eventual por correo -> `numcliev`.
  - [x] Cliente nuevo -> objeto `clievent`.
- [x] Garantizar que sólo una representación quede activa.
- [x] Probar usuarios registrados, invitados y correos inválidos.

### Construcción de documentos

- [x] Crear builders puros para pedido y cotización.
- [x] Compartir construcción de artículos, dirección, observaciones y cliente.
- [x] Separar construcción del payload y envío HTTP.
- [x] Comparar payloads con los fixtures de la etapa 0.
- [x] Exponer filtros documentados para personalizaciones.

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

- [x] Crear `ProductResolver` que busque por mapeo y luego por SKU.
- [x] Crear `PriceCalculator` sin dependencias de WordPress.
- [x] Crear `StockCalculator` sin dependencias de WordPress.
- [x] Crear `ProductSyncService` que coordine repositorio, WooCommerce y SAIT.
- [x] Definir explícitamente tratamiento de precios y existencias en cero.
- [x] Unificar reglas de lista, impuestos, divisa y tipo de cambio en calculadores compartidos.
- [x] Unificar almacén único y múltiples almacenes en un calculador compartido.
- [x] Reutilizar desde `MODART`, `ACTPRECIO`, `ACTEXIST` y `ACTTC`.
- [x] Reutilizar desde sincronización administrativa manual y por lotes.
- [x] Mantener metadatos de auditoría uniformes en el servicio compartido.

### Cambio funcional aislado

- [x] Vincular un producto WooCommerce existente por SKU cuando falte el mapeo.
- [x] No crear un SKU duplicado.
- [x] Registrar el mapeo sólo después de validar el producto.

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

- [x] Definir estados `pending`, `sending`, `sent` y `failed`.
- [x] Registrar intentos, timestamps, HTTP status y último error.
- [x] No marcar como enviado un POST no bloqueante sin confirmación.
- [x] Programar envíos automáticos con Action Scheduler.
- [x] Hacer cada tarea idempotente antes de enviar.
- [x] Desduplicar acciones programadas.
- [x] Definir reintentos limitados (3) y backoff (60/300 segundos).
- [x] Mantener disparo manual protegido con capacidad y nonce para recuperación.
- [x] Considerar HPOS: usar CRUD de `WC_Order`, no acceso directo a posts.
- [x] Probar que `woocommerce_thankyou` y
  `woocommerce_payment_complete` no generan duplicados.
- [x] Mostrar estado, intentos, último intento y HTTP en administración.

### Commits

```text
refactor: agregar estados explicitos de entrega de documentos SAIT
feat: encolar envios SAIT mediante Action Scheduler
feat: reintentar envios SAIT fallidos con proteccion de idempotencia
feat: mostrar estado de entrega SAIT en la administracion WooCommerce
```

## Etapa 8: Separar Manejadores De Eventos

Objetivo: convertir la clase grande de eventos en router y handlers pequeños.

- [x] Crear parser XML que valide sintaxis y normalice el tipo sin alterar campos SAIT.
- [x] Mantener un router sin reglas de negocio.
- [x] Extraer handler de productos.
- [x] Extraer handler de precios.
- [x] Extraer handler de existencias.
- [x] Extraer handler de categorías.
- [x] Extraer handler de clientes.
- [x] Extraer handler de tipo de cambio.
- [x] Preservar HTTP 200 para eventos recibidos pero no aplicables.
- [x] Reservar errores HTTP para autenticación, formato o fallos reales.
- [x] Ejecutar todos los fixtures después de extraer cada handler.

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
- [x] Agregar una línea base local reproducible con fixtures para las cuatro pantallas.
- [ ] Mantener misma URL, usuario, caché y datos para comparar.
- [x] Fijar URL, visitante, política de caché y datos para comparaciones locales.
- [x] Registrar cantidad y duración simulada de llamadas HTTP a SAIT en Docker.
- [x] Usar varias muestras y comparar medianas en la línea base local.

### Extracción

- [x] Crear módulos de selector de sucursal, promociones, existencias y mínimo.
- [x] Mover HTML a templates con escape tardío.
- [x] Cargar assets sólo cuando la función correspondiente esté habilitada.
- [x] Persistir sucursal de visitantes mediante sesión/cookie, no user ID `0`.

### Rendimiento

- [x] Evitar consultas SAIT por cada render de precio.
- [x] Definir claves de caché con cliente, SKU, almacén y reglas relevantes.
- [x] Definir expiración e invalidación explícitas.
- [x] Evitar cachés sin límite.
- [x] Agrupar o precargar consultas cuando sea posible.
- [x] Evaluar trabajo remoto pesado; mantener precio síncrono porque depende del cliente, sucursal y cantidad actuales.
- [x] Revisar tamaño y autoload de opciones de estado.
- [x] Medir nuevamente antes de declarar mejora.

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

- [x] Aprobar primero la incorporación de Composer y dependencias de desarrollo.
- [x] Añadir stubs compatibles de WordPress y WooCommerce.
- [x] Limitar análisis a código propio.
- [x] Excluir vendor, assets generados y fixtures cuando corresponda.
- [x] Empezar en un nivel conservador.
- [x] Generar una baseline inicial sólo para deuda heredada.
- [x] No permitir errores nuevos en la baseline.
- [x] Agregar tipos precisos para callbacks de hooks.
- [x] Agregar tipos de parámetros de `WP_REST_Request`.
- [x] Documentar shapes de resultados de base de datos y Action Scheduler.
- [x] Reducir la baseline módulo por módulo después de cada extracción.

La baseline inicial contenía 16 hallazgos específicos por mensaje, ruta y
cantidad: cuatro contratos de callbacks/documentación, un método dinámico de
correo de WooCommerce y once referencias a variables inyectadas en plantillas.
Todos se resolvieron en el siguiente commit y la baseline se eliminó; cualquier
hallazgo actual hace fallar directamente el análisis.

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
