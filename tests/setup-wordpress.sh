#!/bin/sh
set -eu

compose_file="tests/docker-compose.yml"
site_url="http://localhost:8888"

docker compose -f "$compose_file" up -d database wordpress

attempt=1
until docker compose -f "$compose_file" run --rm wpcli core version >/dev/null 2>&1; do
	if [ "$attempt" -ge 30 ]; then
		echo "WordPress no estuvo disponible despues de 30 intentos." >&2
		exit 1
	fi
	attempt=$((attempt + 1))
	sleep 2
done

current_version="$(docker compose -f "$compose_file" run --rm wpcli core version)"
if [ "$current_version" != "6.6.2" ]; then
	docker compose -f "$compose_file" run --rm wpcli core download \
		--version=6.6.2 \
		--force
fi

if ! docker compose -f "$compose_file" run --rm wpcli core is-installed >/dev/null 2>&1; then
	docker compose -f "$compose_file" run --rm wpcli core install \
		--url="$site_url" \
		--title="SAIT WooCommerce Tests" \
		--admin_user=admin \
		--admin_password=admin \
		--admin_email=admin@example.test \
		--skip-email
fi

docker compose -f "$compose_file" run --rm wpcli plugin install woocommerce \
	--version=9.3.3 \
	--activate
docker compose -f "$compose_file" run --rm wpcli plugin activate sait-woocommerce
docker compose -f "$compose_file" run --rm wpcli plugin deactivate sait-woocommerce-papelia >/dev/null 2>&1 || true
docker compose -f "$compose_file" run --rm wpcli option update woocommerce_custom_orders_table_enabled yes
docker compose -f "$compose_file" run --rm wpcli eval '
update_option(
    "opciones_sait",
    array(
        "SAITNube_URL" => "https://sait-api.invalid",
        "SAITNube_APIKey" => "fixture-api-key",
        "SAITNube_AccessToken" => "fixture-access-token",
        "SAITNube_TipoDoc" => "P",
        "SAITNube_NumAlm" => "1",
        "SAITNube_PrecioLista" => "",
        "SAITNube_TipoCambio" => "18.5000"
    )
);
'

docker compose -f "$compose_file" run --rm wpcli core version
docker compose -f "$compose_file" run --rm wpcli plugin get woocommerce --field=version
docker compose -f "$compose_file" run --rm wpcli plugin get sait-woocommerce --fields=name,status,version
docker compose -f "$compose_file" run --rm wpcli plugin get sait-woocommerce-papelia --fields=name,status,version
