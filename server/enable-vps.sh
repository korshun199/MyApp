#!/usr/bin/env bash
set -euo pipefail

BASE=/home/work/MyApp/server
NGINX=/etc/nginx/sites-available/systemio

echo "Installing MyApp systemd service..."
install -m 644 "$BASE/myapp.service" /etc/systemd/system/myapp.service

# Stop the temporary manual Gunicorn, if it is still running on port 5050.
for pid in $(ps -eo pid=,args= | awk '$0 ~ /[g]unicorn.*127\.0\.0\.1:5050/ {print $1}'); do
    kill "$pid" || true
done

systemctl daemon-reload
systemctl enable --now myapp

if ! grep -q 'MyApp server include' "$NGINX"; then
    sed -i '/^[[:space:]]*location \/ {/i\    # MyApp server include\n    include /home/work/MyApp/server/nginx-systemio-location.conf;' "$NGINX"
fi

nginx -t
systemctl reload nginx

echo "MyApp is enabled. Testing endpoint..."
curl --http1.1 -fsS -o /dev/null "https://systemio.ru/app?tk=${MYAPP_TOKEN:-2134asd23rferf}"
echo "OK"
