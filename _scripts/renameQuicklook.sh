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

function logMessage() {
    echo "$1" >> "$REPORT_FILE"
}

HOST=localhost
ARCHIVE_PATH=""
usage="## Remplace l'extension gif par jpg pour les fichiers quicklook archivés.\n\n  Usage $0 -s <archive_dir>\n"
while getopts "s:h:" options; do
    case $options in
        s ) ARCHIVE_PATH=${OPTARG};;
        h ) echo -e $usage;;
        \? ) echo -e $usage
            exit 1;;
        * ) echo -e $usage
            exit 1;;
    esac
done

if [ "$ARCHIVE_PATH" = "" ]
then
    echo "\nVeuillez specifier un repertoire de destination."
    echo -e $usage
    exit 1
fi

displayMessage "Renommage des fichiers quicklook gif en jpg à partir de \"$ARCHIVE_PATH\""
cd "$ARCHIVE_PATH"

find -L . -type f -name "*.gif" -print0 | while IFS= read -r -d '' FNAME; do
    mv -- "$FNAME" "${FNAME%.gif}.jpg"
done
