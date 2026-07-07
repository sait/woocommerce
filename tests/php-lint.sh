#!/bin/sh
set -eu

find sait-woocommerce -type f -name '*.php' -print | sort | while IFS= read -r file; do
  php -l "$file"
done
