#!/bin/sh
set -eu

compose_file="tests/docker-compose.yml"

wp_run() {
	docker compose -f "$compose_file" run --rm -T --no-deps wpcli "$@"
}

anonymous_meta() {
	wp_run eval '
		global $wpdb;
		echo wp_json_encode(
			$wpdb->get_results(
				$wpdb->prepare(
					"SELECT umeta_id, meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s ORDER BY umeta_id",
					0,
					"sucursal_seleccionada"
				),
				ARRAY_A
			)
		);
	'
}

work_dir="$(mktemp -d)"
cookie_file="$work_dir/cookies"
trap 'rm -rf "$work_dir"' EXIT HUP INT TERM

nonce="$(wp_run eval 'echo wp_create_nonce("sait-woocommerce_nonce");')"
before="$(anonymous_meta)"
response="$(
	curl -sS \
		--cookie "$cookie_file" \
		--cookie-jar "$cookie_file" \
		--data-urlencode 'action=guardar_sucursal' \
		--data-urlencode 'sucursal_id=2' \
		--data-urlencode "nonce=$nonce" \
		http://localhost:8888/wp-admin/admin-ajax.php
)"
after="$(anonymous_meta)"

if [ "$response" != '{"success":true,"data":2}' ]; then
	echo "Respuesta AJAX inesperada al guardar sucursal invitada: $response" >&2
	exit 1
fi

if ! grep -q 'wp_woocommerce_session_' "$cookie_file"; then
	echo 'WooCommerce no entregó una cookie de sesión al visitante.' >&2
	exit 1
fi

if [ "$before" != "$after" ]; then
	echo 'La selección invitada modificó metadatos históricos del usuario 0.' >&2
	exit 1
fi

echo 'Sucursal invitada persistida en sesión WooCommerce sin usar el usuario 0.'
