#!/bin/sh
set -eu

archive="${1:?Falta la ruta del ZIP}"
directory="${2:?Falta el directorio raíz esperado}"
version="${3:?Falta la versión esperada}"
plugin_file="${4:?Falta el archivo principal}"

entries="$(unzip -Z1 "$archive")"
if [ -z "$entries" ]; then
	echo "ZIP vacío: $archive" >&2
	exit 1
fi
if echo "$entries" | grep -Ev "^${directory}/" >/dev/null; then
	echo "El ZIP contiene rutas fuera de ${directory}/" >&2
	exit 1
fi
if echo "$entries" | grep -E '/(tests|vendor|node_modules|\.git|\.codex|\.vscode|papelia|plugins)/' >/dev/null; then
	echo "El ZIP contiene archivos prohibidos: $archive" >&2
	exit 1
fi
if ! echo "$entries" | grep -Fx "${directory}/${plugin_file}" >/dev/null; then
	echo "Falta el archivo principal en $archive" >&2
	exit 1
fi
if ! unzip -p "$archive" "${directory}/${plugin_file}" | grep -Fx "Version: $version" >/dev/null; then
	echo "La versión interna no coincide en $archive" >&2
	exit 1
fi

echo "ZIP validado: $archive"
