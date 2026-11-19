#!/usr/bin/env bash
set -euo pipefail

URL="${1:-http://localhost:8081}"
TIMEOUT="${2:-120}"

echo "Waiting for ${URL} (timeout: ${TIMEOUT}s)..."

ELAPSED=0
while [ "${ELAPSED}" -lt "${TIMEOUT}" ]; do
    CODE="$(curl -s -o /dev/null -w '%{http_code}' "${URL}" 2>/dev/null || true)"
    if [ "${CODE}" = "200" ]; then
        echo "Ready: ${URL} returned HTTP 200."
        exit 0
    fi
    ELAPSED=$((ELAPSED + 1))
    sleep 1
done

echo "Timed out waiting for ${URL}." >&2
exit 1
