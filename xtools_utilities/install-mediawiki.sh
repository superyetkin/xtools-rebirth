#!/bin/bash
##
## This script installs MediaWiki to ./build/mediawiki
## For more information, see http://xtools.readthedocs.io/en/latest/development.html
##

## Check inputs.
if [ -z $MEDIAWIKI_VERSION ]; then
    echo "You must specify the MEDIAWIKI_VERSION environment variable" 
    exit 1
fi

## Set some paths.
BUILDDIR=$(cd $(dirname "$0")"/../build"; pwd -P)
if [ ! -d $BUILDDIR ]; then
    mkdir "$BUILDDIR"
fi
INSTALLDIR="$BUILDDIR/mediawiki"
if [ -d "$INSTALLDIR" ]; then
    rm -r "$INSTALLDIR"
fi
echo "Installing MediaWiki $MEDIAWIKI_VERSION to $INSTALLDIR"

## Get the required version, and unpack it to `./build/mediawiki`.
if [ ! -s "$BUILDDIR/$MEDIAWIKI_VERSION.tar.gz" ]; then
    wget --directory-prefix="$BUILDDIR" "https://github.com/wikimedia/mediawiki/archive/$MEDIAWIKI_VERSION.tar.gz"
fi
cd "$BUILDDIR"
echo "Unpacking"
tar -zxf "$MEDIAWIKI_VERSION.tar.gz"
mv "mediawiki-$MEDIAWIKI_VERSION" $INSTALLDIR

## Install MediaWiki.
cd "$INSTALLDIR"
WIKIDB="xtools_wiki1"
echo "Creating database as MySQL root user"
PASSARG=""
if [ -n "$DBPASS" ]; then
    PASSARG="-p$DBPASS"
fi
mysql "$PASSARG" -uroot -e "DROP DATABASE IF EXISTS $WIKIDB"
mysql "$PASSARG" -uroot -e "CREATE DATABASE $WIKIDB"
echo "Updating dependencies (Composer)"
composer install
echo "Installing xToolsWiki1 wiki"
php maintenance/install.php --dbtype mysql --dbuser "root" --dbpass "$DBPASS" --dbname $WIKIDB --scriptpath "" --pass admin123 xToolsWiki1 admin
# Add some extra configuration.
cat << 'EOF' >> "$INSTALLDIR/LocalSettings.php"
$wgShowExceptionDetails = true;
$wgCacheDirectory = __DIR__."/images/tmp";
$wgServer = "http://localhost:8081";
$wgUsePathInfo = false;
EOF
