#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

COMPOSE_PROJECT_NAME="${COMPOSE_PROJECT_NAME:-paintings-hall}"

docker compose -p "${COMPOSE_PROJECT_NAME}" run -T --rm --no-deps app \
    sh -c 'find /var/www/html -name "*.php" -print0 | xargs -0 -n1 php -l'
