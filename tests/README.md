# Tests

Este directorio contiene utilidades ligeras para validar el plugin con Docker.

Después de ejecutar el setup, toda la suite local se lanza con:

```sh
sh tests/test-all.sh
```

## Entorno WordPress Y WooCommerce

El entorno de integracion usa versiones fijas:

- WordPress 6.6.2.
- WooCommerce 9.3.3.
- PHP 7.4.

La imagen oficial combina WordPress 6 con PHP 7.4 y el script actualiza el
volumen a WordPress 6.6.2 antes de instalar o verificar el sitio.

Levanta, instala y activa todo desde la raiz:

```sh
sh tests/setup-wordpress.sh
```

WordPress queda en `http://localhost:8888`. Los comandos utiles son:

- Usuario: `admin`.
- Contraseña: `admin`.

Ejecuta la validacion automatizada del entorno:

```sh
sh tests/smoke-test.sh
```

Otros comandos utiles son:

```sh
docker compose -f tests/docker-compose.yml logs -f wordpress
docker compose -f tests/docker-compose.yml stop
docker compose -f tests/docker-compose.yml down
```

`down` conserva la base de datos y los archivos de WordPress en volumenes
Docker. Para reiniciar los datos se deben eliminar explicitamente los
volumenes `sait_woocommerce_database` y `sait_woocommerce_wordpress`.

La red usa una subred fija para no depender del pool automatico de Docker. Si
`192.168.64.0/24` se superpone con una red local, cambia solamente esa subred
en `tests/docker-compose.yml`.

Node no forma parte del entorno de integración. Composer se incorporó en la
etapa 10 exclusivamente para instalar PHPStan y los stubs de desarrollo; estas
dependencias no se empaquetan ni se cargan al ejecutar el plugin.

## API SAIT Simulada

Las pruebas no requieren ni permiten credenciales o endpoints reales de API
v3. El MU-plugin `tests/mu-plugins/sait-api-mock.php` intercepta únicamente el
host reservado `https://sait-api.invalid` y responde con
`tests/fixtures/api/responses.json`.

Las llamadas no contempladas al host simulado devuelven `WP_Error`. Los POST a
pedidos y cotizaciones responden HTTP `201` y guardan el request en la opcion
`sait_test_last_request` para poder caracterizar el payload.

El mismo MU-plugin intercepta `wp_mail()` para que las pruebas de clientes no
intenten entregar correos.

La simulacion se verifica con:

```sh
sh tests/test-api-mock.sh
```

El cliente HTTP centralizado y su politica de errores/reintentos se validan con:

```sh
sh tests/test-client.sh
```

Esta prueba cubre respuestas validas, `result: null`, JSON invalido,
`WP_Error`, estados HTTP no exitosos, configuracion incompleta e inyeccion de
un cliente falso.

El repositorio de relaciones `sait_claves` se valida con:

```sh
sh tests/test-mappings.sh
```

Se prueban busquedas por clave SAIT e ID WooCommerce, metodos de entidades,
consultas preparadas, adaptadores historicos, eliminacion y prevencion de
duplicados sin agregar un indice unico.

El saneamiento de logs se valida con:

```sh
sh tests/test-logger.sh
```

La prueba confirma que el contexto operativo permitido se conserva y que API
keys, tokens, correos, nombres y payloads son descartados.

La separación del frontend y el registro condicional de hooks/assets se
validan con:

```sh
sh tests/test-frontend-modules.sh
```

La persistencia real por AJAX de una sucursal invitada se verifica con:

```sh
sh tests/test-guest-branch.sh
```

La prueba confirma la cookie de sesión WooCommerce y que no cambien las filas
históricas de `user_id = 0`.

El contexto, padding, caché e invalidación de precios se validan con:

```sh
sh tests/test-price-service.sh
```

La caché distingue usuario/`numcli`, SKU, sucursal, cantidad, divisa y forma de
pago. `numcli = "    0"` representa el precio público compartido.

El plugin complementario de Papelía se monta por separado y permanece inactivo
durante las pruebas del núcleo. Su prueba lo activa temporalmente y valida los
contratos de payload y stock con un cliente inyectado, sin endpoints reales:

```sh
sh tests/test-papelia-plugin.sh
```

