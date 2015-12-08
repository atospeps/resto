#!/bin/bash

# recuperation du repertoire du script, n'utiliser que la variable PRG_DIR qui represente le chemin absolu
PRG="$0"
EXEC_DIR=`dirname ${PRG}`
export PRG_DIR=`(cd ${EXEC_DIR} ; echo $PWD)`

REPORT_FILE="$PRG_DIR/moveFilesReport.txt"
if [ -f "$REPORT_FILE" ]
then
    rm "$REPORT_FILE"
fi

function displayMessage() {
    echo "$1"
    echo "$1" >> "$REPORT_FILE"
}


HOST=localhost
TARGET_PATH=""
ARCHIVE_PATH="/hpss/peps/data"
usage="## Deplace les fichiers .gif et .wld de l'archive HPSS vers le serveur de distribution.\n\n  Usage $0 -H <ftp_host> -u <ftp_user:ftp_pwd> [-s <ftp_dir> ] -d <dest_dir> -p <resto_db_password>\n"
while getopts "H:u:s:d:p:h:" options; do
    case $options in
        s ) ARCHIVE_PATH=${OPTARG};;
        p ) RESTO_PASS=${OPTARG};;
        d ) TARGET_PATH=${OPTARG};;
        H ) FTP_HOST=${OPTARG};;
        u ) FTP_USER=${OPTARG};;
        h ) echo -e $usage;;
        \? ) echo -e $usage
            exit 1;;
        * ) echo -e $usage
            exit 1;;
    esac
done


if [ "$TARGET_PATH" = "" ]
then
    echo "\nVeuillez specifier un repertoire de destination."
    echo -e $usage
    exit 1
fi
if [ "$FTP_USER" = "" ]
then
    echo "Veuillez specifier les informations de connexion au serveur FTP de l'HPSS."
    echo -e $usage
    exit 1
fi
if [ "$FTP_HOST" = "" ]
then
    echo "Veuillez specifier le nom du serveur FTP de l'HPSS."
    echo -e $usage
    exit 1
fi

if [ "$RESTO_PASS" = "" ]
then
    echo -n "Entrer le mot de passe pour la connexion à la base de données resto avec le user resto : "
    read -s RESTO_PASS
fi
if [ ! -d "$TARGET_PATH" ]
then
    echo "\nLe repertoire de destination n'existe pas."
    exit 1
fi

export PGPASSFILE="$PRG_DIR/.pgpass"
echo "$HOST:5432:resto:resto:$RESTO_PASS" > "$PGPASSFILE"

displayMessage "Récupération de la liste des produits catalogués non OCN"
psql -t -U resto resto -c "select title, quicklook from _s1.features where producttype <> 'OCN';" > "$PRG_DIR/products.txt"

rm "$PGPASSFILE"

displayMessage "Deplacement des fichiers gif et wld de l'archive \"ftp://$FTP_HOST/$ARCHIVE_PATH\" vers \"$TARGET_PATH\""

totalProduct=0
quicklook=0
worldfile=0

FTP_URL="ftp://$FTP_HOST/${ARCHIVE_PATH}"

while read line
do
    title=`echo $line | cut -d\  -f 1`
    path=`echo $line | cut -d\  -f 3`
    if [ "$path" != "" ]; then
	    subpath=`echo ${path%$title}`
	    quicklookpath="${path}_quicklook.gif"
	    
	    mkdir -p "$TARGET_PATH/${subpath}"
	    curl -s -u $FTP_USER "${FTP_URL}/$quicklookpath" -o "$TARGET_PATH/${quicklookpath}"
	    if [ "$?" = "0" ]; then
	        displayMessage "Fichier quicklook copié : $quicklookpath"
	        quicklook=$((quicklook+1))
	        worldfilepath="${path}_quicklook.wld"
	        curl -s -u $FTP_USER "${FTP_URL}/$worldfilepath" -o "$TARGET_PATH/${worldfilepath}"
	        displayMessage "Fichier worldfile copié : $worldfilepath"
	        worldfile=$((worldfile+1))
	        if [ "$?" != "0" ]; then
	            displayMessage "Pas de fichier worldfile pour le produit $title"
	        fi
	    else
	         displayMessage "Pas de fichier quicklook pour le produit $title"
	    fi
	    
	    totalProduct=$((totalProduct+1))
    fi
done <  "$PRG_DIR/products.txt"
displayMessage "Nombre de produits en base : $totalProduct"
displayMessage "Nombre de quicklook copiés : $quicklook"
displayMessage "Nombre de worldfile copiés : $worldfile"

