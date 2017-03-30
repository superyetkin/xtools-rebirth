#!/bin/bash
set -x

METADB="xtools_meta"
echo "Creating database: $METADB"
PASSARG=""
if [ -n "$DBPASS" ]; then
    PASSARG=" -p$DBPASS -proot "
fi
mysql "$PASSARG" -e "DROP DATABASE IF EXISTS $METADB"
mysql "$PASSARG" -e "CREATE DATABASE $METADB"
SQLFILE=$(cd $(dirname "$0"); pwd -P)"/meta-db.sql"
echo "Importing $SQLFILE"
cat $SQLFILE | mysql "$PASSARG" "$METADB"
echo "Inserting data into $METADB"
mysql "$PASSARG" -e "INSERT INTO $METADB.wiki SET dbname='xtools_wiki1', name='xTools Test Wiki 1', family='testing', url='http://localhost/xtools_wiki1';"
