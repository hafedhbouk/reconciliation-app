#!/bin/sh
set -e

# Wait for database to be ready
# Usage: wait-for-db.sh <host> <port> <user> <password>

HOST="${1:-mysql}"
PORT="${2:-3306}"
USER="${3:-root}"
PASSWORD="${4:-}"
MAX_RETRIES=30
RETRY_INTERVAL=2

echo "Waiting for database at ${HOST}:${PORT}..."

RETRY_COUNT=0
while [ $RETRY_COUNT -lt $MAX_RETRIES ]; do
    if mysqladmin ping -h"$HOST" -P"$PORT" -u"$USER" -p"$PASSWORD" --silent 2>/dev/null; then
        echo "Database is ready!"
        exit 0
    fi

    RETRY_COUNT=$((RETRY_COUNT + 1))
    echo "Database not ready, retrying in ${RETRY_INTERVAL}s... (${RETRY_COUNT}/${MAX_RETRIES})"
    sleep $RETRY_INTERVAL
done

echo "ERROR: Database connection timed out after ${MAX_RETRIES} retries"
exit 1
