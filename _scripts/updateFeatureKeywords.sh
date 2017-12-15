#!/bin/bash


#####################################
# Initialisation variables
#####################################
PRG="$0"
EXEC_DIR=`dirname ${PRG}`
export PRG_DIR=`(cd ${EXEC_DIR} ; echo $PWD)`

HTTPS=0
RESTO_CURL_PROXY=0
RESTO_DB_HOST="localhost"
RESTO_DB_NAME=resto

#####################################
# SQL Query of products to update
#####################################
QUERY='select identifier from resto.features;'

#####################################
# Fichier de logs
#####################################
REPORT_FILE="$PRG_DIR/updateFeatureKeywords.log"
JOBLOG_FILE="$PRG_DIR/updateFeatureKeywordsJob.log"

if [ -f "$REPORT_FILE" ]
then
    rm "$REPORT_FILE"
fi
if [ -f "$JOBLOG_FILE" ]
then
    rm "$JOBLOG_FILE"
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
	echo "Usage: $0 -f <file_path> -H <resto_hostname> -a <resto_bd_user:password> -b <dhus_db_user:password> [ -s (use HTTPS protocol) -n (use --noproxy curl options) -d <resto_db_hostname> -q <resto_sql_query> ]" 1>&2; 
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
		CURL_PROXY='--noproxy "${RESTO_HOST}"';
	else
		CURL_PROXY='';
fi
logMessage ${CURL_PROXY}

#####################################
# RESTo : database connection string
#####################################
DB_RESTO_STRING_CONNECTION=postgresql://${RESTO_DB_AUTH}\@${RESTO_DB_HOST}/${RESTO_DB_NAME}

#####################################
# RESTo database : récupération de la liste des produits à mettre à jour
#                  (ignore les espaces et les lignes vide)
#####################################
logMessage "Récupération de la liste des produits à mettre a jour"
psql -t $DB_RESTO_STRING_CONNECTION -c "${QUERY}" | sed -e 's/^[ \t]*//' | sed '/^$/d' > "$PRG_DIR/products.txt"

#####################################
# Mise à jour des produits
#####################################
logMessage "Mise a jour des produits en cours...";
if [ "$HTTPS" = "1" ]
then
    state=$(parallel -j10 --eta --joblog ${JOBLOG_FILE} -a products.txt curl -s -k -X PUT ${CURL_PROXY} https://${WEBS_AUTH}@${RESTO_HOST}/resto/api/tag/{1}/refresh);
else
    state=$(parallel -j10 --eta --joblog ${JOBLOG_FILE} -a products.txt curl -s -X PUT ${CURL_PROXY} http://${WEBS_AUTH}@${RESTO_HOST}/resto/api/tag/{1}/refresh);
fi
logMessage "$state";

#####################################
# Nettoyage
#####################################
if [ -f "$PRG_DIR/products.txt" ]
then
    rm "$PRG_DIR/products.txt"
fi
