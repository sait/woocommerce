#!/bin/sh
set -eu

compose()
{
	docker compose -f tests/docker-compose.yml "$@"
}

wp()
{
	compose run --rm -T --no-deps wpcli "$@"
}

php_version="$(compose exec -T wordpress php -r 'echo PHP_MAJOR_VERSION, ".", PHP_MINOR_VERSION;')"
[ "$php_version" = "7.4" ]

wordpress_version="$(wp core version)"
[ "$wordpress_version" = "6.6.2" ]

woocommerce_version="$(wp plugin get woocommerce --field=version)"
[ "$woocommerce_version" = "9.3.3" ]

wp plugin is-active woocommerce
wp plugin is-active sait-woocommerce
wp plugin is-installed sait-woocommerce-papelia
papelia_status="$(wp plugin get sait-woocommerce-papelia --field=status)"
[ "$papelia_status" = "inactive" ]

hpos_enabled="$(wp option get woocommerce_custom_orders_table_enabled)"
[ "$hpos_enabled" = "yes" ]

schema_version="$(wp option get sait_woocommerce_db_version)"
[ "$schema_version" = "1.0.0" ]

wp eval '
global $wpdb;
$table = $wpdb->prefix . "sait_claves";
if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
    throw new RuntimeException("No existe la tabla sait_claves.");
}
'

wp eval '
do_action("rest_api_init");
$routes = rest_get_server()->get_routes();
$required = array(
    "/saitplugin/v1/hello",
    "/saitplugin/v1/saitevents",
    "/saitplugin/v1/reenviar-pedido-sait/(?P<idpedido>\\d+)",
    "/saitplugin/v1/testpedido/(?P<idpedido>\\d+)",
);
foreach ($required as $route) {
    if (!isset($routes[$route])) {
        throw new RuntimeException("Falta la ruta REST: " . $route);
    }
}
'

compose run --rm -T --no-deps php sh tests/php-lint.sh

if compose exec -T wordpress test -f /var/www/html/wp-content/debug.log; then
	if compose exec -T wordpress grep -E 'PHP (Fatal error|Parse error)' /var/www/html/wp-content/debug.log; then
		echo "Se encontraron errores fatales en debug.log." >&2
		exit 1
	fi
fi

echo "Smoke test correcto: WordPress 6.6.2, WooCommerce 9.3.3, PHP 7.4 y HPOS."
