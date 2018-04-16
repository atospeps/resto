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

// Collection to update. Write it as it's stored in the database
$collection = '_s1';

// Keywords update
// We set the keyword we want to update  as it will be written on the keywords column,
// We will transform then the capitol letters to get the column
// If the keyword has a parent, we add it as it's written as it is on the keywords
// If there's no parent, we set parent to  false
// If the parent of an element is in the $verify array, we have to place the parent first
// IMPORTANT: we must respect the parent hierarchy with the one in RestoFacetUtil.php
$verify = array(
        'processingLevel' => array(
                'parent'=>false,
        ),
        'orbitDirection' => array(
            'parent'=>false,
        ),
        'instrument' => array(
            'parent'=>'platform',
        ),
        'polarisation' => array(
            'parent'=>'sensorMode',
        ),
        'swath' => array(
            'parent'=>false,
        )
);
/*************************************************/
/*************************************************/


// Get the database connection
$db = check_db($resto_db);
//Total of products
$total_products = get_total_products($db, $collection);
echo " Updating data base (can take several minutes)....". PHP_EOL;
// As we can't get all products at one time (out of memory problem
// we use offset and limit.
$limit = 1000;
$offset = 0;
do {
    // Get the products from data base needed to be updated
    $products = get_products_update($db, $limit, $offset, $collection, $verify);
    if ($products !== false) {
        // We update the products on the array
        $updated_products = array_update($products, $verify);
        update_db($db, $updated_products, $collection);
    }
    $offset = $offset + $limit;
} while ($offset < $total_products);

echo '------- Update finsihed -------' . PHP_EOL;

/*
 * we update the elements in the database
 */
function update_db($db, $updated_products, $collection){
    foreach ($updated_products as $product) {
        $keywords = object_to_array(json_decode($product["keywords"]));
        $hashes = getHashes($keywords);
        $result_update = pg_query($db, "UPDATE " . $collection . ".features SET keywords='" . pg_escape_string($product["keywords"]) . "', hashes='" . pg_escape_string($hashes) . "' WHERE identifier='" . $product["identifier"] . "'");    
    }
}

/*
 * We update the entering array with the correct keywords
 */
function array_update($products, $columns){
    // We iterate over each product
    foreach ($products as $product_key=>$product) {        
        // We get the keywords of one product        
        $keywords = json_decode($product["keywords"]);      
        // Iterate over the array on the begining of the script ($verify)
        foreach ($columns as $key => $column) {
            // We validate if the column in databaase has a value
            if (validateExistingParameter($product[strtolower($key)])) {
                // 1. We see if th keyword we want to update has a father
                if ($columns[$key]['parent'] !== false) {
                    // Has a parent
                    $parentHash = get_hash_keyword_element($keywords, $columns[$key]['parent']);
                }else{
                    //Has no a parent
                    $parentHash = false;
                }
                // 2. Now we delete it from the keywords, so we can add it later with the new value
                $keywordHash = get_hash_keyword_element($keywords, $key);
                // We delete it from keywords if it was already set
                if ($keywordHash !== false) {
                    unset($keywords->$keywordHash);;
                }    
                // 3. We create the keyword once again with correct values
                $new_keyword = create_keyword($product[strtolower($key)], $key, $parentHash);
                // We create the correct hashes
                $hash = createHash($key . ':' . $product[strtolower($key)], $parentHash);
                $keywords->$hash = $new_keyword;
            }else{
                // If the value is not set, we delete it on the keywords.
                // We verify if the element is in the keywords. 
                $keyword_hash = get_hash_keyword_element($keywords, $key);
                // If it's in the keywords we delete it 
                //If it's not in the keywords we do nothing
                if ($keyword_hash !== false) {
                    unset($keywords->$keyword_hash);
                }
            }
        }                
    //We insert the keywords in the product    
    $products[$product_key]["keywords"] = json_encode($keywords);
    }
  return $products;
}
    
/*
 * Get products which need to update their keywords and hashes
 */
function get_products_update($db, $limit, $offset, $collection, $verify) {
    // We get the only the elements which have one of the two elements filled
    $columns = get_columns($verify);
    $result = pg_query($db, "SELECT identifier, keywords, " . join(", ", $columns) . " FROM " . $collection . ".features ORDER BY identifier LIMIT " . $limit . " OFFSET " . $offset);
    if ($result !== false) {       
        $products = pg_fetch_all($result);
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
function createHash($input, $parent) {
    return substr(sha1($input . ($parent !== false ? ',' . $parent : '')), 0, 15);
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
 * We get the lower case of the columns
 */
function get_columns($columns){
    return array_keys(array_change_key_case($columns, CASE_LOWER));
}

/*
 * Validate if an argument is set
 */
function validateExistingParameter($parameter){
    if ($parameter === false || is_null($parameter) || empty($parameter)) {
        return false;
    }else{
        return true;
    }
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

