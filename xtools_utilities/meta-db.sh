#!/bin/bash

METADB="xtools_meta"
echo "Creating meta database"
PASSARG=""
if [ -n "$DBPASS" ]; then
    PASSARG="-p$DBPASS"
fi
mysql "$PASSARG" -uroot -e "DROP DATABASE IF EXISTS $METADB"
mysql "$PASSARG" -uroot -e "CREATE DATABASE $METADB"
SQLFILE=$(cd $(dirname "$0"); pwd -P)"/meta-db.sql"
mysql "$PASSARG" -uroot $METADB < "$SQLFILE"
mysql "$PASSARG" -uroot -e "INSERT INTO wiki SET dbname='xtools_wiki1', name='xTools Test Wiki 1', family='testing', url='http://localhost/xtools_wiki1';" $METADB
