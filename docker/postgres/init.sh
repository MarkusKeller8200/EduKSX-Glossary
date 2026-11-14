#!/bin/sh
# Erster Start von PostgreSQL im Stack (#357): Zugang der App «glossary» mit Rechten nur auf das Schema «glossary»
# (DB-Standard B5, E2). Läuft nur, wenn das Datenverzeichnis neu ist (docker-entrypoint-initdb.d des Images).
# Verwaltung: Benutzer ${POSTGRES_USER} mit dem Passwort aus /run/secrets/db_admin_password.
set -eu

pw="$(cat /run/secrets/db_password)"
psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" -v pw="$pw" -v db="$POSTGRES_DB" <<'SQL'
create role glossary login password :'pw';
revoke all on database :"db" from public;
grant connect, temporary on database :"db" to glossary;
revoke create on schema public from public;
create schema glossary authorization glossary;
alter role glossary set search_path = glossary;
SQL
echo "[postgres] Zugang «glossary» mit Schema «glossary» angelegt."
