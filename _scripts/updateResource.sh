#!/bin/bash

# recuperation du repertoire du script, n'utiliser que la variable PRG_DIR qui represente le chemin absolu
PRG="$0"
EXEC_DIR=`dirname ${PRG}`
export PRG_DIR=`(cd ${EXEC_DIR} ; echo $PWD)`

HTTPS=0
HOST=localhost
TARGET=resto
usage="## Update a resource in a collection\n\n  Usage $0 -c <Collection name> -i <Resource identifier> -f  <Resource description file>  -u <username:password> [-s (use https if set)  -H server (default localhost) -p resto path (default resto)]\n"
while getopts "s:i:f:c:u:p:h:H" options; do
    case $options in
        u ) AUTH=`echo $OPTARG`;;
        H ) HOST=`echo $OPTARG`;;
        p ) TARGET=`echo $OPTARG`;;
        i ) IDENTIFIER=`echo $OPTARG`;;
        f ) FILE=`echo $OPTARG`;;
        s ) HTTPS=1;;
        c ) COLLECTION=`echo $OPTARG`;;
        h ) echo -e $usage;;
        \? ) echo -e $usage
            exit 1;;
        * ) echo -e $usage
            exit 1;;
    esac
done
if [ "$IDENTIFIER" = "" ]
then
    echo -e $usage
    exit 1
fi
if [ "$COLLECTION" = "" ]
then
    echo -e $usage
    exit 1
fi
if [ "$FILE" = "" ]
then
    echo -e $usage
    exit 1
fi


if [ "$HTTPS" = "1" ]
then
    curl -k --get -X DELETE https://$AUTH@$HOST/$TARGET/collections/$COLLECTION/$IDENTIFIER
    curl -k -X POST -d @$FILE https://$AUTH@$HOST/$TARGET/collections/$COLLECTION
else
    curl -s --get -X DELETE http://$AUTH@$HOST/$TARGET/collections/$COLLECTION/$IDENTIFIER 
    echo ""
    curl -s -X POST -d @$FILE http://$AUTH@$HOST/$TARGET/collections/$COLLECTION
    echo ""
fi

