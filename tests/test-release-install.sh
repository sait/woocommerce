#!/bin/sh
set -eu

compose_file="tests/release/docker-compose.yml"
site_url="http://localhost:8890"
current_zip="/workspace/dist/sait-woocommerce-2.0.0.zip"
legacy_zip_host="dist/sait-woocommerce-1.2.3-test.zip"
legacy_zip="/workspace/$legacy_zip_host"
legacy_ref="1afa911"

compose() {
	docker compose -f "$compose_file" "$@"
}

wp() {
	compose run --rm -T --no-deps wpcli "$@"
}

cleanup() {
	compose down -v --remove-orphans >/dev/null 2>&1 || true
}
trap cleanup EXIT HUP INT TERM

test -f "dist/sait-woocommerce-2.0.0.zip"
git archive --format=zip --prefix=sait-woocommerce/ --output="$legacy_zip_host" "$legacy_ref":sait-woocommerce

cleanup
compose up -d database wordpress

attempt=1
until compose run --rm -T wpcli core version >/dev/null 2>&1; do
	if [ "$attempt" -ge 30 ]; then
		echo 'WordPress de release no estuvo disponible después de 30 intentos.' >&2
		exit 1
	fi
	attempt=$((attempt + 1))
	sleep 2
done

wp core download --version=6.6.2 --force >/dev/null
wp core install \
	--url="$site_url" \
	--title='SAIT WooCommerce Release Test' \
	--admin_user=admin \
	--admin_password=admin \
	--admin_email=admin@example.test \
	--skip-email >/dev/null
wp plugin install woocommerce --version=9.3.3 --activate >/dev/null
wp option update woocommerce_custom_orders_table_enabled yes >/dev/null

# Actualización desde la última copia 1.2.3 anterior al refactor.
wp plugin install "$legacy_zip" --activate >/dev/null
wp eval '
$options = array(
	"SAITNube_URL" => "https://sait-api.invalid",
	"SAITNube_APIKey" => "release-fixture-key",
	"SAITNube_AccessToken" => "release-fixture-token",
	"SAITNube_NumAlm" => "1",
	"release_marker" => "preservar"
);
update_option("opciones_sait", $options);
global $wpdb;
$wpdb->insert(
	$wpdb->prefix . "sait_claves",
	array("tabla" => "release_test", "clave" => "LEGACY", "wcid" => 987),
	array("%s", "%s", "%d")
);
' >/dev/null
wp plugin install "$current_zip" --force --activate >/dev/null
wp eval '
$plugin = get_plugin_data(WP_PLUGIN_DIR . "/sait-woocommerce/SAIT_WOOCOMMERCE.php", false, false);
if ($plugin["Version"] !== "2.0.0") {
	throw new RuntimeException("La actualización no instaló SAIT WooCommerce 2.0.0.");
}
$options = get_option("opciones_sait", array());
if (!isset($options["release_marker"]) || $options["release_marker"] !== "preservar") {
	throw new RuntimeException("La actualización descartó opciones_sait.");
}
global $wpdb;
$mapping = $wpdb->get_row(
	$wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}sait_claves WHERE tabla = %s AND clave = %s",
		"release_test",
		"LEGACY"
	)
);
if (!$mapping || (int) $mapping->wcid !== 987) {
	throw new RuntimeException("La actualización descartó sait_claves.");
}
if (get_option("sait_woocommerce_db_version") !== "1.0.0") {
	throw new RuntimeException("La actualización no dejó el esquema esperado.");
}
' >/dev/null

# Instalación limpia del mismo ZIP sobre una base de datos nueva.
wp db reset --yes >/dev/null
wp core install \
	--url="$site_url" \
	--title='SAIT WooCommerce Clean Install' \
	--admin_user=admin \
	--admin_password=admin \
	--admin_email=admin@example.test \
	--skip-email >/dev/null
wp plugin activate woocommerce >/dev/null
wp plugin delete sait-woocommerce >/dev/null
wp plugin install "$current_zip" --activate >/dev/null
wp option update woocommerce_custom_orders_table_enabled yes >/dev/null
wp eval '
global $wpdb;
$table = $wpdb->prefix . "sait_claves";
if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
	throw new RuntimeException("La instalación limpia no creó sait_claves.");
}
$plugin = get_plugin_data(WP_PLUGIN_DIR . "/sait-woocommerce/SAIT_WOOCOMMERCE.php", false, false);
if ($plugin["Version"] !== "2.0.0") {
	throw new RuntimeException("La instalación limpia no activó la versión 2.0.0.");
}
' >/dev/null

echo 'Instalación limpia y actualización 1.2.3 -> 2.0.0 validadas en entorno aislado.'
