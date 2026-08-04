#!/bin/sh
set -eu

docker compose -f tests/docker-compose.yml run --rm -T --no-deps wpcli \
	eval-file /var/www/html/wp-content/sait-test-integration/test-order-delivery.php
