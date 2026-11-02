#!/usr/bin/env bash
# =========================================================
# GDK Commerce — jalankan website di komputer lokal (macOS / Linux)
# Pemakaian:  bash local/start.sh        → buka http://localhost:8000
# Berhenti:   bash local/stop.sh
# Butuh: php (8.1+) dan mysql/mysqld (mis. `brew install php mysql`)
# Data lokal disimpan di folder local/data (terpisah dari MySQL Anda yang lain).
# =========================================================
set -euo pipefail
cd "$(dirname "$0")/.."
ROOT="$(pwd)"
L="$ROOT/local"
DB_PORT=3407
WEB_PORT=${PORT:-8000}
SOCK=/tmp/gdk-local-$DB_PORT.sock
MYSQL="mysql -uroot -h127.0.0.1 -P$DB_PORT"

command -v php >/dev/null || { echo "PHP belum terpasang. Jalankan: brew install php"; exit 1; }
command -v mysqld >/dev/null || { echo "MySQL belum terpasang. Jalankan: brew install mysql"; exit 1; }

# 1. MySQL khusus proyek ini
if [ ! -d "$L/data" ]; then
  echo "→ Menyiapkan database lokal (sekali saja)…"
  mysqld --initialize-insecure --datadir="$L/data" --user="$(whoami)" > "$L/mysql-init.log" 2>&1
fi
if ! mysqladmin -uroot -h127.0.0.1 -P$DB_PORT ping >/dev/null 2>&1; then
  echo "→ Menyalakan MySQL di port ${DB_PORT}…"
  nohup mysqld --datadir="$L/data" --port=$DB_PORT --socket="$SOCK" --mysqlx=OFF --bind-address=127.0.0.1 > "$L/mysql.log" 2>&1 &
  for i in $(seq 1 30); do mysqladmin -uroot -h127.0.0.1 -P$DB_PORT ping >/dev/null 2>&1 && break; sleep 1; done
fi
if ! $MYSQL -e "USE gdk" 2>/dev/null; then
  echo "→ Membuat database & mengisi produk contoh…"
  $MYSQL -e "CREATE DATABASE gdk CHARACTER SET utf8mb4; CREATE USER IF NOT EXISTS 'gdk'@'%' IDENTIFIED BY 'gdk-local'; GRANT ALL ON gdk.* TO 'gdk'@'%';"
  $MYSQL gdk < database/schema.sql
  $MYSQL gdk < database/seed.sql
  $MYSQL gdk < database/seed_digital.sql
fi

# 2. Konfigurasi lokal (mode simulasi: supplier mock + payment dummy)
if [ ! -f "$L/config.php" ]; then
  KEY=$(php -r 'echo bin2hex(random_bytes(32));')
  SECRET=$(php -r 'echo bin2hex(random_bytes(16));')
  sed -e "s/'env'       => 'development',/'env'       => 'development',/" \
      -e "s#'url'       => 'https://domainanda.com'#'url'       => 'http://localhost:$WEB_PORT'#" \
      -e "s/'key'       => '',/'key'       => '$KEY',/" \
      -e "s/'host'     => 'localhost'/'host'     => '127.0.0.1'/" \
      -e "s/'port'     => 3306/'port'     => $DB_PORT/" \
      -e "s/'u123456789_gdk',/'gdk',/g" \
      -e "s/'GANTI_PASSWORD_DATABASE'/'gdk-local'/" \
      -e "s/'GANTI_DENGAN_STRING_ACAK_PANJANG'/'$SECRET'/" \
      api/config.sample.php > "$L/config.php"
  echo "→ Konfigurasi lokal dibuat: local/config.php"
fi

# 3. Web server PHP
if lsof -iTCP:$WEB_PORT -sTCP:LISTEN >/dev/null 2>&1; then
  echo "→ Port $WEB_PORT sudah dipakai (website mungkin sudah berjalan)."
else
  GDK_CONFIG="$L/config.php" nohup php -S 127.0.0.1:$WEB_PORT -t "$ROOT" "$L/router.php" > "$L/php.log" 2>&1 &
  echo $! > "$L/php.pid"
  sleep 1
fi

cat <<MSG

✅ GDK Commerce berjalan di:  http://localhost:$WEB_PORT

   Mode simulasi: supplier mock + pembayaran dummy (tidak ada uang/produk asli).
   Jadikan akun Anda admin (setelah daftar di website):
     GDK_CONFIG=local/config.php php api/cron/make-admin.php email@anda.com
   Lalu buka http://localhost:$WEB_PORT/admin.html (wajib aktifkan 2FA dengan aplikasi authenticator).

   Hentikan: bash local/stop.sh
MSG
