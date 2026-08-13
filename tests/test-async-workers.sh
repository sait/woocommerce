#!/bin/sh
set -eu

compose()
{
	docker compose -f tests/docker-compose.yml "$@"
}

integration_dir="/var/www/html/wp-content/sait-test-integration"

# El callback y su verificacion se ejecutan en procesos PHP distintos.
compose run --rm -T --no-deps wpcli \
	eval-file "$integration_dir/run-art-sync-isolated.php"
compose run --rm -T --no-deps wpcli \
	eval-file "$integration_dir/assert-art-sync-isolated.php"
