<?php

/**
 * @author Atos
 * RESTo Upload module.
 *
 *    | 
 *    | Resource                                                        | Description
 *    |_________________________________________________________________|______________________________________
 *    | HTTP/GET        wps/users/{userid}/jobs                         | List of all user's jobs
 *    | HTTP/GET        wps/status                                       | Check VIZO status
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
    }

    /**
     * 
     * {@inheritDoc}
     * @see RestoModule::run()
     */
    public function run($elements, $data = []) {
        
        // Allowed HTTP method
        if ($this->context->method !== 'POST')
        {
            RestoLogUtil::httpError(404);
        }        
        return $this->route($segments, $data);
    }

    /**
     * 
     * @param unknown $segments
     * @param unknown $data
     * @return unknown|unknown|string[]|StdClass[][]
     */
    private function route($segments, $data){
        
        if (isset($segments[0]) && $segments['0'] === 'area' && !isset($segments[1])) {
            return RestoLogUtil::httpError(404);
        }
        return $this->upload($segments, $data);
    }
    
    /**
     * 
     * @param unknown $segments
     * @param unknown $data
     * @return unknown|string[]|StdClass[][]
     */
    private function upload($segments, $data){
        
        if ($segments[0] === 'upload') {
            
            if (!isset($segments[1]) || isset($segments[2])) {
                RestoLogUtil::httpError(404);
            }
            
            // upload file
            $fileName = RestoUtil::uploadFile($this->context->uploadDirectory);
            
            /*
             * api/upload/area
             */
            if ($segments[1] === 'area' && !isset($segments[2])) {
                
                // get content
                try {
                    $content = file_get_contents($fileName);
                    $json = json_decode($content, true);
                } catch (Exception $e) {
                    RestoLogUtil::httpError(415);
                }
                
                // GeoJSON
                if ($json && (
                        RestoGeometryUtil::isValidGeoJSONFeatureCollection($json) ||
                        RestoGeometryUtil::isValidGeoJSONFeature($json)
                        )) {
                            unlink($fileName);
                            return $json;
                        }
                        
                        /*
                         * KML / SHP
                         */
                        try {
                            // detect content format (here KML only)
                            $format = geoPHP::detectFormat($content);
                            
                            // extract file infos
                            $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
                            $basename = pathinfo($_FILES['file']['name'], PATHINFO_FILENAME);
                            
                            /*
                             * ----- KML
                             */
                            if ($format === 'kml') {
                                $geometry = geoPHP::load($content, 'kml');
                                $geometry = geoPHP::geometryReduce($geometry);
                                if ($geometry) {
                                    $feature = new StdClass();
                                    $feature->type = "Feature";
                                    $feature->geometry = json_decode($geometry->out('json'));
                                    $feature->properties = new StdClass();
                                    unlink($fileName);
                                    return array(
                                            "type" => "FeatureCollection",
                                            "features" => array($feature)
                                    );
                                }
                            }
                            
                            /*
                             * ----- SHP
                             */
                            elseif ($ext === 'zip') {
                                
                                $extractDir = $this->context->workingDirectory . DIRECTORY_SEPARATOR . $basename;
                                
                                $polygons = array();
                                if (RestoUtil::extractZip($fileName, $extractDir) === true) {
                                    $ShapeFile = new ShapeFile($extractDir . DIRECTORY_SEPARATOR . $basename . '.shp');
                                    while ($record = $ShapeFile->getRecord(SHAPEFILE::GEOMETRY_WKT)) {
                                        if (isset($record['dbf']['deleted'])) continue;
                                        $polygons[] = $record['shp'];
                                    }
                                }
                                
                                unlink($fileName);
                                if (is_dir($extractDir)) {
                                    RestoUtil::rrmdir($extractDir);
                                }
                                
                                if (count($polygons) > 0) {
                                    $geometry = geoPHP::load('GEOMETRYCOLLECTION(' . implode(',', $polygons) . ')', 'wkt');
                                    $geometry = geoPHP::geometryReduce($geometry);
                                    if ($geometry) {
                                        $feature = new StdClass();
                                        $feature->type = "Feature";
                                        $feature->geometry = json_decode($geometry->out('json'));
                                        $feature->properties = new StdClass();
                                        return array(
                                                "type" => "FeatureCollection",
                                                "features" => array($feature)
                                        );
                                    }
                                }
                            }
                            
                            RestoLogUtil::httpError(415);
                            
                        } catch (Exception $ex) {
                            RestoLogUtil::httpError(415);
                        }
            }
        }
        
    }
    
    private function uploadShapefile(){
        
    }
    
    private function uploadGeoJson(){
        
    }
    
    private function uploadKML(){
        
    }

    /**
     * 
     */
    private function checkFile(){
        
    }
    
}


