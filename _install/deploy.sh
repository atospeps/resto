#!/bin/bash
#
# Copyright 2014 Jérôme Gasperi
#
# Licensed under the Apache License, version 2.0 (the "License");
# You may not use this file except in compliance with the License.
# You may obtain a copy of the License at:
#
#   http://www.apache.org/licenses/LICENSE-2.0
#
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
# WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
# License for the specific language governing permissions and limitations
# under the License.


# recuperation du repertoire du script, n'utiliser que la variable PRG_DIR qui represente le chemin absolu
PRG="$0"
EXEC_DIR=`dirname ${PRG}`
export PRG_DIR=`(cd ${EXEC_DIR} ; echo $PWD)`
#PRG_DIR correspond au chemin complet vers _install
SRCDIR="${PRG_DIR}/.."
SECURE="OFF"
usage="## RESTo deployment\n\n  Usage $0 -t <RESTO_TARGET> [ -s ] \n\n\t -s : secure installation  \n"
while getopts "st:h" options; do
    case $options in
        s ) SECURE="ON";;
        t ) TARGETDIR=`echo $OPTARG`;;
        h ) echo -e $usage;;
        \? ) echo -e $usage
            exit 1;;
        * ) echo -e $usage
            exit 1;;
    esac
done

if [ "$TARGETDIR" = "" ]
then
    echo -e $usage
    exit 1
fi

if [ -d "$TARGETDIR" ]; then
    if [ "$(ls $TARGETDIR)" ]; then
        echo "ERROR : $TARGETDIR is not empty. Cannot install"
        exit 1
    fi
fi

mkdir $TARGETDIR
echo " Deploying to $TARGETDIR "
cp -Rf $SRCDIR/.htaccess $SRCDIR/favicon.ico $SRCDIR/index.php $SRCDIR/include $SRCDIR/lib $TARGETDIR

if [ "$SECURE" = "ON" ]; then
    echo " Securing deployment "
    # securisation de l'installation en deplaçant le fichier config.php dans /etc/httpd/conf.d
	CONFDIR="/etc/httpd/conf.d"
	CONFFILE="$TARGETDIR/include/config.php"
	DIST_CONF_FILE="$CONFDIR/config.php"
	if [ -f "$DIST_CONF_FILE" ] ; then
	    cur_date=`date '+%Y%m%d%H%M%S'`
	    mv $DIST_CONF_FILE $DIST_CONF_FILE-${cur_date}
	fi
	
	mv $CONFFILE $DIST_CONF_FILE
	chown root:root $DIST_CONF_FILE
	chmod 644 $DIST_CONF_FILE
fi

echo ' ==> Successfully installed resto to $TARGETDIR directory'
