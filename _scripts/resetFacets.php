<?php

/*************************************************/
/**                 VARIABLES                   **/
/*************************************************/
// Data base Parameters
$resto_db = array(
  'host'=>'localhost',
  'db'=>'resto',
  'user'=>'resto',
  'password'=>'resto',
);

$limit = 1000;
$offset = 0;

/*************************************************/
/**                MAIN                         **/
/*************************************************/
date_default_timezone_set('Europe/Paris');
echo 'Debut : ' . date("Y-m-d H:i:s") . PHP_EOL;

// Get the database connection
$db = verify_db($resto_db);

// Delete all facets
deleteFacets($db);

$features = getFeatures($db, $limit, $offset);
$count = 0;

while(count($features) > 0) {

    $count += count($features);
    $ch = curl_init("http://admin:admin@localhost/resto/administration/facets/update");
    $data = json_encode($features);
    
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);      
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
        'Content-Type: application/json',                                                                                
        'Content-Length: ' . strlen($data))                                                                       
    );      
    
    curl_exec($ch);
    curl_close($ch);

    $features = array();
    $offset += $limit;
    $features = getFeatures($db, $limit, $offset);
}

echo '------- Facets successfully created -------' . PHP_EOL;
echo '------- ' . $count . ' features processed -------' . PHP_EOL;

echo 'Fin : ' . date("Y-m-d H:i:s") . PHP_EOL;


/*************************************************/
/**                FUNCTIONS                    **/
/*************************************************/

/*
 * Delete all resto.facets.
 */
function deleteFacets($db) {
    pg_query($db, "DELETE FROM resto.facets ");
    echo '------- Facets successfully deleted -------' . PHP_EOL;
}

/*
 * Get $number features from $offset.
 */
function getFeatures($db, $number, $offset) {
    $query = 'SELECT collection, keywords FROM resto.features LIMIT ' . $number . ' OFFSET ' . $offset;
    $results = pg_query($db, $query);

    $features = array();
    while ($result = pg_fetch_assoc($results)) {
        $features[] = array(
                'collection' => $result['collection'],
                'keywords' => json_decode(trim($result['keywords'], '\''), true)
        );
    }

    echo '------- Retrieve features ' . $offset . ' to ' . ($offset + $number) . ' -------' . PHP_EOL;
    return $features;
}

/*
 * Try to connect to the database
 */
function verify_db($db){
    echo '------- Database validation -------' . PHP_EOL;
    $db_connection = pg_connect("host=" . $db['host'] . " dbname=" . $db['db'] . " user=" . $db['user'] . " password=" . $db['password'] );
    if (!$db_connection) {
        echo "------- Error connection to the " . $label .' database -------' . PHP_EOL;
        exit();
    } else {
        echo '------- Connected to '. $db['db'] .' database -------' . PHP_EOL;
        return $db_connection;
    }
}

