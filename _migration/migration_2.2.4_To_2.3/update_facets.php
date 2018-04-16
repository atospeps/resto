<?php

/*************************************************/
/********* VARIABLES *****************************/
// Data base Parameters
$resto_db = array(
  'host'=>'localhost',
  'db'=>'resto',
  'user'=>'resto',
  'password'=>'resto',
);

// Elements to validate
$verify = array (
        'processingLevel',
        'instrument',
        'polarisation', 
        'orbitDirection', 
        'swath'
);

// Collection to update. 
// Write it as it's stored on the facets table
$collection_facets = 'S1';
// Write it as it's stored in the database
$collection_features = '_s1';

/*************************************************/
/*************************************************/

// Array to store all the results
$facets_status = array();
// Get the database connection
$db = check_db($resto_db);
// We get the current 
$status = verify_facets($db, $verify, $collection_features);
// update values in databasel
update_db($db, $status, $collection_facets);

echo '------- Update finsihed -------' . PHP_EOL;


/*
 * Update values in database
 */
function update_db($db, $status, $collection) {
    // An update has been done
    $updated = FALSE;
    // We get the types
    foreach ($status as $type => $values) {
        // We get all the possible names for a type currently stored in the database
        $distincts = getDistinct($db, $type, $collection);
        // For each type we get the different names and their counts
        foreach ($values as $name => $attributes) {
            // We verify if the facet exists in the table
            $exists = pg_query($db, "SELECT counter FROM resto.facets WHERE value='" . $name . "' AND type='" . $type . "' AND collection='" . $collection . "'");
            // There is no row, so we insert it
            if (pg_num_rows($exists) === 0) {
                // If parent hash is set we have to insert it
                if (isset($attributes["pid"])) {
                    $insert = pg_query($db, "INSERT INTO resto.facets (uid,value,type,pid,collection,counter)
                             VALUES ('" . $attributes["uid"] . "','" . $name . "','" . $type . "','" . $attributes["pid"] . "', '" . $collection . "', '" . $attributes["count"] . "')");
                }else{
                    $insert = pg_query($db, "INSERT INTO resto.facets (uid,value,type,collection,counter)
                             VALUES ('" . $attributes["uid"] . "','" . $name . "','" . $type . "', '" . $collection . "', '" . $attributes["count"] . "')");
                }
                if ($insert !== FALSE) {
                    echo '==> New facet inserted' . PHP_EOL;
                    echo 'Facet ' . $type . '-' . $name . PHP_EOL;
                }
            } else {
                // As we use the name, we delete it from the array
                unset($distincts[$name]);
                $result = pg_query($db, "SELECT counter FROM resto.facets WHERE value='" . $name . "' AND type='" . $type . "' AND collection='" . $collection . "'");
                $json = pg_fetch_assoc($result);
                $result_update = pg_query($db, "UPDATE resto.facets SET counter=" . $attributes["count"] . " WHERE value='" . $name . "' AND type='" . $type . "' AND collection='" . $collection . "'");
                if ($json["counter"] != $attributes["count"] && $result_update !== FALSE) {
                    echo '-> Type: ' . $type . ' - Name: ' . $name . PHP_EOL;
                    echo "Old value: " . $json["counter"] . " - New value: " . $attributes["count"] . PHP_EOL;
                    $updated = TRUE;
                }
            }
        }
        // We see if there's any element in the array. If yes, means this face is obsolet (not use in any product) and has to be removed
        if (!empty($distincts)) {
            echo '==> Remove obsolete facets' . PHP_EOL;
            $delete_file = fopen("delete_facets_queries.sql", "a");
            date_default_timezone_set('Europe/Paris');
            fwrite($delete_file,'-- ' . date('Y-m-d H:i:s') .  PHP_EOL);
            foreach ($distincts as $distinct) {
                fwrite($delete_file, "DELETE FROM resto.facets WHERE value='" . $distinct . "' AND type='" . $type . "' AND collection='" . $collection . "';" . PHP_EOL);
                echo 'Facet ' . $type . '-' . $distinct . ' can be removed' . PHP_EOL;
            }
            fclose($delete_file);
        }
    }
    if (!$updated) {
        echo '==> No updates has been done' . PHP_EOL;
    }
}


/*
 * We iterate over all the products and store the current keyword status in an array
 */
function verify_facets($db, $verify, $collection) {
    // Get total of products
    $total_products = get_total_products($db, $collection); 
    // Just in case the are no results
    if ($total_products === 0) {
        echo 'ERROR: there are no producs in RESTo' . PHP_EOL;
        exit();
    }
    echo '------- Starting facets analysis -------' . PHP_EOL;
    // We don't want to load all db products in one query as we can have memory problems
    // We limit an offset
    $limit = 1000;
    $offset = 0;
    // Array to store all the results
    $facet_status = array();
    do{
        $keywords = get_keywords(pg_query($db, "SELECT keywords FROM " . $collection . ".features ORDER BY identifier LIMIT " . $limit . " OFFSET " . $offset));
        $status = analyse_keywords($keywords, $verify, $facet_status);
        $offset = $offset + $limit;
        $facet_status = $status;
    } while ($offset < $total_products);   
    return $facet_status;
}


/*
 * We read the jeywords for a row and we update the $facets array with all the counting
 */
function analyse_keywords($keywords, $verify, $facet_status){
    // We analyse all the keywords of an specific row
    foreach ($keywords  as $keyword) {
        // If a keyword is in the list 
        if(in_array($keyword->type, $verify)){
            // If the type and value are already set on the array we just make 1++
            if (isset($facet_status[$keyword->type][$keyword->name])) {
                $facet_status[$keyword->type][$keyword->name]['count']++;
            }else{
                // If the type and value are not set we in itialize the count
                $facet_status[$keyword->type][$keyword->name]['count'] = 1;
            }
            // We get the uid and parent id just in case we have to insert the element
            $facet_status[$keyword->type][$keyword->name]['uid'] = $keyword->uid;
            if (isset($keyword->parentHash)) {
                $facet_status[$keyword->type][$keyword->name]['pid'] = $keyword->parentHash;
            } 
        }
    }
    return $facet_status;
}

/*
 * We get the keywords and establish them as an array
 */
function get_keywords($query){
    // We get all the results in an array
    $rows = pg_fetch_all($query);
    // We format them in the correct ways so we can treat them easily afterwards
    $result = array();
    foreach ($rows as $row) {
        $keywords = json_decode($row["keywords"]);
        // We iterate once again over the results to erase the key ion the array
        foreach ($keywords as $key=>$value) {
            // We add the key of the object just as an atribute. This way we can easier work with it
            $value->uid=$key;
            $result[] = $value;
        }
    }
    return $result;
}


/*
 * A count for total products
 */
function get_total_products($db, $collection){
    $result = pg_query($db, "SELECT count(*) FROM " . $collection . ".features");
    $json = pg_fetch_assoc($result);
    echo "- Total products in RESTo: " . $json["count"] . PHP_EOL;
    return intval($json["count"]);
}

/*
 * Get all possible values for a type on the current database 
 */
function getDistinct($db, $type, $collection){
    $results = pg_query($db, "SELECT DISTINCT value FROM resto.facets WHERE type='" . $type . "' AND collection='" . $collection . "'");
    $differents = pg_fetch_all($results);
    $result = array();
    // We put all distincts values in array
    if ($differents !== FALSE) {
    foreach ($differents as $different) {
        $result[$different["value"]] = $different["value"];
    }
    }
    return $result;
}

/*
 * We validate a data base
 */
function check_db($db){
    echo '------- Data base validation -------' . PHP_EOL;
    $db_connection = pg_connect("host=" . $db['host'] . " dbname=" . $db['db'] . " user=" . $db['user'] . " password=" . $db['password'] );
    if (!$db_connection) {
        echo "Error connectin to the " . $label .' data base' . PHP_EOL;
        exit();
    }else{
        echo 'Connected to '. $db['db'] .' data base' . PHP_EOL;
        return $db_connection;
    }
}

