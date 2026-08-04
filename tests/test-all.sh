#!/bin/sh
set -eu

sh tests/test-api-mock.sh
sh tests/test-rest.sh
sh tests/test-events.sh
sh tests/test-documents.sh
sh tests/smoke-test.sh
