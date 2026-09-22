#!/bin/sh
set -eu

repo_root="$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)"
cd "$repo_root"

if ! git diff --quiet || ! git diff --cached --quiet; then
	echo 'El árbol rastreado debe estar limpio antes de empaquetar.' >&2
	exit 1
fi

declared_core_version="$(sed -n 's/^Version:[[:space:]]*//p' sait-woocommerce/SAIT_WOOCOMMERCE.php | head -n 1)"
declared_papelia_version="$(sed -n 's/^Version:[[:space:]]*//p' personalizados/sait-woocommerce-papelia/sait-woocommerce-papelia.php | head -n 1)"
declared_fysson_version="$(sed -n 's/^Version:[[:space:]]*//p' personalizados/sait-woocommerce-fysson/sait-woocommerce-fysson.php | head -n 1)"
core_version="${1:-$declared_core_version}"
papelia_version="${2:-$declared_papelia_version}"
fysson_version="${3:-$declared_fysson_version}"

if [ "$declared_core_version" != "$core_version" ]; then
	echo "Versión del núcleo inesperada: $declared_core_version" >&2
	exit 1
fi
if [ "$declared_papelia_version" != "$papelia_version" ]; then
	echo "Versión de Papelía inesperada: $declared_papelia_version" >&2
	exit 1
fi
if [ "$declared_fysson_version" != "$fysson_version" ]; then
	echo "Versión de Fysson inesperada: $declared_fysson_version" >&2
	exit 1
fi

output_dir="dist"
core_zip="$output_dir/sait-woocommerce-$core_version.zip"
papelia_zip="$output_dir/sait-woocommerce-papelia-$papelia_version.zip"
fysson_zip="$output_dir/sait-woocommerce-fysson-$fysson_version.zip"

mkdir -p "$output_dir"

last_release="$(git rev-list -n 1 HEAD -- "$output_dir")"

needs_build() { # $1 directorio del paquete en el repo, $2 ZIP esperado
	if [ -z "$last_release" ]; then
		return 0
	fi
	if ! git diff --quiet "$last_release" HEAD -- "$1"; then
		return 0
	fi
	if [ ! -f "$2" ]; then
		return 0
	fi
	return 1
}

git archive --format=zip --prefix=sait-woocommerce/ --output="$core_zip" HEAD:sait-woocommerce
sh scripts/inspect-release.sh "$core_zip" sait-woocommerce "$core_version" SAIT_WOOCOMMERCE.php

if needs_build personalizados/sait-woocommerce-papelia "$papelia_zip"; then
	git archive --format=zip --prefix=sait-woocommerce-papelia/ --output="$papelia_zip" HEAD:personalizados/sait-woocommerce-papelia
	sh scripts/inspect-release.sh "$papelia_zip" sait-woocommerce-papelia "$papelia_version" sait-woocommerce-papelia.php
else
	echo "Papelía sin cambios desde el último release; se conserva $(basename "$papelia_zip")"
fi

if needs_build personalizados/sait-woocommerce-fysson "$fysson_zip"; then
	git archive --format=zip --prefix=sait-woocommerce-fysson/ --output="$fysson_zip" HEAD:personalizados/sait-woocommerce-fysson
	sh scripts/inspect-release.sh "$fysson_zip" sait-woocommerce-fysson "$fysson_version" sait-woocommerce-fysson.php
else
	echo "Fysson sin cambios desde el último release; se conserva $(basename "$fysson_zip")"
fi

(
	cd "$output_dir"
	sha256sum "$(basename "$core_zip")" "$(basename "$papelia_zip")" "$(basename "$fysson_zip")" > SHA256SUMS
)

echo "Paquetes creados en $output_dir/:"
echo "- $(basename "$core_zip")"
echo "- $(basename "$papelia_zip")"
echo "- $(basename "$fysson_zip")"
echo '- SHA256SUMS'
