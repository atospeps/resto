<?php

/**
 * @author Atos
 * RESTo Upload module.
 *    upload/area                                                       |  Upload a SHP, KML or GeoJSON file
 *
 *    | 
 *    | Resource                                                        | Description
 *    |_________________________________________________________________|______________________________________
 *    | HTTP/GET        wps/users/{userid}/jobs                         | List of all user's jobs
 *    | HTTP/GET        wps/status                                      | Check VIZO status
 *    
 *    
 */
class Upload extends RestoModule {
    /*
     * Resto context
     */
    public $context;
    
    /*
     * Current user (only set for administration on a single user)
     */
    public $user = null;
    
    /*
     * File extensions allowed
     */
    private $extensions = array('geojson', 'json', 'zip', 'kml');
    
    /*
     * 
     */
    private $fileSizeLimit = 1024;

    /*
     * 
     */
    private $areaLimit = 1000;
    
    /*
     * 
     */
    private $scanFile = false;
    
    

    
    /*
     * Antivirus
     * geometry empty
     * geometry complexe
     * geometry trop de points
     * geometry trop grande surface
     * erreur de lecture des fichiers geo
     * fichier geo invalides
     * 
     * 
     */
    
    /**
     * Constructor
     *
     * @param RestoContext $context
     * @param RestoUser $user
     */
    public function __construct($context, $user) {
        parent::__construct($context, $user);
        
        // Set user
        $this->user = $user;
        
        // Set context
        $this->context = $context;
        if (isset($this->context->dbDriver)) {
            $this->context->dbDriver->closeDbh();
        }
        
        if (function_exists('mb_detect_encoding') === false) {
            function mb_detect_encoding($str = 'UTF-8')
            {
                return $str;
            }
        }
        if (function_exists('mb_strtolower') === false) {
            function mb_strtolower($str, $encoding = 'UTF-8')
            {
                return strtolower($str);     
            }
        }
    }

    /**
     * 
     * {@inheritDoc}
     * @see RestoModule::run()
     */
    public function run($segments, $data = []) {    
        error_log("Upload Area from file", 0);
        // Allowed HTTP method
        if ($this->context->method !== 'POST' || isset($segments[0]))
        {
            RestoLogUtil::httpError(404);
        }

        if (isset($_FILES)) {
            return $this->process();
        }
        // Bad request - empty data
        RestoLogUtil::httpError(400);
    }

    /**
     * 
     * @param unknown $segments
     * @param unknown $data
     * @return unknown|string[]|StdClass[][]
     */
    private function process() {
        
        // upload file
        $options = array('extensions' => $this->extensions);
        $file = RestoUtil::uploadFile($this->context->uploadDirectory, $options);

        // TODO : *** test antivirus ***
        
        // TODO *** check size, complexity issimple, .... ***

        $geometry = null;
        
        $file_ext = $file['extension'];
        switch ($file_ext) {
            case 'json':
            case 'geojson':
                $geometry = $this->readGeoJson($file);
                break;
            case 'kml':
                $geometry = $this->readKML($file);
                break;
            case 'zip':
                $geometry = $this->readShapefile($file);
                break;
            default:
                return RestoLogUtil::httpError(400, 'Cannot upload file(s) - Extension \'' . $file_ext . '\'not allowed, please choose a valid file.');
        }
        return $this->answer($geometry);
    }
    
    /**
     * 
     */
    private function readShapefile($file){
        
        $path = $file['path'];
        $geometry = null;
        $extractDir = $this->context->workingDirectory . DIRECTORY_SEPARATOR . basename($path);

        $polygons = array();
        if (RestoUtil::extractZip($path, $extractDir) === true) {
            $shp = glob($extractDir . '/*.[sS][hH][pP]');
            if ($shp)
            {
                try {
                    $ShapeFile = new ShapeFile($shp[0]);
                    while ($record = $ShapeFile->getRecord(SHAPEFILE::GEOMETRY_WKT)) {
                        if (isset($record['dbf']['deleted'])) {
                            continue;
                        }
                        $polygons[] = $record['shp'];
                    }
                } catch (Exception $e) {
                    error_log($e->getMessage(), 0);
                }
                
            }
        }

        unlink($path);
        if (is_dir($extractDir)) {
            RestoUtil::rrmdir($extractDir);
        }

        if (count($polygons) > 0) 
        {
            $geometry = geoPHP::load('GEOMETRYCOLLECTION(' . implode(',', $polygons) . ')', 'wkt');
        }
        return $geometry;

    }
    
    /**
     * 
     * @param unknown $file
     * @return unknown|NULL
     */
    private function readGeoJson($file){
        $path = $file['path'];
        $geometry = null;
        try {
            $content = file_get_contents($path);
            unlink($path);
            $geometry = geoPHP::load($content, 'json');
        } catch (Exception $e) {
            unlink($path);
            return RestoLogUtil::httpError(400);
        }
        return $geometry;
    }
    
    /**
     * 
     * @param unknown $file
     * @return Geometry|NULL
     */
    private function readKML($file){
        $path = $file['path'];
        $geometry = null;
        try {
            $content = file_get_contents($path);
            unlink($path);
            $geometry = geoPHP::load($content, 'kml');
        } catch (Exception $e) {
            unlink($path);
            return RestoLogUtil::httpError(400);
        }
        return $geometry;
    }

    /**
     * 
     * @param unknown $geometry
     */
    private function answer($geometry) {
        if (empty($geometry) || $geometry->isEmpty())
        {
            return RestoLogUtil::httpError(400, "POLYGON_EMPTY");
        }
        $geometry = geoPHP::geometryReduce($geometry);
        if ($geometry->numGeometries() > 1) {
            return RestoLogUtil::httpError(400, "POLYGON_COUNT");
        }

        header('Content-Type: application/json');
        print preg_replace('/\s+/', '', '{
            "type": "FeatureCollection",
            "features": [
                                  { "type": "Feature",
                                    "geometry": ' . $geometry->out('json') . ',
                                    "properties": {}
                                  }
                                ]
        }');
    }

    /**
     * 
     */
    private function scanFile(){
        
    }
    
}


