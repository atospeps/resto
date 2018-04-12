#!/bin/bash

#
#
#
function template(){
    title=$1
    realtime=$2
    isnrt=$3
    dhusIngestDate=$4
#    collection=${title:0:2}
    
    TEMPLATE='<?xml version="1.0" encoding="UTF-8" standalone="no"?>'
    TEMPLATE+='<product>'
    TEMPLATE+="<title>$title</title>"
    TEMPLATE+='<resourceSize>19228891</resourceSize>'
	TEMPLATE+='<startTime>2013-08-04T20:18:41.537</startTime>'
	TEMPLATE+='<stopTime>2013-08-04T20:19:45.728</stopTime>'
	TEMPLATE+="<dhusIngestDate>$dhusIngestDate</dhusIngestDate>"
	TEMPLATE+='<productType>OCN</productType>'
	TEMPLATE+='<missionId>S1A</missionId>'
	TEMPLATE+='<processingLevel>LEVEL2</processingLevel>'
	TEMPLATE+='<mode>EW</mode>'
	TEMPLATE+='<absoluteOrbitNumber>7118</absoluteOrbitNumber>'
	TEMPLATE+='<relativeOrbitNumber>618</relativeOrbitNumber>'
	TEMPLATE+='<orbitDirection>ASCENDING</orbitDirection>'
	TEMPLATE+='<swath>EW</swath>'
	TEMPLATE+='<polarisation>HH HV</polarisation>'
	TEMPLATE+='<missiontakeid>39736</missiontakeid>'
	TEMPLATE+='<resourceSize>222</resourceSize>'
	TEMPLATE+="<isNrt>$isnrt</isNrt>"
	TEMPLATE+="<realtime>$realtime</realtime>"
	TEMPLATE+='<checksum>312312312dazdaz2dAZd233</checksum>'
	TEMPLATE+='<footprint>POLYGON ((-44.610012 59.141117,-37.392982 59.885784,-36.303066 56.055756,-42.812229 55.343391,-44.610012 59.141117))</footprint>'
	
	# S2ST
	TEMPLATE+='<s2takeid>GS2A_20180201T153611_013650_N02.06</s2takeid>'
	TEMPLATE+='<mgrs>01WCS</mgrs>'
	TEMPLATE+='<baresoil>0</baresoil>'
	TEMPLATE+='<highprobaclouds>0</highprobaclouds>'
	TEMPLATE+='<mediumprobaclouds>0</mediumprobaclouds>'
	TEMPLATE+='<lowprobaclouds>0</lowprobaclouds>'
	TEMPLATE+='<snowice>0</snowice>'
	TEMPLATE+='<vegetation>0</vegetation>'
	TEMPLATE+='<water>0</water>'
	
	# S3
	TEMPLATE+='<cyclenumber>3232</cyclenumber>'
	TEMPLATE+='<approxSize>3213</approxSize>'
	TEMPLATE+='<ecmwfType>0</ecmwfType>'
	TEMPLATE+='<processingName>LEVEL2</processingName>'
	TEMPLATE+='<onlineQualityCheck>0</onlineQualityCheck>'
	
	TEMPLATE+='</product>'
   
   echo $TEMPLATE 
}

DATA_S1_NRT_10=$(template "S1A_EW_OCN__2SDH_20130804T201841_20130804T201945_007118_009B38_N10M" "NRT-10m" 1 "2013-08-04T2:19:45.728")
DATA_S1_NRT_1=$(template "S1A_EW_OCN__2SDH_20130804T201841_20130804T201945_007118_009B38_N1H0" "NRT-1h" 1 "2013-08-04T2:19:45.728")
DATA_S1_NRT_3=$(template "S1A_EW_OCN__2SDH_20130804T201841_20130804T201945_007118_009B38_N3H0" "NRT-3h" 1 "2013-08-04T2:19:45.728")
DATA_S1_FAST_24=$(template "S1A_EW_OCN__2SDH_20130804T201841_20130804T201945_007118_009B38_F24H" "Fast-24h" 0 "2013-08-04T2:19:45.728")
DATA_S1_OFFLINE=$(template "S1A_EW_OCN__2SDH_20130804T201841_20130804T201945_007118_009B38_OFFL" "Off-line" 0 "2013-08-04T2:19:45.728")
DATA_S1_REPROCESSING=$(template "S1A_EW_OCN__2SDH_20130804T201841_20130804T201945_007118_009B38_REPR" "Reprocessing" 0 "2013-08-04T2:19:45.728")

DATA_S2_NOMINAL_10=$(template "S2A_MSIL1C_20130530T002611_N0210_R102_T01WCS_20130530T002609" "Nominal" 0 "2013-08-04T2:19:45.728")
DATA_S2_NRT=$(template "S2A_MSIL1C_20130530T002611_N0207_R102_T01WCS_20130530T002609" "NRT" 1 "2013-08-04T2:19:45.728")
DATA_S2_RT=$(template "S2A_MSIL1C_20130530T002611_N0205_R102_T01WCS_20130530T002609" "RT" 0 "2013-08-04T2:19:45.728")

