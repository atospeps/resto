<?php
ignore_user_abort(false);


function shutdown () {
    // if the script fails some logic goes here
}

// registers the function to run on shutdown
register_shutdown_function('shutdown');
error_log("begin of sleeping...", 0);
sleep(15);
error_log("#end...", 0);
exit();
    
    $polygon = 'POLYGON((-118.30061 46.3612010547259,-118.26918 46.4498190577502,-118.22449 46.579128737858,-118.218414 46.5966008059577,-118.16582 46.7428502249873,-118.11351 46.8892315092104,-118.09277 46.9473112932713,-116.871735 46.9536373342462,-116.87404 45.9654817266249,-118.291 45.9582586888966,-118.30061 46.3612010547259))';
    $dir = '/usr/local/apache2/htdocs/resto/';

    //////////////////////////////////////////////////////////////////////////////////
    function autoload($className) {
        global $dir;
        foreach (array(
                'include/resto/',
                'include/resto/Drivers/',
                'include/resto/Collections/',
                'include/resto/Models/',
                'include/resto/Dictionaries/',
                'include/resto/Modules/',
                'include/resto/Routes/',
                'include/resto/Utils/',
                'include/resto/XML/',
                'lib/iTag/',
                'lib/WPS/',
                'lib/WPS/Utils/',
                'lib/JWT/') as $current_dir) {
                $path = $dir . $current_dir . sprintf('%s.php', $className);
                if (file_exists($path)) {
                    include $path;
                    return;
                }
        }
    }
    spl_autoload_register('autoload');
    ////////////////////////////////////////////////////////////////////////////////////
    
    
    $keywords = array();
    $module = array(
            'database' => array(
                    'dbname' => 'itag',
                    /*
                     * Database host - if not specified connect through socket instead of TCP/IP
                     */
                    'host' => 'localhost',
                    'user' => 'itag',
                    'password' => 'itag'
            ),
            'taggers' => array(
                    'Political' => array(),
                    'LandCover' => array()
            ),
            /*
             * iTag doesn't compute land cover keywords if footprint area is greater than "areaLimit" (square kilometers)
             */
            'areaLimit' => 3000000
    );
    /*
     * Compute keywords from iTag
     */
        
    $options = array('areaLimit' => $module['areaLimit']);
    $iTag = new iTag($module['database'], $options);
    $metadata = array(
            'footprint' => $polygon,
            'timestamp' => null
    );
    
    $util = new RestoKeywordsUtil();
    $keywords = $iTag->tag($metadata, $module['taggers']);
    
    header('Content-Type: application/json');
    print json_encode($util->keywordsFromITag($keywords));//json_encode($keywords);


