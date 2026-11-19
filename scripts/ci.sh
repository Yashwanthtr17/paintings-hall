#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}" || exit 1

COMPOSE_PROJECT_NAME="paintings-hall-ci"
CI_ENV_FILE=".env.ci"
LOG_FILE="compose-logs.txt"
APP_PORT="${APP_PORT:-18081}"
GRAFANA_PORT="${GRAFANA_PORT:-13000}"
DB_PORT="${DB_PORT:-15432}"
BASE_URL="http://localhost:${APP_PORT}"
GRAFANA_URL="http://localhost:${GRAFANA_PORT}"

# CI uses its own generated credentials, never the local .env.
unset DB_NAME DB_USER DB_PASSWORD GRAFANA_ADMIN_USER GRAFANA_ADMIN_PASSWORD

compose() {
    docker compose -p "${COMPOSE_PROJECT_NAME}" --env-file "${CI_ENV_FILE}" "$@"
}

gen_password() {
    if command -v openssl >/dev/null 2>&1; then
        openssl rand -hex 24
    else
        od -An -N24 -tx1 /dev/urandom | tr -d ' \n'
    fi
}

wait_for() {
    local url="$1"
    local timeout="${2:-120}"
    local elapsed=0

    echo "Waiting for ${url} (timeout: ${timeout}s)..."
    while [ "${elapsed}" -lt "${timeout}" ]; do
        if [ "$(curl -s -o /dev/null -w '%{http_code}' "${url}" 2>/dev/null || true)" = "200" ]; then
            echo "Ready: ${url}"
            return 0
        fi
        elapsed=$((elapsed + 1))
        sleep 1
    done

    echo "ERROR: timed out waiting for ${url}" >&2
    return 1
}

cleanup() {
    status=$?
    trap - EXIT

    echo
    echo "==> Saving container logs"
    {
        compose ps -a
        compose logs --no-color
    } > "${LOG_FILE}" 2>&1 || true

    echo "==> Removing the CI stack"
    compose down -v --remove-orphans >/dev/null 2>&1 || true
    rm -f "${CI_ENV_FILE}"

    exit "${status}"
}
trap cleanup EXIT

echo "==> Checking Docker"
for cmd in docker curl; do
    if ! command -v "${cmd}" >/dev/null 2>&1; then
        echo "ERROR: ${cmd} is required." >&2
        exit 1
    fi
done
docker --version
docker compose version
docker info >/dev/null

echo "==> Creating temporary CI credentials"
umask 077
{
    echo "DB_NAME=paintings_hall-db"
    echo "DB_USER=yashu"
    echo "DB_PASSWORD=$(gen_password)"
    echo "GRAFANA_ADMIN_USER=yashu"
    echo "GRAFANA_ADMIN_PASSWORD=$(gen_password)"
    echo "APP_PORT=${APP_PORT}"
    echo "GRAFANA_PORT=${GRAFANA_PORT}"
    echo "DB_PORT=${DB_PORT}"
} > "${CI_ENV_FILE}"
chmod 600 "${CI_ENV_FILE}"

echo "==> Validating Docker Compose"
compose config --quiet

echo "==> Building the application image"
compose build

echo "==> Checking PHP syntax"
compose run -T --rm --no-deps app \
    sh -c 'find /var/www/html -name "*.php" -print0 | xargs -0 -n1 php -l'

echo "==> Starting the stack"
compose up -d
wait_for "${BASE_URL}" 180
wait_for "${GRAFANA_URL}/api/health" 120

echo "==> Running smoke tests"
ENV_FILE="${CI_ENV_FILE}" \
COMPOSE_ENV_FILE="${CI_ENV_FILE}" \
COMPOSE_PROJECT_NAME="${COMPOSE_PROJECT_NAME}" \
bash "${ROOT_DIR}/tests/smoke.sh"

echo
echo "CI passed."
echo "Logs saved to ${LOG_FILE}"