DATA_S3_NRT=$(template "S3A_SL_1_RBT____20130209T202834_20130209T203134_20130214T221404_0179_014_142_4320_SVL_O_NR_002" "NRT" 1 "2013-08-04T2:19:45.728")
DATA_S3_NRT1=$(template "S3A_SL_1_RBT____20130209T202834_20130209T203134_20130210T121404_0179_014_142_4320_SVL_O_NR_002" "NRT" 1 "2013-08-04T2:19:45.728")
DATA_S3_STC=$(template "S3A_SL_1_RBT____20130209T202834_20130209T203134_20130210T121404_0179_014_142_4320_SVL_O_ST_002" "STC" 0 "2013-08-04T2:19:45.728")
DATA_S3_NTC=$(template "S3A_SL_1_RBT____20130209T202834_20130209T203134_20130210T121404_0179_014_142_4320_SVL_O_NT_002" "NTC" 0 "2013-08-04T2:19:45.728")

#echo $DATA_S1_NRT_10

function catalog {
	data=$1
	collection=$2
	title=$3
	pattern=`getFeatureVersionPattern "$title" "$collection"`
	printf "****************************************************************************************\n"
	printf "*** Insertion du produit {{ %s }}\n" $title
	printf "****************************************************************************************\n\n"
	curl -s -X POST --noproxy ${host} --data "$data" http://${login}:${pwd}@${host}/resto/collections/${collection}
	printf "\n\n"
	
	psql -U postgres -d resto -c "select identifier, title, realtime, isnrt, new_version, visible, dhusingestdate from _${collection,,}.features where product_version(title, '$collection')='$pattern' order by published asc"
}


function clean {
	identifier=$1
	collection=$2
	
	curl -s -X DELETE --noproxy ${host} --data "$data" http://${login}:${pwd}@${host}/resto/collections/${collection}/${identifier} > /dev/null
}

