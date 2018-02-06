#!/bin/bash

##############################################################
#
# METS A JOURS LE STATUS DE L'INFRA VIZO
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

HTTPS=0
RESTO_CURL_PROXY=0
RESTO_DB_HOST="localhost"
RESTO_DB_NAME=resto

#####################################
# Usage
#####################################
usage() { 
	echo "Usage: $0" 1>&2; 
	exit 1; 
}


