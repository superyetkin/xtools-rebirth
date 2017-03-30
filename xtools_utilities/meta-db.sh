#!/bin/bash
##
## This file is run by Travis when setting up the tests.
##

METADB="xtools_meta"
echo "Creating database: $METADB"
mysql -e "DROP DATABASE IF EXISTS $METADB;"
mysql -e "CREATE DATABASE $METADB;"
SQLFILE=$(cd $(dirname "$0"); pwd -P)"/meta-db.sql"
echo "Importing $SQLFILE"
mysql $METADB < "$SQLFILE"
echo "Inserting data into $METADB"
mysql -e "INSERT INTO $METADB.wiki SET dbname='xtools_wiki1', name='xTools Test Wiki 1', family='testing', url='http://localhost/xtools_wiki1';"
