#!/bin/sh
set -eu

repo_root="$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)"
cd "$repo_root"

core_version="${1:-2.0.0}"
papelia_version="${2:-1.0.0}"
output_dir="dist"
core_zip="$output_dir/sait-woocommerce-$core_version.zip"
papelia_zip="$output_dir/sait-woocommerce-papelia-$papelia_version.zip"

if ! git diff --quiet || ! git diff --cached --quiet; then
	echo 'El árbol rastreado debe estar limpio antes de empaquetar.' >&2
	exit 1
fi

declared_core_version="$(sed -n 's/^Version:[[:space:]]*//p' sait-woocommerce/SAIT_WOOCOMMERCE.php | head -n 1)"
declared_papelia_version="$(sed -n 's/^Version:[[:space:]]*//p' personalizados/sait-woocommerce-papelia/sait-woocommerce-papelia.php | head -n 1)"
if [ "$declared_core_version" != "$core_version" ]; then
	echo "Versión del núcleo inesperada: $declared_core_version" >&2
	exit 1
fi
if [ "$declared_papelia_version" != "$papelia_version" ]; then
	echo "Versión de Papelía inesperada: $declared_papelia_version" >&2
	exit 1
fi

mkdir -p "$output_dir"
git archive --format=zip --prefix=sait-woocommerce/ --output="$core_zip" HEAD:sait-woocommerce
git archive --format=zip --prefix=sait-woocommerce-papelia/ --output="$papelia_zip" HEAD:personalizados/sait-woocommerce-papelia

sh scripts/inspect-release.sh "$core_zip" sait-woocommerce "$core_version" SAIT_WOOCOMMERCE.php
sh scripts/inspect-release.sh "$papelia_zip" sait-woocommerce-papelia "$papelia_version" sait-woocommerce-papelia.php

(
	cd "$output_dir"
	sha256sum "$(basename "$core_zip")" "$(basename "$papelia_zip")" > SHA256SUMS
)

echo "Paquetes creados en $output_dir/:"
echo "- $(basename "$core_zip")"
echo "- $(basename "$papelia_zip")"
echo '- SHA256SUMS'
