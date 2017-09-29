<?php

/*
 * update_nrt.php -d <database> -u <user> - p <password>
 */

//ini_set('display_errors', '1');
date_default_timezone_set('Europe/Paris');
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

$db = verify_db($resto_db);

/*************************************************/
/*************************************************/

output('STARTING UPDATE');

update_realtime();
update_visible_newversion();
vacuumFeatures();

output('UPDATE FINISHED');

/*************************************************/
/*************************************************/

/**
 * Update realtime
 */
function update_realtime()
{
    output(" updating realtime...");
    
    // S1
    output("  S1");
    query("UPDATE _s1.features   SET realtime = 'Reprocessing' WHERE isnrt = 0");     // [DEV] 11m pour 417 000 lignes
    query("UPDATE _s1.features   SET realtime = 'NRT-3h'       WHERE isnrt = 1");
    
    // S2ST
    output("  S2ST");
    query("UPDATE _s2st.features SET realtime = 'Nominal'      WHERE isnrt = 0");
    query("UPDATE _s2st.features SET realtime = 'NRT'          WHERE isnrt = 1");
    
    // S3
    output("  S3");
    $query = "UPDATE _s3.features "
           . "SET realtime = CASE "
           .                  "WHEN SUBSTR(productidentifier, 89, 2) = 'NR' THEN 'NRT' "
           .                  "WHEN SUBSTR(productidentifier, 89, 2) = 'ST' THEN 'STC' "
           .                  "ELSE 'NTC' "
           .                "END;";
    query($query);
    
    output(" OK");
}

/**
 * Update visible and new_version for all collections
 */
function update_visible_newversion()
{
    output(" updating visible flag and new_version...");
    
    setVisibleNewVersion('S1');
    setVisibleNewVersion('S2ST');
    setVisibleNewVersion('S3');
    
    output(" OK");
}

/**
 * Set visible and new_version for the specified collection name
 * 
 * @param string $collectionName
 */
function setVisibleNewVersion($collectionName)
{
    $schema = '_' . strtolower($collectionName);
    
    output("  ".$collectionName);
    
    // all the non-NRT products are set to visible
    query("UPDATE " . $schema . ".features SET visible = 1, new_version = NULL WHERE isnrt = 0");
    
    // for all the NRT products...
    //$nrtProducts = getAllNRTProducts($collectionName);
    $r = query("SELECT * FROM " . $schema . ".features WHERE isnrt = 1");
    while ($nrtProduct = pg_fetch_assoc($r)) {
        // get all the versions of the current product
        $allVersions = getAllVersions($collectionName, $nrtProduct['productidentifier']);
        if (count($allVersions)) {
            // the newest version is set to visible
            $newestVersion = $allVersions[0];
            query("UPDATE " . $schema . ".features SET visible = 1, new_version = NULL WHERE identifier = '" . $newestVersion['identifier'] . "'");
            // the other versions (NRT) become invisible
            array_shift($allVersions);
            foreach ($allVersions as $version) {
                if ((int)$version['isnrt'] === 1) {
                    query("UPDATE " . $schema . ".features SET visible = 0, new_version = '" . $newestVersion['identifier'] . "' WHERE identifier = '" . $version['identifier'] . "'");
                }
            }
        }
    }
}

/**
 * Get all NRT products for a specified collection
 */
/*function getAllNRTProducts($collectionName)
{
    $schema = '_' . strtolower($collectionName);
    
    $query = " SELECT *"
           . " FROM " . $schema . ".features"
           . " WHERE isnrt = 1";
    
    $results = query($query);
    
    $products = array();
    while ($result = pg_fetch_assoc($results)) {
        $products[] = $result;
    }
    
    return $products;
}*/

/**
 * Get all version
 */
function getAllVersions($collectionName, $productIdentifier)
{
    global $obsolescenceS1useDhusIngestDate;
    
    $schema = '_' . strtolower($collectionName);
    
    $pattern = getFeatureVersionPattern($productIdentifier, $collectionName);

    // WHERE
    $whereClause = " WHERE productidentifier LIKE '" . pg_escape_string($pattern) . "'";
    
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
            if ($obsolescenceS1useDhusIngestDate === true) {
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
            // ignore checksum (CCCC)
            //      MMM_BB_TTTR_LFPP_YYYYMMDDTHHMMSS_YYYYMMDDTHHMMSS_OOOOOO_DDDDDD_CCCC
            $regexFeatureVersions = substr($productIdentifier, 0, $length - 4) . '%';
            break;
        case 'S2' :
            // ignore ... (yyyymmddThhmmss)
            //      MMM_CCCC_TTTTTTTTTT_ssss_yyyymmddThhmmss_ROOO_VYYYYMMTDDHHMMSS_YYYYMMTDDHHMMSS
            $regexFeatureVersions = substr($productIdentifier, 0, 25) . '%' . substr($productIdentifier, 40);
            break;
        case 'S2ST' :
            // ignore processing baseline number (xxyy)
            //      MMM_MSIL1C_YYYYMMDDTHHMMSS_Nxxyy_ROOO_Txxxxx_YYYYMMDDTHHMMSS
            $regexFeatureVersions = substr($productIdentifier, 0, 28) . '%' . substr($productIdentifier, 32);
            break;
        case 'S3' :
            // ignore product creation date + center code + timeliness
            //      MMM_OL_L_TTTTTT_yyyymmddThhmmss_YYYYMMDDTHHMMSS_YYYYMMDDTHHMMSS_IIIIIIIIIIIIIIIII_GGG_P_XX_NNN
            $regexFeatureVersions = substr($productIdentifier, 0, 48)  . '%'    // product creation date (YYYYMMDDTHHMMSS)
                                  . substr($productIdentifier, 63, 19) . '%'    // center code (GGG)
                                  . substr($productIdentifier, 85, 3)  . '%'    // timeliness (XX)
                                  . substr($productIdentifier, 90);
            break;
    }
    
    return $regexFeatureVersions;
}

/**
 * Nettoyage/optimisation des tables features
 */
function vacuumFeatures()
{
    output(" database optimisation...");
    
    output("  _s1.features");
    query('vacuum analyse _s1.features');
    
    output("  _s2.features");
    query('vacuum analyse _s2.features');
    
    output("  _s2st.features");
    query('vacuum analyse _s2st.features');
    
    output("  _s3.features");
    query('vacuum analyse _s3.features');
    
    output("  resto.features");
    query('vacuum analyse resto.features');
    
    output(" OK");
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
    echo $s . PHP_EOL;
}

/**
 * We validate a data base
 */
function verify_db($db)
{
    $db_connection = pg_connect("host=" . $db['host'] . " dbname=" . $db['db'] . " user=" . $db['user'] . " password=" . $db['password'] );
    if (!$db_connection) {
        echo "Error connecting to the " . $db['db'] .' data base' . PHP_EOL;
        exit();
    } else {
        echo 'Connected to '. $db['db'] .' data base' . PHP_EOL;
        return $db_connection;
    }
}
