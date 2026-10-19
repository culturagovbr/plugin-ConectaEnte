#!/usr/bin/env bash
# Roda os testes do plugin em PHP 7.2 (o host não tem php). Argumentos vão ao phpunit.
#   tests/run.sh                          toda a suíte
#   tests/run.sh --filter testSegment     filtra por nome
#   tests/run.sh tests/src/VocabularyTest.php
set -euo pipefail

PLUGIN="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PROTECTED="$(cd "$PLUGIN/../../.." && pwd)"
PLUGIN_IN_CONTAINER="/protected/application/plugins/ConectaEnte"

args=("$@")
has_path=0
for arg in "${args[@]+"${args[@]}"}"; do
    case "$arg" in tests/*) has_path=1 ;; esac
done
if [ "$has_path" -eq 0 ]; then
    args+=("tests/src")
fi

exec docker run --rm \
    -v "$PROTECTED":/protected \
    -w "$PLUGIN_IN_CONTAINER" \
    php:7.2-cli \
    php /protected/vendor/bin/phpunit --bootstrap tests/bootstrap.php "${args[@]}"
