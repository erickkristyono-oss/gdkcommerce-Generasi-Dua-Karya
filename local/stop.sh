#!/usr/bin/env bash
# Hentikan website & database lokal GDK Commerce (data tetap tersimpan di local/data)
cd "$(dirname "$0")"
[ -f php.pid ] && kill "$(cat php.pid)" 2>/dev/null && rm -f php.pid && echo "Web server dihentikan."
mysqladmin -uroot -h127.0.0.1 -P3407 shutdown 2>/dev/null && echo "MySQL lokal dihentikan."
true
