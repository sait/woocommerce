# Tests

Este directorio contiene utilidades ligeras para validar el plugin con Docker.

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
