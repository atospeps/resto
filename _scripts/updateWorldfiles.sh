#!/bin/bash

HTTPS=0
HOST=localhost
TARGET=resto
ARCHIVE_PATH="/hpss/peps/data"

ARCHIVE_PATH="/data/peps/dhus/data/"

usage="## Met a jour les fichiers de géoréfencement des quicklooks Sentinel 1 S1\n\n  Usage $0  -u <admin_username:admin_password> -p <resto_db_password> [-s (use https if set)]\n"

# recuperation du repertoire du script, n'utiliser que la variable PRG_DIR qui represente le chemin absolu
PRG="$0"
EXEC_DIR=`dirname ${PRG}`
export PRG_DIR=`(cd ${EXEC_DIR} ; echo $PWD)`

REPORT_FILE="$PRG_DIR/updateWorldfilesReport.txt"
> $REPORT_FILE

############################################################################
# Logs message into log file
############################################################################
function logMessage() {
    echo "$1"
    echo "$1" >> "$REPORT_FILE"
}

############################################################################ 
# This function allows to create world file.
# 
# Parameters
# - $1 : WKT footprint
# - $2 : image width
# - $3 : image height
# - $4 : filepath
############################################################################
function createWorldfile(){
	wktFootprint=$1
	width=$2
	height=$3
	filepath=$4

	if [ $filepath = "" ]
	then
		logMessage "Cannot create world file. File path not defined";
		exit 1;
	elseif [ $wktFootprint = "" ] 
		logMessage "Cannot create world file. Quicklook footprint not defined";
		exit 1;
	fi
	
	if [ $width = "" ]
	then
		logMessage "Cannot create world file. Quicklook width not defined";
		exit 1;
	fi
	
	if [ $height = "" ]
	then
		logMessage "Cannot create world file. Quicklook height not defined";
		exit 1;
	fi	

	## Parses WKT polygon #############################
	
	# extract coordinates from WKT string
	values=`(echo $wktFootprint | sed 's/[^0-9. ,]*//g')`
	lines=`(echo $values | sed 's/,/'\n'/g')`
	
	# split coordinates
	IFS="\n";
	lines=($lines);
	
	# split lon/lat of each point
	IFS=" ";
	ll=(${lines[0]});
	lr=(${lines[1]});
	ur=(${lines[2]});
	ul=(${lines[3]});
	
	## Builds world file #############################
		
	# coeff A : (ur.Longitude - ul.Longitude) / width
	wld[0]=`echo "scale=10; (${ur[0]} - ${ul[0]}) / $width" | bc -l`;
	
	# coeff D : ur.Latitude - ul.Latitude) / width
	wld[1]=`echo "scale=10; (${ur[1]} - ${ul[1]}) / $width" | bc -l`;
	
	# coeff B : (ll.Longitude - ul.Longitude) / height
	wld[2]=`echo "scale=10; (${ll[0]} - ${ul[0]}) / $height" | bc -l`;
	
	# coeff E : (ll.Latitude - ul.Latitude) / height
	wld[3]=`echo "scale=10; (${ll[1]} - ${ul[1]}) / $height" | bc -l`;
	
	# coeff C : ul.Longitude
	wld[4]=`echo "scale=10; ${ul[0]}" | bc -l`;

	# coeff F : ul.Latitude
	wld[5]=`echo "scale=10; ${ul[1]}" | bc -l`;
	
	# creates world file
	> $filepath;

	if [ -f $filepath ]
	then
	    > $filepath;
		for index in "${!wld[@]}"; do
			echo "${wld[$index]}">>$filepath;			
		done
		logMessage "World file '$filepath' has been created successfully";
	else
		logMessage "Cannot create World file $filepath";
	fi
}

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

logMessage "Mise à jour des fichiers de géoréfencements des quicklooks des produits Sentinel-1"
#psql -t -U resto resto -c "select productidentifier, quicklook, ST_AsText(geometry) from _s1.features where productidentifier='S1A_IW_SLC__1SSV_20150331T073046_20150331T073113_PZF4A7_1R05Q2_WYVM';" > "$PRG_DIR/products.txt"
psql -t -U resto resto -c "select productidentifier, quicklook, ST_AsText(geometry) from _s1.features;" > "$PRG_DIR/products.txt"

rm "$PGPASSFILE"

while read line
do
	if [ "$line" != "" ]
	then
		title=`echo $line | cut -d\| -f1 | sed 's/^ *//g' | sed 's/ *$//g'`
	    path=`echo $line | cut -d\| -f2 | sed 's/^ *//g' | sed 's/ *$//g'`
	    wktPolygon=`echo $line | cut -d\| -f3 | sed 's/^ *//g' | sed 's/ *$//g'`

	    quicklookpath="${ARCHIVE_PATH}/${path}_quicklook.gif"
	    worldfilepath="${ARCHIVE_PATH}/${path}_quicklook.wld"
	    
	    if [ ! -f "$quicklookpath" ]
	    then
	    	logMessage "Pas de fichier quicklook trouve pour le produit $title. Aucun fichier world file ne sera cree pour ce produit";
	    else
	    	quicklookpath="/data/peps/dhus/data//2014/10/23/S1A/S1A_IW_SLC__1SSV_20140920T075917_20140920T075944_CYPGSF_M837Y4_X4GH_quicklook.gif"
	    	size=`gdalinfo $quicklookpath | grep "Size is" | sed 's/[^0-9 ]*//g' | sed 's/ *$//g'`

	    	# split size to array([0] => width, [1] => height)
	    	size=($size);
	    	
	    	createWorldfile "$wktPolygon" "${size[0]}" "${size[1]}" $worldfilepath
	    fi
    fi    
done <  "$PRG_DIR/products.txt"
