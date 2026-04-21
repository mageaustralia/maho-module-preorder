#!/bin/sh
set -e

# Maho's app root is /app (BP = /app, app code lives in /app/app/)
# The ./.data/app volume gives us a writable /app/app directory.
# On first boot we seed it with the required subdirectory structure and
# overlay our module source files.

APP_DIR=/app/app
SRC=/mnt/preorder-src

# ── 1. Bootstrap /app/app skeleton if this is a fresh volume ────────────────
if [ ! -d "$APP_DIR/etc" ]; then
  echo "[overlay] Seeding /app/app skeleton..."
  mkdir -p \
    "$APP_DIR/code/community" \
    "$APP_DIR/code/local" \
    "$APP_DIR/design/frontend/base/default" \
    "$APP_DIR/design/adminhtml/default/default" \
    "$APP_DIR/etc/modules" \
    "$APP_DIR/locale/en_US"
fi

# ── 2. Overlay our module ────────────────────────────────────────────────────
if [ -d "$SRC" ]; then
  echo "[overlay] Copying Mageaustralia_Preorder module files..."

  # Module PHP code
  mkdir -p "$APP_DIR/code/local/Mageaustralia/Preorder"
  cp -r "$SRC/app/code/local/Mageaustralia/Preorder/." \
        "$APP_DIR/code/local/Mageaustralia/Preorder/"

  # Module declaration XML
  cp "$SRC/app/etc/modules/Mageaustralia_Preorder.xml" \
     "$APP_DIR/etc/modules/Mageaustralia_Preorder.xml"

  # Locale translations
  cp "$SRC/app/locale/en_US/Mageaustralia_Preorder.csv" \
     "$APP_DIR/locale/en_US/Mageaustralia_Preorder.csv"

  # Design overrides (optional - only if present in src)
  if [ -d "$SRC/app/design" ]; then
    cp -r "$SRC/app/design/." "$APP_DIR/design/" 2>/dev/null || true
  fi

  echo "[overlay] Module overlay complete."
fi

# ── 3. Install Maho if no local.xml yet ─────────────────────────────────────
if [ ! -f "$APP_DIR/etc/local.xml" ]; then
  echo "[overlay] No local.xml found — waiting for DB and running installer..."

  # Wait up to 60s for MariaDB to accept connections
  i=0
  until php -r "new PDO('mysql:host=db;dbname=maho', 'maho', 'maho');" 2>/dev/null; do
    i=$((i+1))
    if [ $i -ge 24 ]; then
      echo "[overlay] ERROR: DB not reachable after 60s — aborting." >&2
      exit 1
    fi
    echo "[overlay] Waiting for DB ($i/24)..."
    sleep 2.5
  done

  echo "[overlay] DB ready. Running maho install..."
  cd /app
  php /app/maho install \
    --license_agreement_accepted=yes \
    --locale=en_AU \
    --timezone=Australia/Sydney \
    --default_currency=AUD \
    --db_host=db \
    --db_name=maho \
    --db_user=maho \
    --db_pass=maho \
    --db_engine=mysql \
    --session_save=files \
    --admin_frontname=admin \
    --url=http://localhost:8080/ \
    --use_secure=false \
    --secure_base_url=http://localhost:8080/ \
    --use_secure_admin=false \
    --admin_lastname=Admin \
    --admin_firstname=Admin \
    --admin_email=admin@example.com \
    --admin_username=admin \
    --admin_password=Admin1234_local

  echo "[overlay] Maho install complete."
fi

# ── 4. Hand off to FrankenPHP's normal entrypoint ───────────────────────────
# The original image CMD is: --config /etc/frankenphp/Caddyfile --adapter caddyfile
# docker-php-entrypoint detects leading "-" args and prepends "frankenphp run".
if [ "$#" -eq 0 ]; then
  exec /usr/local/bin/docker-php-entrypoint \
    --config /etc/frankenphp/Caddyfile \
    --adapter caddyfile
else
  exec /usr/local/bin/docker-php-entrypoint "$@"
fi
