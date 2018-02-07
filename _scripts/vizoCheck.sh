#!/bin/bash

##############################################################
#
# METS A JOURS LA BASE AVEC LE STATUS DE L'INFRA VIZO
# (ET RETOURNE LE DERNIER STATUS [option -s])
#
#   Fait appel à RESTo qui exécutera le traitement CHECK et
#   mettra à jour la table wps_status
#
##############################################################


#####################################
# Initialisation
#####################################
PRG="$0"
EXEC_DIR=`dirname ${PRG}`
export PRG_DIR=`(cd ${EXEC_DIR} ; echo $PWD)`

CURL_NOPROXY=0
HOST="localhost"

#####################################
# Usage
#####################################
usage() { 
	echo "Usage: $0 -u <username:password> [-H <host> (default localhost) -n (use --noproxy curl options)]" 1>&2; 
	exit 1; 
}

#####################################
# OPTIONS
#####################################
while getopts "snu:H:" options; do
    case $options in
        u ) AUTH=`echo $OPTARG`;;
        H ) HOST=`echo $OPTARG`;;
        n) CURL_NOPROXY=1 ;;
        s ) RETURN_STATUS=1;;
        \? ) echo -e $usage
            exit 1;;
        * ) echo -e $usage
            exit 1;;
    esac
done

if [ -z "${AUTH}" ] || [ -z "${HOST}" ];
then
    usage
fi

#####################################
# CALL RESTO
#####################################
if [ "$CURL_NOPROXY" = "1" ]
then
    curl -s --noproxy "$HOST" -X PUT https://$AUTH@$HOST/resto/wps/check
else
    curl -s -X PUT https://$AUTH@$HOST/resto/wps/check
fi

