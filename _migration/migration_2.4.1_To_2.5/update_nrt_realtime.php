<?php

/*
 * update_nrt_realtime.php -d <database> -u <user> - p <password>
 */

$options = getopt('d:u:p:');

/*************************************************/
/********* VARIABLES *****************************/

// Data base Parameters
$resto_db = array(
  'db'       => 'resto',
  'host'     => isset($options['d']) ? $options['d'] : 'localhost',
  'user'     => isset($options['u']) ? $options['u'] : 'resto',
  'password' => isset($options['p']) ? $options['p'] : 'resto',
);

// contants

// Get the database connection
$db = verify_db($resto_db);

echo "Updating data base..." . PHP_EOL;

/*
 * CREATION INDEX TEMPORAIRE
 */

/*
 * MàJ CHAMPS ISNRT
 */

/*
 * MàJ CHAMPS REALTIME
 */

/*
 * MàJ CHAMPS VISIBLE + NEW_VERSION
 */
    
// on recupère les alertes avec, au moins, un des 3 champs MGRS
$alerts = getMgrsAlerts();
if (!$alerts) {
    echo 'WARNING: no alerts with MGRS to update. Aborting.' . PHP_EOL;
    exit();
}

while ($row = pg_fetch_assoc($alerts)) {
    
    $criterias = json_decode($row["criterias"]);
    
    // on crée le champs tileid avec les 3 champs MGRS
    // (uniquement si ils sont tous présent, renseignés et ne comporte pas le joker %)
    $tileId = "";
    if ((isset($criterias->mgrsGSquare) && !empty($criterias->mgrsGSquare) && $criterias->mgrsGSquare !== '%')
     && (isset($criterias->latitudeBand) && !empty($criterias->latitudeBand) && $criterias->latitudeBand !== '%')
     && (isset($criterias->mgrsGSquare) && !empty($criterias->mgrsGSquare) && $criterias->mgrsGSquare !== '%')
    ) {
        $tileId = $criterias->utmZone . strtoupper($criterias->latitudeBand) . strtoupper($criterias->mgrsGSquare);
        $criterias->tileId = $tileId;
    }
    
    unset($criterias->utmZone);
    unset($criterias->latitudeBand);
    unset($criterias->mgrsGSquare);
    
    if (empty($tileId)) {
        // si le tileid n'a pu être créé, on supprime l'alerte
        deleteAlert($row["aid"]);
    } else {
        // on a le tileid, on mets à jour l'alerte
        updateAlertCriterias($row["aid"], $criterias);
    }
}

echo '------- Update finsihed -------' . PHP_EOL;

/*************************************************/
/*************************************************/

/*
 * Retourne la liste des abonnements avec, comme critères, un champs de type MGRS
 */
function getMgrsAlerts()
{
    global $db;
    
    $query = "SELECT aid, criterias"
           . " FROM usermanagement.alerts"
           . " WHERE criterias LIKE '%latitudeBand%' OR criterias LIKE '%utmZone%' OR criterias LIKE '%mgrsGSquare%'";
    
    return pg_query($db, $query);
}

/*
 * Mets à jour les critères d'un abonnement d'un utilisateur
 */
function updateAlertCriterias($aid, $criterias)
{
    global $db;
    
    $query = "UPDATE usermanagement.alerts"
           . " SET criterias = '" . json_encode($criterias) . "'"
           . " WHERE aid = " . $aid;
    //echo $query . PHP_EOL;
    pg_query($db, $query);
}

/*
 * Supprime un abonnement
 */
function deleteAlert($aid)
{
    global $db;
    
    $query = "DELETE FROM usermanagement.alerts"
           . " WHERE aid = " . $aid;
    //echo $query . PHP_EOL;
    pg_query($db, $query);
}

/*
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
