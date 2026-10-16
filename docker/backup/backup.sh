#!/bin/sh
# Cadangan harian MariaDB Keuangan FKIP Unsil (runbook: docs/runbook-cadangan.md).
# Hanya basis data: berkas bukti berada di Google Drive unit, server tidak menyimpan berkas pengguna.
#
# Variabel: DB_HOST, DB_PORT (3306), DB_DATABASE, DB_USERNAME, DB_PASSWORD,
#           BACKUP_DIR (/backup/mysql), RETENSI_HARI (30).
set -eu

: "${DB_HOST:?DB_HOST wajib diisi}"
: "${DB_DATABASE:?DB_DATABASE wajib diisi}"
: "${DB_USERNAME:?DB_USERNAME wajib diisi}"
: "${DB_PASSWORD:?DB_PASSWORD wajib diisi}"
DB_PORT="${DB_PORT:-3306}"
BACKUP_DIR="${BACKUP_DIR:-/backup/mysql}"
RETENSI_HARI="${RETENSI_HARI:-30}"

case "$RETENSI_HARI" in
    ''|*[!0-9]*) echo "RETENSI_HARI harus bilangan bulat" >&2; exit 2 ;;
esac

mkdir -p "$BACKUP_DIR"
STEMPEL="$(date +%Y-%m-%d-%H%M%S)"
BERKAS="$BACKUP_DIR/keuangan-$STEMPEL.sql.gz"
SEMENTARA="$BERKAS.part"

# Kata sandi lewat berkas opsi sementara agar tidak tampak di daftar proses.
OPSI="$(mktemp)"
trap 'rm -f "$OPSI" "$SEMENTARA"' EXIT
chmod 600 "$OPSI"
printf '[client]\nhost=%s\nport=%s\nuser=%s\npassword=%s\n' "$DB_HOST" "$DB_PORT" "$DB_USERNAME" "$DB_PASSWORD" > "$OPSI"

# pipefail tidak tersedia di semua sh: status mariadb-dump diperiksa lewat berkas penanda.
GAGAL="$(mktemp)"
trap 'rm -f "$OPSI" "$SEMENTARA" "$GAGAL"' EXIT
{ mariadb-dump --defaults-extra-file="$OPSI" --single-transaction --routines --triggers --no-tablespaces \
    --default-character-set=utf8mb4 "$DB_DATABASE" || echo gagal > "$GAGAL"; } | gzip -9 > "$SEMENTARA"

if [ -s "$GAGAL" ]; then
    echo "mariadb-dump gagal" >&2
    exit 1
fi

gzip -t "$SEMENTARA"
mv "$SEMENTARA" "$BERKAS"
( cd "$BACKUP_DIR" && sha256sum "$(basename "$BERKAS")" > "$(basename "$BERKAS").sha256" )

find "$BACKUP_DIR" -maxdepth 1 -type f \( -name 'keuangan-*.sql.gz' -o -name 'keuangan-*.sql.gz.sha256' \) -mtime +"$RETENSI_HARI" -delete

echo "Cadangan selesai: $BERKAS"
