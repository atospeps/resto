#!/bin/bash

# recuperation du repertoire du script, n'utiliser que la variable PRG_DIR qui represente le chemin absolu
PRG="$0"
EXEC_DIR=`dirname ${PRG}`
export PRG_DIR=`(cd ${EXEC_DIR} ; echo $PWD)`

REPORT_FILE="$PRG_DIR/checkRestoReport.txt"
if [ -f "$REPORT_FILE" ]
then
    rm "$REPORT_FILE"
fi

function logMessage() {
    echo "$1"
    echo "$1" >> "$REPORT_FILE"
}

function execPsql() {
    scp $1 ${UNIX_USER}@${DHUS_HOST}:$2
    ssh ${UNIX_USER}@${DHUS_HOST} "${3}" >> ${4}
}

HTTPS=0
RESTO_HOST=localhost
TARGET=resto
usage="## Verifie l'integrite la base de donnees par rapport a la base de donnees DHUS\n\n  Usage $0 -r <resto_db_password> -s <dhus_db_host> -d <dhus_db_password> -u <user_dhus_host>\n"
while getopts "r:s:d:h" options; do
    case $options in
        r ) RESTO_PASS=`echo $OPTARG`;;
        s ) DHUS_HOST=`echo $OPTARG`;;
        d ) DHUS_PASS=`echo $OPTARG`;;
        u ) UNIX_USER=`echo $OPTARG`;;
        h ) echo -e $usage;;
        \? ) echo -e $usage
            exit 1;;
        * ) echo -e $usage
            exit 1;;
    esac
done
if [ "$RESTO_PASS" = "" ]
then
    echo -n "Entrer le mot de passe pour la connexion a la base de donnees resto avec le user resto : "
    read -s RESTO_PASS
fi

if [ "$DHUS_HOST" = "" ]
then
    echo ""
    echo "Entrer le nom du serveur postgresql DHUS : "
    read DHUS_HOST
fi

if [ "$UNIX_USER" = "" ]
then
    echo ""
    echo "Entrer le nom de l utilisateur sur le serveur DHUS (pas celui de la base de donnees) : "
    read UNIX_USER
fi

if [ "$DHUS_PASS" = "" ]
then
    echo ""
    echo -n "Entrer le mot de passe pour la connexion a la base de donnees dhus avec le user dhus : "
    read -s DHUS_PASS
fi

export PGPASSFILE="$PRG_DIR/.pgpass"
echo "$RESTO_HOST:5432:resto:resto:$RESTO_PASS" > "$PGPASSFILE"

echo ""
echo "Recuperation de la liste des produits catalogues dans la base de donnees Resto"

# get resto products
psql -t -U resto resto -c "select title from features where title is not null;" > "$PRG_DIR/products_in_resto.txt"
echo "select title from product where (product_status='CATALOG_DONE' or product_status='OBSOLETE') and title not in (" > "$PRG_DIR/product_request.sql"
first=true
# build request for dhus
while read line
do
    product_title=`echo $line | cut -d\  -f 1`
    if [ "$first" = false ]
    then
        echo ", '$product_title'" >> "$PRG_DIR/product_request.sql"
    else
        echo "'$product_title'" >> "$PRG_DIR/product_request.sql"
        first=false
    fi

done < "$PRG_DIR/products_in_resto.txt"
echo ");" >> "$PRG_DIR/product_request.sql"

# get products in DHUS and not in resto
requestPath="$PRG_DIR/product_request.sql"
requestDest="/tmp/product_request.sql"
execPsql ${requestPath} ${requestDest} "psql -t -U dhus -d dhus -f ${requestDest}" "$PRG_DIR/products_in_dhus_not_resto.txt"
#psql -h $DHUS_HOST -t -U dhus dhus -f "$PRG_DIR/product_request.sql" > "$PRG_DIR/products_in_dhus_not_resto.txt"
while read line
do
    logMessage "Le produit $line est dans le DHUS mais pas dans Resto"
done < "$PRG_DIR/products_in_dhus_not_resto.txt"

# cleaning the request file
echo "" > "$PRG_DIR/product_request.sql"

# get DHUS products
requestPath="$PRG_DIR/product_request.sql"
requestDest="/tmp/product_request.sql"
execPsql ${requestPath} ${requestDest} "psql -t -U dhus -d dhus -c \"select title from product where title is not null and (product_status='OBSOLETE' or product_status='CATALOG_DONE');\"" "$PRG_DIR/products_in_dhus.txt"

# build request for resto
echo "select title from features where title not in (" > "$PRG_DIR/product_request.sql"
first=true
while read line
do
    product_title=`echo $line | cut -d\  -f 1`
	if [ "$first" = false ]
	then
        echo ", '$product_title'" >> "$PRG_DIR/product_request.sql"
	else
	   echo "'$product_title'" >> "$PRG_DIR/product_request.sql"
	   first=false
	fi
done < "$PRG_DIR/products_in_dhus.txt"
echo ");" >> "$PRG_DIR/product_request.sql"

# get products in Resto and not in DHUS
psql -U resto resto -t -f "$PRG_DIR/product_request.sql" > "$PRG_DIR/products_in_resto_not_dhus.txt"
while read line
do
    logMessage "Le produit $line est dans Resto mais pas dans le DHUS"
done < "$PRG_DIR/products_in_dhus_not_resto.txt"

# cleaning
rm "$PGPASSFILE"
rm "$PRG_DIR/products_in_resto.txt"
rm "$PRG_DIR/products_in_dhus.txt"
rm "$PRG_DIR/products_in_dhus_not_resto.txt"
rm "$PRG_DIR/products_in_resto_not_dhus.txt"
rm "$PRG_DIR/product_request.sql"