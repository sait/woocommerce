#!/bin/sh
set -eu

sh tests/test-api-mock.sh
sh tests/test-client.sh
sh tests/test-mappings.sh
sh tests/test-logger.sh
sh tests/test-settings.sh
sh tests/test-product-calculators.sh
sh tests/test-rest.sh
sh tests/test-events.sh
sh tests/test-documents.sh
sh tests/smoke-test.sh
