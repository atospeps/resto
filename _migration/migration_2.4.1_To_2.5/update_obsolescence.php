<?php

/*
 * update_obsolescence.php -d <database> -u <user> - p <password>
 */

//ini_set('display_errors', '1');
date_default_timezone_set('UTC');
error_reporting(E_ALL);

$options = getopt('d:u:p:');

/*************************************************/
/*************************************************/

$obsolescenceS1useDhusIngestDate = false;

$resto_db = array(
  'db'       => 'resto',
  'host'     => isset($options['d']) ? $options['d'] : 'localhost',
  'user'     => isset($options['u']) ? $options['u'] : 'resto',
  'password' => isset($options['p']) ? $options['p'] : 'resto',
);

$db = check_db($resto_db);

/*************************************************/
/*************************************************/

output('*** STARTING OBSOLESCENCE UPDATE ***');

update_obsolescence();

output('*** OBSOLESCENCE UPDATE FINISHED ***');

/*************************************************/
/*************************************************/


/**
 * Update visible and new_version for all collections
 */
function update_obsolescence()
{    
    setVisibleNewVersion('S1');
    setVisibleNewVersion('S2ST');
    setVisibleNewVersion('S3');

}

/**
 * Set visible and new_version for the specified collection name
 * 
 * @param string $collectionName
 */
function setVisibleNewVersion($collectionName)
{
    $schema = '_' . strtolower($collectionName);
    
    output('Updating collection '.$collectionName . '...');
    
    // all the non-NRT products are set to visible
    query("UPDATE " . $schema . ".features SET visible = 1, new_version = NULL");
    
    output("Intialization finished successfully...");
    
    $count = 0;
    
    // for all the NRT products...
    $r = query("SELECT * FROM " . $schema . ".features WHERE isnrt = 1");
    if (!$r) {
        output("Une erreur est survenue");
        exit;
    }
    while ($nrtProduct = pg_fetch_assoc($r)) {
        $count++;

        if ($count % 10000 == 0){
            output("$count products updated successfully...");
        }
        // get all the versions of the current product
        $allVersions = getAllVersions($collectionName, $nrtProduct['productidentifier']);

        if (count($allVersions) > 1) {
            // the newest version is set to visible
            $newestVersion = $allVersions[0];

            // the other versions (NRT) become invisible
            array_shift($allVersions);
            foreach ($allVersions as $version) {
                if ((int)$version['isnrt'] === 1) {
                    $whereClause    = ' WHERE identifier=\'' . $version['identifier'] . '\'';
                    $updateClause   = ' SET visible=0, new_version=\'' . $newestVersion['identifier'] . '\'';
                    $query = 'UPDATE ' . $schema . '.features' . $updateClause . $whereClause;
                    query($query);
                }
            }
        }        
    } 
    output("Finished : $count products updated.");
}

/**
 * Get all version
 */
function getAllVersions($collectionName, $productIdentifier)
{
    global $obsolescenceS1useDhusIngestDate;
    
    $schema = '_' . strtolower($collectionName);

    // WHERE
    $pattern = getFeatureVersionPattern($productIdentifier, $collectionName);
    $whereClause = " WHERE product_version(title, '" . $collectionName . "')='" . pg_escape_string($pattern) . "'";

    // FROM
    $fromClause  = " FROM " . pg_escape_string($schema) . ".features";

    // ORDER BY
    switch($schema) {
        case '_s1':
            $orderByClause = " ORDER BY"
                    .   " isnrt ASC,"
                    .   " CASE realtime"
                    .     " WHEN 'Reprocessing' THEN 1"
                    .     " WHEN 'Off-line'     THEN 2"
                    .     " WHEN 'Fast-24h'     THEN 3"
                    .     " WHEN 'NRT-3h'       THEN 4"
                    .     " WHEN 'NRT-1h'       THEN 5"
                    .     " WHEN 'NRT-10m'      THEN 6"
                    .     " ELSE 7"
                    .   " END";
            if (obsolescenceS1useDhusIngestDate === true) {
                $orderByClause .= ", dhusingestdate DESC";
            }
            break;
        case '_s2st':
            $orderByClause = " ORDER BY"
                    .   " isnrt ASC,"
                    .   " CASE realtime"
                    .     " WHEN 'Nominal' THEN 1"
                    .     " WHEN 'NRT'     THEN 2"
                    .     " WHEN 'RT'      THEN 3"
                    .     " ELSE 4"
                    .   " END,"
                    .   " SUBSTRING (productidentifier, 29, 4) DESC"; // version number
            break;
        case '_s3':
            $orderByClause = " ORDER BY"
                    . " isnrt ASC,"
                    .   " CASE realtime"
                    .     " WHEN 'NTC' THEN 1"
                    .     " WHEN 'STC' THEN 2"
                    .     " WHEN 'NRT' THEN 3"
                    .     " ELSE 4"
                    .   " END,"
                    . " SUBSTRING (productidentifier, 49, 15) DESC"; // creation date
              break;
        default:
            break;
    }

    // QUERY
    $query = 'SELECT *';
    $query .= $fromClause;
    $query .= $whereClause;
    $query .= $orderByClause;
    
    // RESULTS
    $results = query($query);
    
    $versions = array();
    while ($result = pg_fetch_assoc($results)) {
        $versions[] = $result;
    }
    
    return $versions;
}

