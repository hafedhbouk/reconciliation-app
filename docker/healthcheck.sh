#!/bin/sh

# Health check script for Docker container
# Returns 0 if healthy, 1 if unhealthy

# Check if PHP-FPM is running
if ! pgrep -x "php-fpm" > /dev/null 2>&1; then
    echo "UNHEALTHY: PHP-FPM is not running"
    exit 1
fi

# Check if Nginx is running
if ! pgrep -x "nginx" > /dev/null 2>&1; then
    echo "UNHEALTHY: Nginx is not running"
    exit 1
fi

# Check if the application responds
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8080/health 2>/dev/null || echo "000")

if [ "$HTTP_CODE" = "200" ]; then
    echo "HEALTHY: Application is responding"
    exit 0
fi

# Try the main page if health endpoint fails
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8080/ 2>/dev/null || echo "000")

if [ "$HTTP_CODE" = "200" ] || [ "$HTTP_CODE" = "302" ]; then
    echo "HEALTHY: Application is responding (HTTP $HTTP_CODE)"
    exit 0
fi

echo "UNHEALTHY: Application is not responding (HTTP $HTTP_CODE)"
exit 1