La prueba confirma que el complemento modifica `otrosdatos`/`obs`, separa el
stock total del stock por sucursal, reutiliza transients y no desactiva
globalmente las validaciones de stock de WooCommerce.

Los calculos puros y el servicio compartido de productos se validan con:

```sh
sh tests/test-product-calculators.sh
sh tests/test-product-sync.sh
```

Estas pruebas cubren precios, impuestos, divisa, almacenes, valores cero,
resolucion por mapeo/SKU y metadatos uniformes sin usar endpoints reales.

## Caracterizacion De Eventos XML

Los eventos se envian a la ruta REST real del plugin, pero todas sus consultas
a SAIT se resuelven mediante los fixtures locales:

```sh
sh tests/test-events.sh
```

La prueba usa exclusivamente SKUs, terminos y correos con sufijo `Fixture` o
dominio `example.test`. Al comenzar elimina los datos de una ejecucion previa
para que pueda repetirse sobre el volumen Docker.

## Caracterizacion REST

El contrato HTTP 1.2.3 se verifica por separado antes de mover las rutas a
controladores:

```sh
sh tests/test-rest.sh
```

El webhook permanece publico a nivel WordPress y valida el token externo. Los
dos endpoints de reenvio requieren autenticacion y la capacidad
`edit_shop_orders`; las pruebas cubren 400, 401, 403, 404 y un reenvio exitoso.

## Caracterizacion De Documentos

Pedidos y cotizaciones se construyen con ordenes WooCommerce reales dentro del
volumen de pruebas. El POST se captura localmente y nunca sale a SAIT:

```sh
sh tests/test-documents.sh
```

Se cubren clientes normales y eventuales, mapeados o encontrados por correo,
clientes nuevos, correos invalidos, descuentos, observaciones, direccion de
envio y cotizacion. `/clientes` es la unica ruta de busqueda: un `numcli` que
contiene `-` se envia como `numcliev`.

Los estados persistentes de entrega se validan con:

```sh
sh tests/test-order-delivery.sh
```

La prueba cubre `pending`, `sending`, `failed` y `sent`, conteo de intentos,
HTTP/errores y confirma que un POST no bloqueante no se marque como enviado.

## Lint PHP

Desde la raiz del repositorio:

```sh
docker compose -f tests/docker-compose.yml run --rm php sh tests/php-lint.sh
```

Tambien puedes revisar un archivo puntual:

```sh
docker compose -f tests/docker-compose.yml run --rm php php -l sait-woocommerce/SAIT_WOOCOMMERCE.php
```

El contenedor monta el repositorio en `/workspace` y no modifica archivos del plugin.

## Análisis Estático Con PHPStan

Instala las dependencias de desarrollo fijadas en `composer.lock`:

```sh
docker compose -f tests/docker-compose.yml run --rm -T composer install
```

Ejecuta el análisis del código propio del plugin:

```sh
docker compose -f tests/docker-compose.yml run --rm -T composer run phpstan
```

La configuración comienza en nivel 3 y carga stubs compatibles con WordPress
6.6.2 y WooCommerce 9.3.3. `vendor/`, las pruebas y sus fixtures quedan fuera
del análisis; `vendor/` también está excluido de Git y del paquete del plugin.
La baseline inicial se utilizó para revisar la deuda heredada y se eliminó al
resolver sus 16 hallazgos. Actualmente cualquier error hace fallar el comando.

## Línea Base Del Frontend

La etapa de rendimiento cuenta con un escenario local determinista para
catálogo, producto, carrito y checkout:

```sh
sh tests/test-frontend-performance.sh
```

La prueba usa un único visitante invitado, un producto y una categoría
exclusivos. Antes de cada muestra limpia sólo los transients `sait_*`, reinicia
las métricas del mock y conserva el carrito del visitante. Por defecto toma
cinco muestras y muestra la mediana de TTFB de cada pantalla.

El mock agrega 25 ms controlados por llamada y reporta conteo y duración
simulada por ruta. Esa latencia no representa un endpoint real: permite
comparar el antes y el después sin acceder a API v3. Para cambiar la cantidad
de muestras:

```sh
SAIT_PERFORMANCE_SAMPLES=7 sh tests/test-frontend-performance.sh
```
