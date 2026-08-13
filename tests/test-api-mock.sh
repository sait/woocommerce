#!/bin/sh
set -eu

compose()
{
	docker compose -f tests/docker-compose.yml "$@"
}

compose run --rm -T --no-deps php php -l tests/mu-plugins/sait-api-mock.php

compose run --rm -T --no-deps wpcli eval '
$normal = SAIT_UTILS::SAIT_getClientebyemail("normal.fixture@example.test");
if ($normal !== "  123") {
    throw new RuntimeException("Cliente normal no simulado: " . $normal);
}

$eventual = SAIT_UTILS::SAIT_getClienteEventualbyemail("eventual.fixture@example.test");
if ($eventual !== " -456") {
    throw new RuntimeException("Cliente eventual no simulado: " . $eventual);
}

$unknown = SAIT_UTILS::SAIT_GetNube("/api/v3/no-existe", false);
if ($unknown !== null) {
    throw new RuntimeException("Una ruta desconocida no fue rechazada.");
}

$response = SAIT_UTILS::SAIT_PostNube(
    "/api/v3/pedidos",
    array("numdoc" => "WOFIXTURE"),
    true
);
if (wp_remote_retrieve_response_code($response) !== 201) {
    throw new RuntimeException("El POST simulado no devolvio 201.");
}

$request = get_option("sait_test_last_request");
if (
    $request["path"] !== "/api/v3/pedidos"
    || $request["body"]["numdoc"] !== "WOFIXTURE"
) {
    throw new RuntimeException("No se capturo el payload simulado.");
}
'

echo "Mock SAIT correcto: no se requieren endpoints ni credenciales reales."
