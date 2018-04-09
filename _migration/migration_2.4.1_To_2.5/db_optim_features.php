<?php

/*
 * db_optim_features.php -d <database> -u <user> - p <password>
 */

date_default_timezone_set('Europe/Paris'); // correctif warnings qualif (normalement il faudrait modifier php.ini pour définir un timezone)
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

$db = check_db($resto_db);

/*************************************************/
/*************************************************/

output('STARTING OPTIMIZATION');

vacuumFeatures();

output('OPTIMIZATION FINISHED');

/*************************************************/
/*************************************************/

/**
 * Nettoyage/optimisation des tables features
 */
function vacuumFeatures()
{
    output("  _s1.features...");
    query('vacuum analyse _s1.features');
    
    output("  _s2.features...");
    query('vacuum analyse _s2.features');
    
    output("  _s2st.features...");
    query('vacuum analyse _s2st.features');
    
    output("  _s3.features...");
    query('vacuum analyse _s3.features');
    
    output("  resto.features...");
    query('vacuum analyse resto.features');
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
function check_db($db)
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
