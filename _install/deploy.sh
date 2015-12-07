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
TARGETDIR="/var/www/html/resto"
CONFDIR="/opt/peps/resto"
USER="exppeps"
GROUP="peps"
FORCE_DEPLOY="NO"

usage="## RESTo deployment\n\n  Usage $0 -t <RESTO_TARGET> -c <CONF_TARGET> -u <unix_user> -g <unix group> -b <BACKUPDIR> -f \n\
\t-t <RESTO_TARGET>\t: target directory where to deploy resto, default is /var/www/html/resto\n\
\t-b <BACKUPDIR>\t\t: directory where to backup old version of resto, if RESTO_TARGET exists, BACKUPDIR and -f not set, deployment stops, no default value\n\
\t-f \t\t\t: force deployment and suppression of <RESTO_TARGET>\n\
\t-u <unix_user>\t\t: unix user which owns TARGET directories, default is exppeps\n\
\t-g <unix group>\t\t: unix group which owns TARGET directories, default is peps\n"
while getopts "ft:c:u:g:b:h" options; do
    case $options in
        t ) TARGETDIR=`echo $OPTARG`;;
        b ) BACKUPDIR=`echo $OPTARG`;;
        f ) FORCE_DEPLOY="YES";;
        u ) USER=`echo $OPTARG`;;
        g ) GROUP=`echo $OPTARG`;;
        h ) echo -e $usage;;
        \? ) echo -e $usage
            exit 1;;
        * ) echo -e $usage
            exit 1;;
    esac
done

if [ -d "$TARGETDIR" ]; then
    if [ "$BACKUPDIR" = "" ]; then
        if [ "$FORCE_DEPLOY" = "NO" ]; then
             echo "ERROR : $TARGETDIR exists but BACKUPDIR and -f not set."
             echo -e $usage
             exit 2
        fi 
    else 
	    if [ ! -d "$BACKUPDIR" ]; then
	        mkdir $BACKUPDIR
	        if [[ $? != 0 ]]; then
	            echo "ERROR : cannot create backup dir $BACKUPDIR"
	            exit $rc
	        fi  
	    fi
	    BASE=`basename $TARGETDIR`
	    DIR=`dirname $TARGETDIR`
	    echo "Creating a backup in $BACKUPDIR"
	    tar czf ${BACKUPDIR%%/}/resto-$(date +"%Y-%m-%d").tgz -C $DIR $BASE
	    rc=$?
	    if [[ $? != 0 ]]; then
	        echo "ERROR : cannot save $TARGETDIR in $BACKUPDIR"
	        exit $rc
	    fi          
	fi  
    rm -rf $TARGETDIR
fi

mkdir $TARGETDIR
echo " Deploying to $TARGETDIR "
cp -Rf $SRCDIR/.htaccess $SRCDIR/favicon.ico $SRCDIR/index.php $SRCDIR/include $SRCDIR/lib $TARGETDIR

# deploiement des scripts et du fichier de conf
echo " Deploying scritps and conf file to $CONFDIR"
CONFFILE="$TARGETDIR/include/config.php"
DIST_CONF_FILE="$CONFDIR/conf/config.php"
if [ ! -d "$CONFDIR" ]; then
    echo "Creation of $CONFDIR"
    mkdir "$CONFDIR"
fi
if [ ! -d "$CONFDIR/conf" ]; then
    echo "Creation of $CONFDIR/conf"
    mkdir "$CONFDIR/conf"
fi
if [ ! -d "$CONFDIR/scripts" ]; then
    echo "Creation of $CONFDIR/scripts"
    mkdir "$CONFDIR/scripts"
fi
if [ -f "$DIST_CONF_FILE" ] ; then
    cur_date=`date '+%Y%m%d%H%M%S'`
    mv $DIST_CONF_FILE $DIST_CONF_FILE-${cur_date}
fi

mv $CONFFILE $DIST_CONF_FILE
rm -rf "$CONFDIR/scripts"
cp -r "${SRCDIR}/_scripts" "$CONFDIR/scripts"

# deploiement du fichier version s'il existe
if [ -f $SRCDIR/version.txt ]; then
    cp $SRCDIR/version.txt $TARGETDIR
fi

echo "Applying unix user's rights."
chown -R $USER:$GROUP $TARGETDIR
chmod -R 750 $TARGETDIR
chown -R $USER:$GROUP $CONFDIR
chmod -R 750 $CONFDIR
usermod -a -G peps apache
chcon -v --type=httpd_sys_content_t $CONFDIR


echo ' ==> Successfully installed resto to $TARGETDIR directory'
