#!/usr/bin/env bash
# Sincroniza o banco local (impaktomidia) com uma copia completa da producao (UOLHost).
# Uso (Git Bash / Laragon): bash database/sync-producao-para-local.sh
set -euo pipefail

MYSQL_BIN="/c/laragon/bin/mysql/mysql-8.4.3-winx64/bin"
MYSQLDUMP="$MYSQL_BIN/mysqldump.exe"
MYSQL="$MYSQL_BIN/mysql.exe"

PROD_HOST="a16-asgard8.hospedagemuolhost.com.br"
PROD_PORT="3306"
PROD_USER="impaktom14266d67_ipk"
PROD_DB="impaktom14266d67_ipk2024"

LOCAL_DB="impaktomidia"

echo "=== Sincronizar Producao -> Local ==="
echo "Isso vai APAGAR o banco local '$LOCAL_DB' e recria-lo como copia exata da producao ($PROD_DB)."
read -r -p "Digite SIM para confirmar: " CONFIRM
if [ "$CONFIRM" != "SIM" ]; then
  echo "Cancelado."
  exit 1
fi

read -r -s -p "Senha do MySQL de producao ($PROD_USER): " PROD_PASS
echo

TMPDIR=$(mktemp -d)
CRED_FILE="$TMPDIR/prod.cnf"
DUMP_FILE="$TMPDIR/dump.sql"
trap 'rm -rf "$TMPDIR"' EXIT

cat > "$CRED_FILE" <<EOF
[client]
host=$PROD_HOST
port=$PROD_PORT
user=$PROD_USER
password=$PROD_PASS
ssl-mode=DISABLED
EOF
chmod 600 "$CRED_FILE"
unset PROD_PASS

echo "Gerando dump da producao..."
"$MYSQLDUMP" --defaults-extra-file="$CRED_FILE" \
  --single-transaction --routines --triggers --column-statistics=0 --no-tablespaces \
  "$PROD_DB" > "$DUMP_FILE"

echo "Recriando banco local '$LOCAL_DB'..."
"$MYSQL" -u root -e "DROP DATABASE IF EXISTS $LOCAL_DB; CREATE DATABASE $LOCAL_DB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

echo "Importando dump no local..."
"$MYSQL" -u root "$LOCAL_DB" < "$DUMP_FILE"

echo "Validando contagem de registros (producao x local)..."
TABLES=$("$MYSQL" --defaults-extra-file="$CRED_FILE" -N -e "SHOW TABLES" "$PROD_DB" | tr -d '\r')
ALL_OK=true
for T in $TABLES; do
  PROD_COUNT=$("$MYSQL" --defaults-extra-file="$CRED_FILE" -N -e "SELECT COUNT(*) FROM \`$T\`" "$PROD_DB" | tr -d '\r')
  LOCAL_COUNT=$("$MYSQL" -u root -N -e "SELECT COUNT(*) FROM \`$T\`" "$LOCAL_DB" | tr -d '\r')
  if [ "$PROD_COUNT" = "$LOCAL_COUNT" ]; then
    echo "  OK   $T: $LOCAL_COUNT"
  else
    echo "  DIVERGENTE $T: local=$LOCAL_COUNT producao=$PROD_COUNT"
    ALL_OK=false
  fi
done

if [ "$ALL_OK" = true ]; then
  echo "Sincronizacao concluida com sucesso."
else
  echo "Sincronizacao concluida com divergencias — verifique acima." >&2
  exit 2
fi
