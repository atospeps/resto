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
	echo "Usage: $0 -u <username:password> [-H <host> (default localhost) -n (use --noproxy curl options) -s (returns current status)]" 1>&2; 
	exit 1; 
}

#####################################
# Options
#####################################
while getopts "snu:H:" options; do
    case $options in
        u ) AUTH=`echo $OPTARG`;;
        H ) HOST=`echo $OPTARG`;;
        n ) CURL_NOPROXY=1 ;;
        s ) RETURN_STATUS=1;;
        \?) echo -e $usage
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
# RESTo
#####################################
if [ "$CURL_NOPROXY" = "1" ]
then
    curl -s --noproxy "$HOST" -X PUT https://$AUTH@$HOST/resto/wps/check
else
    curl -s -X PUT https://$AUTH@$HOST/resto/wps/check
fi

#####################################
# Retourne le status
#####################################
#if [ "$RETURN_STATUS" = "1" ]
#then
    
#fi

