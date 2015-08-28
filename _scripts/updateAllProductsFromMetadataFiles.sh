#!/bin/bash


# recuperation du repertoire du script, n'utiliser que la variable PRG_DIR qui represente le chemin absolu
PRG="$0"
EXEC_DIR=`dirname ${PRG}`
export PRG_DIR=`(cd ${EXEC_DIR} ; echo $PWD)`

REPORT_FILE="$PRG_DIR/updateAllReport.txt"
if [ -f "$REPORT_FILE" ]
then
    rm "$REPORT_FILE"
fi

function logMessage() {
    echo "$1"
    echo "$1" >> "$REPORT_FILE"
}


HTTPS=0
HOST=localhost
TARGET=resto
ARCHIVE_PATH="/hpss/peps/data"
usage="## Met a jour toutes les ressources de la collection S1\n\n  Usage $0  -u <admin_username:admin_password> -p <resto_db_password> [-s (use https if set)]\n"
while getopts "s:u:p:h" options; do
    case $options in
        u ) AUTH=`echo $OPTARG`;;
        s ) HTTPS=1;;
        p ) RESTO_PASS=`echo $OPTARG`;;
        h ) echo -e $usage;;
        \? ) echo -e $usage
            exit 1;;
        * ) echo -e $usage
            exit 1;;
    esac
done
if [ "$AUTH" = "" ]
then
    echo -e $usage
    exit 1
fi
if [ "$RESTO_PASS" = "" ]
then
    echo -n "Entrer le mot de passe pour la connexion à la base de données resto avec le user resto : "
    read -s RESTO_PASS
fi

export PGPASSFILE="$PRG_DIR/.pgpass"
echo "$HOST:5432:resto:resto:$RESTO_PASS" > "$PGPASSFILE"

logMessage "Récupération de la liste des produits catalogués"
psql -t -U resto resto -c "select identifier, title, quicklook from _s1.features order by startdate desc;;" > "$PRG_DIR/products.txt"

rm "$PGPASSFILE"

while read line
do
    identifier=`echo $line | cut -d\  -f 1`
    title=`echo $line | cut -d\  -f 3`
    path=`echo $line | cut -d\  -f 5`
    
    metadatapath="${ARCHIVE_PATH}/${path}_Metadata_updated.xml"
    if [ ! -f "$metadatapath" ]
    then 
        metadatapath="${ARCHIVE_PATH}/${path}_Metadata.xml"
        if [ ! -f "$metadatapath" ]
        then
            metadatapath=""
        fi
    fi
    if [ "$metadatapath" = "" ]
    then
        logMessage "Pas de fichier metadata trouve pour le produit $title, il ne sera pas mis à jour."
    else    
        logMessage "Mise a jour du produit $title"
        updateResult=`"$PRG_DIR/updateResource.sh" -c S1 -i $identifier -f "$metadatapath" -u $AUTH`
        echo $updateResult >> "$REPORT_FILE"
        if [[ "$updateResult" == *"Error"* ]]
        then
            logMessage "Une erreur est survenue pendant la mise à jour du produit.";
        else
            logMessage "Mise a jour réussie.";
        fi
    fi
done <  "$PRG_DIR/products.txt"
