#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

: "${DEPLOY_HOST:?DEPLOY_HOST is required}"
DEPLOY_USER="${DEPLOY_USER:-ubuntu}"
DEPLOY_PATH="${DEPLOY_PATH:-/home/${DEPLOY_USER}/paintings-hall}"

SSH_OPTS=(-o StrictHostKeyChecking=accept-new -o ConnectTimeout=15)

echo "Deploying to ${DEPLOY_USER}@${DEPLOY_HOST}:${DEPLOY_PATH}"

ssh "${SSH_OPTS[@]}" "${DEPLOY_USER}@${DEPLOY_HOST}" "mkdir -p '${DEPLOY_PATH}'"

scp "${SSH_OPTS[@]}" Dockerfile docker-compose.yml .dockerignore .env.example \
    "${DEPLOY_USER}@${DEPLOY_HOST}:${DEPLOY_PATH}/"

scp "${SSH_OPTS[@]}" -r src init-db monitoring \
    "${DEPLOY_USER}@${DEPLOY_HOST}:${DEPLOY_PATH}/"

ssh "${SSH_OPTS[@]}" "${DEPLOY_USER}@${DEPLOY_HOST}" "cd '${DEPLOY_PATH}' && \
    if [ ! -f .env ]; then \
        echo 'ERROR: ${DEPLOY_PATH}/.env is missing. Create it from .env.example with production credentials.' >&2; \
        exit 1; \
    fi && \
    docker compose up -d --build"

echo "Deployment completed."
