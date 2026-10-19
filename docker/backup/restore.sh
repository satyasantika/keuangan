#!/bin/sh
# Memulihkan dump cadangan ke lingkungan UJI (runbook: docs/runbook-cadangan.md).
# Pemakaian: restore.sh <berkas.sql.gz> [--paksa]
# Menolak berjalan bila APP_ENV=production kecuali --paksa.
set -eu

BERKAS="${1:-}"
PAKSA="${2:-}"

if [ -z "$BERKAS" ] || [ ! -f "$BERKAS" ]; then
    echo "Pemakaian: $0 <berkas.sql.gz> [--paksa]" >&2
    exit 2
fi

if [ "${APP_ENV:-}" = "production" ] && [ "$PAKSA" != "--paksa" ]; then
    echo "Menolak memulihkan ke APP_ENV=production tanpa --paksa." >&2
    exit 3
fi

: "${DB_HOST:?DB_HOST wajib diisi}"
: "${DB_DATABASE:?DB_DATABASE wajib diisi}"
: "${DB_USERNAME:?DB_USERNAME wajib diisi}"
: "${DB_PASSWORD:?DB_PASSWORD wajib diisi}"
DB_PORT="${DB_PORT:-3306}"

if [ -f "$BERKAS.sha256" ]; then
    ( cd "$(dirname "$BERKAS")" && sha256sum -c "$(basename "$BERKAS").sha256" )
fi

gzip -t "$BERKAS"

OPSI="$(mktemp)"
trap 'rm -f "$OPSI"' EXIT
chmod 600 "$OPSI"
printf '[client]\nhost=%s\nport=%s\nuser=%s\npassword=%s\n' "$DB_HOST" "$DB_PORT" "$DB_USERNAME" "$DB_PASSWORD" > "$OPSI"

gunzip -c "$BERKAS" | mariadb --defaults-extra-file="$OPSI" --default-character-set=utf8mb4 "$DB_DATABASE"

echo "Pemulihan selesai ke basis data $DB_DATABASE@$DB_HOST dari $BERKAS"
