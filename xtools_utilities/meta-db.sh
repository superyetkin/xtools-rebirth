#!/bin/bash
set -x

METADB="xtools_meta"
echo "Creating database: $METADB"
PASSARG=""
if [ -n "$DBPASS" ]; then
    PASSARG="-p$DBPASS"
fi
mysql "$PASSARG" -uroot -e "DROP DATABASE IF EXISTS $METADB"
mysql "$PASSARG" -uroot -e "CREATE DATABASE $METADB"
SQLFILE=$(cd $(dirname "$0"); pwd -P)"/meta-db.sql"
echo "Importing $SQLFILE"
mysql "$PASSARG" -uroot --database="$METADB" < "$SQLFILE"
echo "Inserting data into $METADB"
mysql "$PASSARG" -uroot --database="$METADB" -e "INSERT INTO $METADB.wiki SET dbname='xtools_wiki1', name='xTools Test Wiki 1', family='testing', url='http://localhost/xtools_wiki1';"
