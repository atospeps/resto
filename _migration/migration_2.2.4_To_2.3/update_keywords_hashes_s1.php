<?php

// Resto Parameters
$resto_db = array(
  'host'=>'localhost',
  'db'=>'resto',
  'user'=>'resto',
  'password'=>'resto',
);


// Get the database connection
$db = verify_db($resto_db);
//Total of products
$total_products = get_total_products($db);
echo " Updating data base (can take several minutes)....". PHP_EOL;
// As we can't get all products at one time (out of memory problem
// we use offset and limit.
$limit = 1000;
$offset = 0;
do {
    // Get the products from data base needed to be updated
    $products = get_products_update($db, $limit, $offset);
    if ($products !== false) {
        // We update the products on the array
        $updated_products = array_update($products);
        update_db($db, $updated_products);
    }
    $offset = $offset + $limit;
} while ($offset < $total_products);

echo '------- Update finsihed -------' . PHP_EOL;

/*
 * we update the elements in the database
 */
function update_db($db, $updated_products){
    foreach ($updated_products as $product) {
        $keywords = object_to_array(json_decode($product["keywords"]));
        $hashes = getHashes($keywords);
        $result_update = pg_query($db, "UPDATE _s1.features SET keywords='" . pg_escape_string($product["keywords"]) . "', hashes='" . pg_escape_string($hashes) . "' WHERE identifier='" . $product["identifier"] . "'");    
    }
}

/*
 * We update the entering array with the correct keywords
 */
function array_update($products){
    // We iterate over each product
    foreach ($products as $product_key=>$product) {        
        // IMPORTNAT!!
        // On the current facets hierarchy, sensorMode is parent of polarisation, and orbitdirection is alone
        /* .....
                'instrument',
                'sensorMode',
                'polarisation'
            ),
            array(
                'orbitDirection'
            ),
            array(
                'continent',
                'country',
           ...... 
         */
        // We need to know if the keyword has a father or not in order to place the parentHash attribute 
        // If the hierarchy on resto is updated the same will have to be done here
        
        $keywords = json_decode($product["keywords"]);
        /*--------------------------------------------*/
        //polarisation needs to be updated
        if (isset($products[$product_key]['updatePolarisation'])) {
            $parentHash = get_hash_keyword_element($keywords, 'sensorMode');  
            // We create the keyword
            $new_keyword = create_keyword($product['polarisation'], 'polarisation', $parentHash);
            $hash = createHash('polarisation:'.$product['polarisation'], $parentHash);
            // We insert the keyword
            $keywords->$hash = $new_keyword;
            unset($products[$product_key]['updatePolarisation']);
        }
        /*--------------------------------------------*/
        //We insert the orbitdirection
        if (isset($products[$product_key]['updateOrbitdirection'])) {
            // We create the keyword
            $new_keyword = create_keyword($product['orbitdirection'], 'orbitDirection', false);
            $hash = createHash('orbitDirection:'.$product['orbitdirection']);
            // We insert the keyword
            $keywords->$hash = $new_keyword;
            unset($products[$product_key]['updateOrbitdirection']);
        }
        /*--------------------------------------------*/
        // We have to verify !!ALWAYS!! the processingLevel
        // We delete the current processingLevel. We will replace it afterwards
        $processingLevelKey = get_hash_keyword_element($keywords, 'processingLevel');
        unset($keywords->$processingLevelKey);
        // We create the processingLevel keyword
        $new_keyword = create_keyword($product['processinglevel'], 'processingLevel', false);
        $hash = createHash('processingLevel:'.$product['processinglevel']);
        // We insert it
        $keywords->$hash = $new_keyword;
        
        
    //We insert the keywords in the product     
    $products[$product_key]["keywords"] = json_encode($keywords);
    }
  return $products;
}
    
    /*
 * Get products which need to update their keywords and hashes
 */
function get_products_update($db, $limit, $offset) {
    // We get the only the elements which have one of the two elements filled
    $result = pg_query($db, "SELECT identifier, processinglevel, keywords, orbitdirection, polarisation FROM _s1.features ORDER BY identifier LIMIT " . $limit . " OFFSET " . $offset);
    if ($result !== false) {
        
        $products = pg_fetch_all($result);
        foreach ($products as $key => $product) {
            // we verify the orbitdirection field is filled
            if (!verify_element_exists_keywords('polarisation', $product)) {
                $products[$key]['updatePolarisation'] = true;
            }
            // I unset is already false, we need to update, no need to validate polarisation
            if (!verify_element_exists_keywords('orbitdirection', $product)) {
                $products[$key]['updateOrbitdirection'] = true;
            }
        }
        return $products;
    } else {
        return false;
    }
}

/*
 * Function taken from REsto
 * Convert the keywords into hash
 */
function getHashes($keywords) {
    $hashes = array();
    foreach (array_keys($keywords) as $hash) {
        /*
         * Do not index location if cover is lower than 10 %
         */
        if (in_array($keywords[$hash]['type'], array('country', 'region', 'state'))) {
            if (!isset($keywords[$hash]['value']) || $keywords[$hash]['value'] < 10) {
                continue;
            }
        }
        $hashes[] = '"' . pg_escape_string($hash) . '"';
        $hashes[] = '"' . pg_escape_string($keywords[$hash]['type'] . ':' . (isset($keywords[$hash]['normalized']) ? $keywords[$hash]['normalized'] : strtolower($keywords[$hash]['name']))) . '"';
    }
    return '{' . pg_escape_string(join(',', $hashes)) . '}';;
}


/*
 * Function taken from REsto
 * Create the hash
 */
function createHash($input, $parent = null) {
    return substr(sha1($input . (isset($parent) ? ',' . $parent : '')), 0, 15);
}

/*
 * We create a stdClass which will be a keyword
 */
function create_keyword($name, $type, $parentHash){
    // We create the keyword
    $object = new stdClass();
    $object->name = $name;
    $object->type = $type;
    if ($parentHash !== false) {
        $object->parentHash = $parentHash;
    }
    return $object;
}

/*
 * We get the has key of a certain keyword
 */
function get_hash_keyword_element($keywords, $element) {
    foreach ($keywords as $key => $value) {
        if ($value->type === $element) {
            return $key;
        }
    }
    return false;
}
 

/*
 * We verify if element is set in column and in keywords 
 */
function verify_element_exists_keywords($element, $product) {
    // Validate if column is set
    if (!empty($product[$element]) && !is_null($product[$element])) {
        // We validate if element is in the keywords
        if ((strpos($product['keywords'], '"' . $element . '"') === false)) {
            // Yes in column, not in keywords. Product to update
            return false;
        }else{
            // Yes in column, yes in columns. No ti be updated
            return true;
        }
    }else{
        // Product not in column, no need to update
        return true;
    }
}

/*
 * Convert recursively an object to an array
 */
function object_to_array($obj) {
    if(is_object($obj)) $obj = (array) $obj;
    if(is_array($obj)) {
        $new = array();
        foreach($obj as $key => $val) {
            $new[$key] = object_to_array($val);
        }
    }
    else $new = $obj;
    return $new;
}

/*
 * A count for total products
 */
function get_total_products($db){
    $result = pg_query($db, "SELECT count(*) FROM _s1.features");
    $json = pg_fetch_assoc($result);
    echo "- Total products in RESTo: " . $json["count"] . PHP_EOL;
    return intval($json["count"]);
}

/*
 * We validate a data base
 */
function verify_db($db){
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

