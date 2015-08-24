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

SRCDIR="${PRG_DIR}/.."

# Paths are mandatory from command line
SUPERUSER=postgres
DROPFIRST=NO
USER=resto
DATADIR="${PRG_DIR}/data"
usage="## RESTo database installation\n\n  Usage $0 -p <resto (Read+Write database) user password> [-f <PostGIS directory> -s <database SUPERUSER> -F]\n\n  -d : database name (default resto)\n  -f : absolute path to the directory containing postgis.sql - If not set EXTENSION mechanism will be used\n  -s : dabase SUPERUSER (default "postgres")\n  -F : WARNING - drop resto database\n"
while getopts "f:s:p:hF" options; do
    case $options in
        f ) ROOTDIR=`echo $OPTARG`;;
        s ) SUPERUSER=`echo $OPTARG`;;
        p ) USERPASSWORD=`echo $OPTARG`;;
        F ) DROPFIRST=YES;;
        h ) echo -e $usage;;
        \? ) echo -e $usage
            exit 1;;
        * ) echo -e $usage
            exit 1;;
    esac
done
if [ "$DATADIR" = "" ]
then
    echo -e $usage
    exit 1
fi
if [ "$USERPASSWORD" = "" ]
then
    echo -e $usage
    exit 1
fi

# attention cette variable n'etant que partiellement utilisee dans les scripts, la modifier ne marchera pas
DB=resto

##### DROP SCHEMA FIRST ######
if [ "$DROPFIRST" = "YES" ]
then
    dropdb $DB -U $SUPERUSER
fi


# Create DB
createdb $DB -U $SUPERUSER -E UTF8
createlang -U $SUPERUSER plpgsql $DB

# Make db POSTGIS compliant
if [ "$ROOTDIR" = "" ]
then
    psql -d $DB -U $SUPERUSER -c "CREATE EXTENSION postgis; CREATE EXTENSION postgis_topology;"
else
    # Example : $ROOTDIR = /usr/local/pgsql/share/contrib/postgis-1.5/
    postgis=`echo $ROOTDIR/postgis.sql`
    projections=`echo $ROOTDIR/spatial_ref_sys.sql`
    psql -d $DB -U $SUPERUSER -f $postgis
    psql -d $DB -U $SUPERUSER -f $projections
fi


###### ADMIN ACCOUNT CREATION ######
psql -U $SUPERUSER -d $DB << EOF
DO
\$body\$
BEGIN
   IF NOT EXISTS (
      SELECT *
      FROM   pg_catalog.pg_user
      WHERE  usename = '$USER') THEN

      CREATE USER $USER WITH PASSWORD '$USERPASSWORD' NOCREATEDB;
   END IF;
END
\$body\$
EOF

#############CREATE DB ##############
psql -d $DB -U $SUPERUSER -f "${PRG_DIR}/sql/createDB.sql"


# Data
psql -U $SUPERUSER -d $DB -f $DATADIR/platformsAndInstruments.sql
psql -U $SUPERUSER -d $DB -f $DATADIR/regionsAndStates.sql
psql -U $SUPERUSER -d $DB -f $DATADIR/en/landuses.sql
psql -U $SUPERUSER -d $DB -f $DATADIR/en/continentsAndCountries.sql
psql -U $SUPERUSER -d $DB -f $DATADIR/en/generalKeywords.sql
psql -U $SUPERUSER -d $DB -f $DATADIR/fr/landuses.sql
psql -U $SUPERUSER -d $DB -f $DATADIR/fr/continentsAndCountries.sql
psql -U $SUPERUSER -d $DB -f $DATADIR/fr/generalKeywords.sql

# Normalize values
psql -U $SUPERUSER -d $DB -f "${PRG_DIR}/sql/normalize.sql"

# Rights
psql -U $SUPERUSER -d $DB -v user=$USER -v db=$DB -f "${PRG_DIR}/sql/updateRights.sql"




