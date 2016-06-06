#!/bin/bash

# recuperation du repertoire du script, n'utiliser que la variable PRG_DIR qui represente le chemin absolu
PRG="$0"
EXEC_DIR=`dirname ${PRG}`
export PRG_DIR=`(cd ${EXEC_DIR} ; echo $PWD)`

USER=apache
GROUP=apache
RIGHTS=755
HTTPS=0
HOST=localhost
TARGET=resto
ARCHIVE_PATH="/home/exploit/files"
usage="## Crée les repertoires de fichiers pour chaque utilisateur\n\n  Usage $0  -u <admin_username:admin_password> -f <files_directory_path> [-s (use https if set)]\n"
while getopts "s:u:f:h" options; do
    case $options in
        u ) AUTH=`echo $OPTARG`;;
        s ) HTTPS=1;;
        f ) ARCHIVE_PATH=`echo $OPTARG`;; 
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

# create global files directory if it doesn't exist
if [ ! -d "$ARCHIVE_PATH" ]; then
    mkdir $ARCHIVE_PATH
    if [[ $? != 0 ]]; then
        echo "ERROR : cannot create dir $ARCHIVE_PATH"
        exit $rc
    fi  
    chown -R $USER:$GROUP $ARCHIVE_PATH
    chmod -R $RIGHTS $ARCHIVE_PATH
fi

# create entry processing files directory if it doesn't exist
if [ ! -d "$ARCHIVE_PATH/entryprocessing" ]; then
    mkdir $ARCHIVE_PATH/entryprocessing
    if [[ $? != 0 ]]; then
        echo "ERROR : cannot create dir $ARCHIVE_PATH/entryprocessing"
        exit $rc
    fi  
    chown -R $USER:$GROUP $ARCHIVE_PATH/entryprocessing
    chmod -R $RIGHTS $ARCHIVE_PATH/entryprocessing
fi

echo "Récupération de la liste des utilisateurs"
psql -t -U resto resto -c "select userid from usermanagement.users;" > "$PRG_DIR/userid.txt"

while read line
do
    identifier=`echo $line | cut -d\  -f 1`
    
    if [ "$identifier" != "" ]
    then
    
	    # create global user files directory if it doesn't exist
	    if [ ! -d "$ARCHIVE_PATH/$identifier" ]; then
	        mkdir $ARCHIVE_PATH/$identifier
	        if [[ $? != 0 ]]; then
	            echo "ERROR : cannot create dir $ARCHIVE_PATH/$identifier"
	            exit $rc
	        fi  
	        chown -R $USER:$GROUP $ARCHIVE_PATH/$identifier
	        chmod -R $RIGHTS $ARCHIVE_PATH/$identifier
	    fi
	
	    # create auxiliary user files directory if it doesn't exist
	    if [ ! -d "$ARCHIVE_PATH/$identifier/auxiliary" ]; then
	        mkdir $ARCHIVE_PATH/$identifier/auxiliary
	        if [[ $? != 0 ]]; then
	            echo "ERROR : cannot create dir $ARCHIVE_PATH/$identifier/auxiliary"
	            exit $rc
	        fi  
	        chown -R $USER:$GROUP $ARCHIVE_PATH/$identifier/auxiliary
	        chmod -R $RIGHTS $ARCHIVE_PATH/$identifier/auxiliary
	    fi
	
	    # create processing result user files directory if it doesn't exist
	    if [ ! -d "$ARCHIVE_PATH/$identifier/processing" ]; then
	        mkdir $ARCHIVE_PATH/$identifier/processing
	        if [[ $? != 0 ]]; then
	            echo "ERROR : cannot create dir $ARCHIVE_PATH/$identifier/processing"
	            exit $rc
	        fi  
	        chown -R $USER:$GROUP $ARCHIVE_PATH/$identifier/processing
	        chmod -R $RIGHTS $ARCHIVE_PATH/$identifier/processing
	    fi
    fi

done < "$PRG_DIR/userid.txt"

rm "$PRG_DIR/userid.txt"

echo " ==> Successfully create user files directory in $ARCHIVE_PATH directory"

