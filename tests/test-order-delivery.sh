#!/bin/sh
set -eu

compose()
{
	docker compose -f tests/docker-compose.yml "$@"
}

integration_dir="/var/www/html/wp-content/sait-test-integration"

compose run --rm -T --no-deps wpcli \
	eval-file "$integration_dir/test-order-delivery.php"

# Cada eval-file se ejecuta en un proceso PHP distinto. Esto reproduce la
# frontera real entre el checkout que prepara una accion y el worker asincrono.
compose run --rm -T --no-deps wpcli \
	eval-file "$integration_dir/prepare-order-delivery-isolated.php"
compose run --rm -T --no-deps wpcli \
	eval-file "$integration_dir/run-order-delivery-isolated.php"
compose run --rm -T --no-deps wpcli \
	eval-file "$integration_dir/assert-order-delivery-isolated.php"
