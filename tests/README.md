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

Se cubren cliente mapeado, cliente normal encontrado por correo, eventual
existente, cliente nuevo, descuentos, observaciones, direccion de envio y
cotizacion. El eventual existente conserva como expectativa la brecha de
1.2.3: se vuelve a enviar `clievent` en vez de reutilizar `numcliev`.

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
