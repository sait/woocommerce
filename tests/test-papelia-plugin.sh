#!/bin/sh
set -eu

compose_file="tests/docker-compose.yml"

cleanup() {
	docker compose -f "$compose_file" run --rm -T --no-deps wpcli \
		plugin deactivate sait-woocommerce-papelia >/dev/null 2>&1 || true
}
trap cleanup EXIT HUP INT TERM

docker compose -f "$compose_file" run --rm -T --no-deps wpcli \
	plugin activate sait-woocommerce-papelia >/dev/null
docker compose -f "$compose_file" run --rm -T --no-deps wpcli \
	eval-file /var/www/html/wp-content/sait-test-integration/test-papelia-plugin.php
