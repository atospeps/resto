<?php

/*
 * update_realtime.php -d <database> -u <user> - p <password>
 */

//ini_set('display_errors', '1');
date_default_timezone_set('Europe/Paris');
error_reporting(E_ALL);

$options = getopt('d:u:p:');

/*************************************************/
/*************************************************/

$resto_db = array(
  'db'       => 'resto',
  'host'     => isset($options['d']) ? $options['d'] : 'localhost',
  'user'     => isset($options['u']) ? $options['u'] : 'resto',
  'password' => isset($options['p']) ? $options['p'] : 'resto',
);

$db = verify_db($resto_db);

/*************************************************/
/*************************************************/

output('STARTING REALTIME UPDATE');

update_realtime();

output('REALTIME UPDATE FINISHED');

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
    query("UPDATE _s1.features   SET realtime = 'Fast-24h'     WHERE isnrt = 0");     // [DEV] 11m pour 417 000 lignes
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
