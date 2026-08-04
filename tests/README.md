# Tests

Este directorio contiene utilidades ligeras para validar el plugin con Docker.

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

Composer y Node no se incorporan en esta etapa: el primer objetivo es
caracterizar la integracion real. Se reconsideraran cuando las extracciones de
logica pura necesiten PHPUnit, analisis estatico o herramientas JavaScript.

## API SAIT Simulada

Las pruebas no requieren ni permiten credenciales o endpoints reales de API
v3. El MU-plugin `tests/mu-plugins/sait-api-mock.php` intercepta únicamente el
host reservado `https://sait-api.invalid` y responde con
`tests/fixtures/api/responses.json`.

Las llamadas no contempladas al host simulado devuelven `WP_Error`. Los POST a
pedidos y cotizaciones responden HTTP `201` y guardan el request en la opcion
`sait_test_last_request` para poder caracterizar el payload.

La simulacion se verifica con:

```sh
sh tests/test-api-mock.sh
```

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