function getFeatureVersionPattern {
    
	productIdentifier=$1
	collection=$2 
	length=${#productIdentifier}
	
	if [ "$collection" == "S1" ]
	then
	        echo ${productIdentifier:0:($length-5)};
	elif [ "$collection" == "S2ST" ]
	then
	        echo ${productIdentifier:0:28}${productIdentifier:32} 
	elif [ "$collection" == "S3" ]
	then
	        echo ${productIdentifier:0:48}${productIdentifier:64:24}${productIdentifier:91}
	else
	echo 
	fi
}


#echo `getFeatureVersionPattern "S1A_EW_OCN__2SDH_20130804T201841_20130804T201945_007118_009B38_N10M" "S1"`


host=192.168.56.102
login=admin
pwd=admin

collection='S1'

printf "####################################################################################################\n"
printf "####################################################################################################\n"
printf "# Validation des produit %s\n" $collection
printf "\n####################################################################################################\n"
printf "####################################################################################################\n\n"
catalog "$DATA_S1_NRT_1" "$collection" "S1A_EW_OCN__2SDH_20130804T201841_20130804T201945_007118_009B38_N1H0"
catalog "$DATA_S1_NRT_10" "$collection" "S1A_EW_OCN__2SDH_20130804T201841_20130804T201945_007118_009B38_N10M"
catalog "$DATA_S1_FAST_24" "$collection" "S1A_EW_OCN__2SDH_20130804T201841_20130804T201945_007118_009B38_F24H"
catalog "$DATA_S1_NRT_3" "$collection" "S1A_EW_OCN__2SDH_20130804T201841_20130804T201945_007118_009B38_N3H0"
catalog "$DATA_S1_OFFLINE" "$collection" "S1A_EW_OCN__2SDH_20130804T201841_20130804T201945_007118_009B38_OFFL"
catalog "$DATA_S1_REPROCESSING" "$collection" "S1A_EW_OCN__2SDH_20130804T201841_20130804T201945_007118_009B38_REPR"
printf "\n####################################################################################################\n"
printf "# Suppression du jeu de données de test de la base de données resto"
printf "\n####################################################################################################\n\n\n"
clean "26d336ca-5a40-53a3-a163-875223da2bb2" "$collection"
clean "064a1b8a-4557-5060-9d70-36fede0a07d3" "$collection"
clean "42fce4e4-1852-5d4e-89a4-d58711d1f1e1" "$collection"
clean "f15316aa-c1e4-5d74-a3f9-d8e1b3a17c60" "$collection"
clean "c84d7643-044d-58d3-8287-ec6d9e7cba28" "$collection"
clean "42752403-537a-5367-b138-1b647fcff717" "$collection"

collection='S2ST'
printf "\n\n####################################################################################################\n"
printf "\n####################################################################################################\n"
printf "# Validation des produit %s\n" $collection
printf "\n####################################################################################################\n"
printf "####################################################################################################\n\n"

#echo `getFeatureVersionPattern "S2A_MSIL1C_20130530T002611_N0210_R102_T01WCS_20130530T002609" "$collection"`

catalog "$DATA_S2_NOMINAL_10" "$collection" "S2A_MSIL1C_20130530T002611_N0210_R102_T01WCS_20130530T002609"
catalog "$DATA_S2_NRT" "$collection" "S2A_MSIL1C_20130530T002611_N0207_R102_T01WCS_20130530T002609"
catalog "$DATA_S2_RT" "$collection" "S2A_MSIL1C_20130530T002611_N0205_R102_T01WCS_20130530T002609"
printf "\n####################################################################################################\n"
printf "# Suppression du jeu de données de test de la base de données resto"
printf "\n####################################################################################################\n\n\n"
clean "54b12415-52e0-5f67-a63c-1a62b9117b1b" "$collection"
clean "fdb17b32-3c90-5433-947c-22c66e88629f" "$collection"
clean "502485dc-76f1-59b7-9142-1a678322654c" "$collection"

collection='S3'
printf "\n\n####################################################################################################\n"
printf "\n####################################################################################################\n"
printf "# Validation des produit %s\n" $collection
printf "\n####################################################################################################\n"
printf "####################################################################################################\n\n"
catalog "$DATA_S3_NRT1" "$collection" "S3A_SL_1_RBT____20130209T202834_20130209T203134_20130210T121404_0179_014_142_4320_SVL_O_NR_002"
catalog "$DATA_S3_NRT" "$collection" "S3A_SL_1_RBT____20130209T202834_20130209T203134_20130210T221404_0179_014_142_4320_SVL_O_ST_002"
catalog "$DATA_S3_STC" "$collection" "S3A_SL_1_RBT____20130209T202834_20130209T203134_20130210T121404_0179_014_142_4320_SVL_O_ST_002"
catalog "$DATA_S3_NTC" "$collection" "S3A_SL_1_RBT____20130209T202834_20130209T203134_20130210T121404_0179_014_142_4320_SVL_O_NT_002"
printf "\n####################################################################################################\n"
printf "# Suppression du jeu de données de test de la base de données resto"
printf "\n####################################################################################################\n\n\n"
clean "653e9118-aac1-5761-97fd-279a1eb610d2" "$collection"
clean "c9d452df-12e0-59a1-92cf-85c9e204a4ba" "$collection"
clean "799659f1-513f-5233-a6ac-7382bb688e17" "$collection"
clean "56023de8-3ad2-5f0d-bf1f-ac593032ea85" "$collection"
clean "56023de8-3ad2-5f0d-bf1f-ac593032ea85" "$collection"

#curl -X POST --noproxy ${host} --data "$DATA_S1_NRT_10" http://${login}:${pwd}@${host}/resto/collections/${collection}
#curl -X POST --noproxy ${host} --data "$DATA_S1_NRT_1" http://${login}:${pwd}@${host}/resto/collections/${collection}
#curl -X POST --noproxy ${host} --data "$DATA_S1_NRT_3" http://${login}:${pwd}@${host}/resto/collections/${collection}
#curl -X POST --noproxy ${host} --data "$DATA_S1_FAST_24" http://${login}:${pwd}@${host}/resto/collections/${collection}
#curl -X POST --noproxy ${host} --data "$DATA_S1_OFFLINE" http://${login}:${pwd}@${host}/resto/collections/${collection}
#curl -X POST --noproxy ${host} --data "$DATA_S1_REPROCESSING" http://${login}:${pwd}@${host}/resto/collections/${collection}

#collection='S2ST'
#curl -X POST --noproxy ${host} --data "$DATA_S2_NOMINAL_10" http://${login}:${pwd}@${host}/resto/collections/${collection}
#curl -X POST --noproxy ${host} --data "$DATA_S2_NRT" http://${login}:${pwd}@${host}/resto/collections/${collection}
#curl -X POST --noproxy ${host} --data "$DATA_S2_RT" http://${login}:${pwd}@${host}/resto/collections/${collection}

#collection='S3'
#curl -X POST --noproxy ${host} --data "$DATA_S3_NRT" http://${login}:${pwd}@${host}/resto/collections/${collection}
#curl -X POST --noproxy ${host} --data "$DATA_S3_NRT1" http://${login}:${pwd}@${host}/resto/collections/${collection}
#curl -X POST --noproxy ${host} --data "$DATA_S3_STC" http://${login}:${pwd}@${host}/resto/collections/${collection}
#curl -X POST --noproxy ${host} --data "$DATA_S3_NTC" http://${login}:${pwd}@${host}/resto/collections/${collection}