/**
 * Get feature version pattern
 */
function getFeatureVersionPattern($productIdentifier, $collection)
{
    $length = strlen($productIdentifier);
    
    $regexFeatureVersions = null;
    switch ($collection) {
        case 'S1' :
            /*
             * ignore checksum (CCCC)
             *      MMM_BB_TTTR_LFPP_YYYYMMDDTHHMMSS_YYYYMMDDTHHMMSS_OOOOOO_DDDDDD_CCCC
             *      pattern version ==> MMM_BB_TTTR_LFPP_YYYYMMDDTHHMMSS_YYYYMMDDTHHMMSS_OOOOOO_DDDDDD
             */
            $regexFeatureVersions = substr($productIdentifier, 0, $length - 5);
            break;
        case 'S2' :
            /*
             * ignore ... (yyyymmddThhmmss)
             *    MMM_CCCC_TTTTTTTTTT_ssss_yyyymmddThhmmss_ROOO_VYYYYMMTDDHHMMSS_YYYYMMTDDHHMMSS
             *    pattern version ==> MMM_CCCC_TTTTTTTTTT_ssss_ROOO_VYYYYMMTDDHHMMSS_YYYYMMTDDHHMMSS
             */
            $regexFeatureVersions = substr($productIdentifier, 0, 24) . substr($productIdentifier, 40);
            break;
        case 'S2ST' :
            /*
             * ignore processing baseline number (xxyy)
             *      MMM_MSIL1C_YYYYMMDDTHHMMSS_Nxxyy_ROOO_Txxxxx_YYYYMMDDTHHMMSS
             *      pattern version==> MMM_MSIL1C_YYYYMMDDTHHMMSS_N_ROOO_Txxxxx_YYYYMMDDTHHMMSS
             */
            $regexFeatureVersions = substr($productIdentifier, 0, 28) . substr($productIdentifier, 32);
            break;
        case 'S3' :
            /*
             * ignore product creation date + timeliness
             *      MMM_OL_L_TTTTTT_yyyymmddThhmmss_YYYYMMDDTHHMMSS_YYYYMMDDTHHMMSS_IIIIIIIIIIIIIIIII_GGG_P_XX_NNN
             *      pattern version => MMM_OL_L_TTTTTT_YYYYMMDDTHHMMSS_YYYYMMDDTHHMMSS_IIIIIIIIIIIIIIIII_GGG_P_NNN
             */
            $regexFeatureVersions = substr($productIdentifier, 0, 48)
            . substr($productIdentifier, 64, 24)
            . substr($productIdentifier, 91);
            break;
        default :
            break;
    }
    
    return $regexFeatureVersions;
}

/**
 * Query
 */
function query($query)
{
    global $db;
    return pg_query($db, $query);
}

/**
 * Output message
 */
function output($s) {
    echo '[' . date("Y-m-dTH:i:s"). ']  ' . $s . PHP_EOL;
}

/**
 * We validate a data base
 */
function check_db($db)
{
    $connectionString = "host=" . $db['host'] . " dbname=" . $db['db'] . " user=" . $db['user'] . (!empty($db['password']) ? (" password=" . $db['password']) : "");
    $db_connection = pg_connect( $connectionString );
    if (!$db_connection) {
        echo "Error connecting to the " . $db['db'] .' data base' . PHP_EOL;
        exit();
    } else {
        echo 'Connected to '. $db['db'] .' data base' . PHP_EOL;
        return $db_connection;
    }
}
