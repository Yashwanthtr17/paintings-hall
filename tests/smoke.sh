#!/usr/bin/env bash
set -uo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

if [ -f .env ]; then
    set -a
    # shellcheck disable=SC1091
    . ./.env
    set +a
fi

# Strip CR characters when .env was created on Windows.
strip_cr() { printf '%s' "${1%$'\r'}"; }

DB_NAME="$(strip_cr "${DB_NAME:-paintings_hall-db}")"
DB_USER="$(strip_cr "${DB_USER:-yashu}")"
APP_PORT="$(strip_cr "${APP_PORT:-8081}")"
GRAFANA_PORT="$(strip_cr "${GRAFANA_PORT:-3000}")"
GRAFANA_ADMIN_USER="$(strip_cr "${GRAFANA_ADMIN_USER:-yashu}")"
GRAFANA_ADMIN_PASSWORD="$(strip_cr "${GRAFANA_ADMIN_PASSWORD:-}")"
COMPOSE_PROJECT_NAME="$(strip_cr "${COMPOSE_PROJECT_NAME:-paintings-hall}")"

BASE_URL="${BASE_URL:-http://localhost:${APP_PORT}}"
GRAFANA_URL="${GRAFANA_URL:-http://localhost:${GRAFANA_PORT}}"

PASS=0
FAIL=0

pass() { echo "PASS: $1"; PASS=$((PASS + 1)); }
fail() { echo "FAIL: $1"; FAIL=$((FAIL + 1)); }
assert_eq() { if [ "$2" = "$3" ]; then pass "$1"; else fail "$1 (expected '$3', got '$2')"; fi; }
assert_contains() { if printf '%s' "$2" | grep -q -- "$3"; then pass "$1"; else fail "$1"; fi; }
assert_not_contains() { if printf '%s' "$2" | grep -q -- "$3"; then fail "$1"; else pass "$1"; fi; }

http_code() {
    curl -s -o /dev/null -w '%{http_code}' "$@" 2>/dev/null || true
}

TEST_TITLE="smoke-test-$(date +%s)-$$"

cleanup() {
    if ! docker info >/dev/null 2>&1; then
        echo "WARNING: Docker CLI is unavailable; test row '${TEST_TITLE}' was not removed." >&2
        return
    fi
    docker compose -p "${COMPOSE_PROJECT_NAME}" exec -T db \
        psql -U "${DB_USER}" -d "${DB_NAME}" \
        -c "DELETE FROM paintings WHERE title = '${TEST_TITLE}';" >/dev/null 2>&1 \
        || echo "WARNING: could not remove test row '${TEST_TITLE}'." >&2
}
trap cleanup EXIT

echo "Running smoke tests against ${BASE_URL}"
echo "Grafana: ${GRAFANA_URL}"
echo

assert_eq "Home page returns HTTP 200" "$(http_code "${BASE_URL}/")" "200"

GET_BODY="$(curl -s "${BASE_URL}/paintings.php" || true)"
assert_contains "GET /paintings.php reports success" "${GET_BODY}" '"success":true'

POST_BODY="$(curl -s -X POST "${BASE_URL}/paintings.php" \
    -H 'Content-Type: application/json' \
    -d "{\"title\":\"${TEST_TITLE}\",\"artist\":\"Smoke Test\",\"year\":2026}" || true)"
assert_contains "POST /paintings.php creates a painting" "${POST_BODY}" '"success":true'

GET_AFTER="$(curl -s "${BASE_URL}/paintings.php" || true)"
assert_contains "New painting appears in the gallery API" "${GET_AFTER}" "${TEST_TITLE}"

INVALID_CODE="$(http_code -X POST "${BASE_URL}/paintings.php" \
    -H 'Content-Type: application/json' \
    -d '{"title":"","artist":""}')"
assert_eq "POST rejects missing title/artist with HTTP 400" "${INVALID_CODE}" "400"

BAD_YEAR_CODE="$(http_code -X POST "${BASE_URL}/paintings.php" \
    -H 'Content-Type: application/json' \
    -d '{"title":"Smoke","artist":"Test","year":"not-a-year"}')"
assert_eq "POST rejects a non-numeric year with HTTP 400" "${BAD_YEAR_CODE}" "400"

PUT_CODE="$(http_code -X PUT "${BASE_URL}/paintings.php")"
assert_eq "Unsupported methods return HTTP 405" "${PUT_CODE}" "405"

if [ -n "${GRAFANA_ADMIN_PASSWORD}" ]; then
    GRAFANA_HEALTH="$(curl -s -u "${GRAFANA_ADMIN_USER}:${GRAFANA_ADMIN_PASSWORD}" "${GRAFANA_URL}/api/health" || true)"
    assert_contains "Grafana is healthy" "${GRAFANA_HEALTH}" '"database"[[:space:]]*:[[:space:]]*"ok"'

    DS_HEALTH="$(curl -s -u "${GRAFANA_ADMIN_USER}:${GRAFANA_ADMIN_PASSWORD}" "${GRAFANA_URL}/api/datasources/uid/paintings-postgres/health" || true)"
    assert_contains "Grafana PostgreSQL datasource is healthy" "${DS_HEALTH}" '"status":"OK"'

    DASHBOARDS="$(curl -s -u "${GRAFANA_ADMIN_USER}:${GRAFANA_ADMIN_PASSWORD}" "${GRAFANA_URL}/api/search?query=Paintings%20Hall" || true)"
    assert_contains "Grafana dashboard is provisioned" "${DASHBOARDS}" '"uid":"paintings-hall-overview"'

    DASHBOARD_JSON="$(curl -s -u "${GRAFANA_ADMIN_USER}:${GRAFANA_ADMIN_PASSWORD}" "${GRAFANA_URL}/api/dashboards/uid/paintings-hall-overview" || true)"
    assert_contains "Dashboard contains the Total Paintings panel" "${DASHBOARD_JSON}" '"title":"Total Paintings"'

    FROM_MS=$(( ($(date +%s) - 2592000) * 1000 ))
    TO_MS=$(( $(date +%s) * 1000 ))
    QUERY_BODY="$(cat <<JSON
{"queries":[{"refId":"A","datasource":{"uid":"paintings-postgres"},"rawSql":"SELECT COUNT(*) AS value FROM paintings","format":"table","intervalMs":60000,"maxDataPoints":1000}],"from":"${FROM_MS}","to":"${TO_MS}"}
JSON
)"
    QUERY_RESPONSE="$(curl -s -u "${GRAFANA_ADMIN_USER}:${GRAFANA_ADMIN_PASSWORD}" \
        -H 'Content-Type: application/json' -X POST \
        --data-binary "${QUERY_BODY}" "${GRAFANA_URL}/api/ds/query" || true)"
    assert_contains "Grafana can query the paintings table" "${QUERY_RESPONSE}" '"frames"'
    assert_not_contains "Grafana query returned no API error" "${QUERY_RESPONSE}" 'bad request data'
else
    fail "GRAFANA_ADMIN_PASSWORD is not set; cannot verify Grafana"
fi

echo
echo "Smoke test summary: ${PASS} passed, ${FAIL} failed"
[ "${FAIL}" -eq 0 ]
