#!/bin/sh
set -eu

compose_file="tests/docker-compose.yml"
samples="${SAIT_PERFORMANCE_SAMPLES:-5}"

wp_run() {
	docker compose -f "$compose_file" run --rm -T wpcli "$@"
}

get_url() {
	wp_run option pluck sait_test_performance_urls "$1"
}

reset_sample() {
	wp_run eval-file /var/www/html/wp-content/sait-test-integration/reset-frontend-performance.php >/dev/null
}

get_metrics() {
	wp_run option get sait_test_request_metrics --format=json 2>/dev/null || printf '%s' '{"total_calls":0,"total_duration_ms":0,"by_route":{}}'
}

median() {
	sort -n "$1" | awk '{ values[NR] = $1 } END { if (NR % 2) print values[(NR + 1) / 2]; else printf "%.6f\n", (values[NR / 2] + values[NR / 2 + 1]) / 2 }'
}

measure() {
	name="$1"
	url="$2"
	times_file="$work_dir/$name-times"
	: >"$times_file"

	sample=1
	while [ "$sample" -le "$samples" ]; do
		reset_sample
		result="$(curl -sS -L --cookie "$cookie_file" --cookie-jar "$cookie_file" -o /dev/null --write-out '%{time_starttransfer}\t%{time_total}\t%{http_code}' "$url")"
		start_transfer="$(printf '%s' "$result" | awk -F '\t' '{ print $1 }')"
		printf '%s\n' "$start_transfer" >>"$times_file"
		printf '%s\tmuestra=%s\tttfb=%ss\ttotal/http=%s\tmetricas=%s\n' "$name" "$sample" "$start_transfer" "$(printf '%s' "$result" | awk -F '\t' '{ print $2 "/" $3 }')" "$(get_metrics)"
		sample=$((sample + 1))
	done

	printf '%s\tmediana_ttfb=%ss\tmuestras=%s\n' "$name" "$(median "$times_file")" "$samples"
}

work_dir="$(mktemp -d)"
cookie_file="$work_dir/cookies"
trap 'rm -rf "$work_dir"' EXIT HUP INT TERM

wp_run eval-file /var/www/html/wp-content/sait-test-integration/setup-frontend-performance.php >/dev/null

catalog_url="$(get_url catalogo)"
product_url="$(get_url producto)"
cart_url="$(get_url carrito)"
checkout_url="$(get_url checkout)"
add_to_cart_url="$(get_url agregar_carrito)"

# Todas las páginas se miden como el mismo visitante invitado. El carrito se
# prepara una sola vez y no forma parte de las muestras de carrito/checkout.
curl -sS -L --cookie-jar "$cookie_file" -o /dev/null "$add_to_cart_url"

printf 'escenario\tdetalle\n'
measure catalogo "$catalog_url"
measure producto "$product_url"
measure carrito "$cart_url"
measure checkout "$checkout_url"
