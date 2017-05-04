#!/bin/bash


#####################################
# Initialisation variables
#####################################
PRG="$0"
EXEC_DIR=`dirname ${PRG}`
export PRG_DIR=`(cd ${EXEC_DIR} ; echo $PWD)`

HTTPS=0
RESTO_CURL_PROXY=0
RESTO_HOST=localhost
RESTO_DB_HOST="localhost"
RESTO_DB_NAME=resto

#####################################
# SQL Query of products to update
#####################################
QUERY='select identifier, productidentifier from resto.features;'

#####################################
# Fichier de logs
#####################################
REPORT_FILE="$PRG_DIR/updateFeatureKeywords.log"

if [ -f "$REPORT_FILE" ]
then
    rm "$REPORT_FILE"
fi

#####################################
# Utilitaire de log
#####################################
function logMessage() {
    echo "$1"
    echo "$1" >> "$REPORT_FILE"
}

#####################################
# Help
#####################################
usage() { 
	echo "Usage: $0 -f <file_path> -H <resto_hostname> -a <resto_bd_user:password> -g <dhus_db_user:password> -w <user_resto_webservice:password> [ -s (use HTTPS protocol) -N (use --noproxy curl options) -b <resto_db_hostname> -D <dhus_db_hostname> -d <archive_directory> -q <quicklooks_dir> -u <unix_user> ]" 1>&2; 
	exit 1; 
}

#####################################
# input parameters
#####################################
while getopts "a:b:d:H:u:q:nsh:" options; do
    case $options in
        a) RESTO_DB_HOST=${OPTARG};;
        b) RESTO_DB_AUTH=${OPTARG};;
        d) RESTO_DB_NAME=${OPTARG};;
        H) RESTO_HOST=${OPTARG};;
		u) WEBS_AUTH=`echo $OPTARG`;;
		q) QUERY=`echo $OPTARG`;;
	    n) RESTO_CURL_PROXY=1 ;;
	    s) HTTPS=1;;
        h) usage ;;
		:) usage ;;
		\?) usage ;;
		*) usage ;;
	    esac
done


if [ -z "${RESTO_HOST}" ] || [ -z "${RESTO_DB_AUTH}" ] || [ -z "${RESTO_DB_NAME}" ]; then
    usage
fi

if [ -z "${WEBS_AUTH}" ]; then
    usage
fi

if [ "$RESTO_CURL_PROXY" = "1" ]
	then
		CURL_PROXY="${RESTO_HOST}";
	else
		CURL_PROXY="";
fi

#####################################
# RESTo : database connection string
#####################################
DB_RESTO_STRING_CONNECTION=postgresql://${RESTO_DB_AUTH}\@${RESTO_DB_HOST}/${RESTO_DB_NAME}

#####################################
# RESTo database : récupération de la liste des produits à mettre à jour
#####################################
logMessage "Récupération de la liste des produits à mettre a jour"
psql -t $DB_RESTO_STRING_CONNECTION -c "${QUERY}" > "$PRG_DIR/products.txt"

#####################################
# Mise à jour des produits
#####################################
while read line
do
	if [ ! -z "$line" ]; then
		identifier=`echo $line | cut -d\  -f 1`
	    title=`echo $line | cut -d\  -f 3`
	    echo $line;
	
		logMessage "Mise a jour du produit $title"
	    if [ "$HTTPS" = "1" ]
		then
		    state=$(curl -s -k -X PUT --noproxy "${CURL_PROXY}" https://${WEBS_AUTH}@${RESTO_HOST}/resto/api/tag/${identifier}/refresh);
		else
		    state=$(curl -s -X PUT --noproxy "${CURL_PROXY}" http://${WEBS_AUTH}@${RESTO_HOST}/resto/api/tag/${identifier}/refresh);
		fi
	    logMessage "    ==> $state";
	fi

done <  "$PRG_DIR/products.txt"

if [ -f "$PRG_DIR/products.txt" ]
then
    rm "$PRG_DIR/products.txt"
fi

