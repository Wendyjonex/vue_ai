#!/bin/bash

PORT=${PORT:-8080}

echo "=== Waiting for MySQL... ==="
MAX_RETRIES=30
RETRY=0
while ! mysqladmin ping -h"$MYSQLHOST" -P"$MYSQLPORT" -u"$MYSQLUSER" -p"$MYSQLPASSWORD" --silent 2>/dev/null; do
    RETRY=$((RETRY + 1))
    if [ $RETRY -ge $MAX_RETRIES ]; then
        echo "WARNING: MySQL not reachable after $MAX_RETRIES attempts, starting PHP anyway..."
        break
    fi
    echo "Waiting for MySQL... ($RETRY/$MAX_RETRIES)"
    sleep 2
done

if [ $RETRY -lt $MAX_RETRIES ]; then
    echo "=== MySQL is up! ==="
    if [ -f /app/schema.sql ]; then
        echo "Importing schema (ignore errors if tables exist)..."
        mysql -h"$MYSQLHOST" -P"$MYSQLPORT" -u"$MYSQLUSER" -p"$MYSQLPASSWORD" "$MYSQLDATABASE" < /app/schema.sql 2>/dev/null || echo "Schema import skipped or completed with warnings"
    elif [ -f /app/dump-test_db-202606301837.sql ]; then
        echo "Importing database (ignore errors if tables exist)..."
        mysql -h"$MYSQLHOST" -P"$MYSQLPORT" -u"$MYSQLUSER" -p"$MYSQLPASSWORD" "$MYSQLDATABASE" < /app/dump-test_db-202606301837.sql 2>/dev/null || echo "Import skipped or completed with warnings"
    fi
fi

echo "=== Starting PHP server on port $PORT ==="
exec php -S 0.0.0.0:$PORT