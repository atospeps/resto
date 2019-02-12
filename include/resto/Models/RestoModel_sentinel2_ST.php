<?php

/**
 * RESTo Sentinel-2 single tile model .
 * 
 * Input metadata is an XML file 
 * 
 */
class RestoModel_sentinel2_ST extends RestoModel {

    public $extendedProperties = array(
        's2TakeId' => array(
            'name' => 's2takeid',
            'type' => 'TEXT'
        ),
        'mgrs' => array(
            'name' => 'mgrs',
            'type' => 'TEXT'
        ),
        'bareSoil' => array(
            'name' => 'baresoil',
            'type' => 'NUMERIC',
            'required' => false
        ),
        'highProbaClouds' => array(
            'name' => 'highprobaclouds',
            'type' => 'NUMERIC',
            'required' => false
        ),
        'mediumProbaClouds' => array(
            'name' => 'mediumprobaclouds',
            'type' => 'NUMERIC',
            'required' => false
        ),
        'lowProbaClouds' => array(
            'name' => 'lowprobaclouds',
            'type' => 'NUMERIC',
            'required' => false
        ),
        'snowIce' => array(
            'name' => 'snowice',
            'type' => 'NUMERIC',
            'required' => false
        ),
        'vegetation' => array(
            'name' => 'vegetation',
            'type' => 'NUMERIC',
            'required' => false
        ),
        'water' => array(
            'name' => 'water',
            'type' => 'NUMERIC',
            'required' => false
        )
    );

    /**
     * Constructor
     * 
     * @param RestoContext $context : Resto context
     * @param RestoContext $user : Resto user
     */
    public function __construct() {
        parent::__construct();
        
        $this->searchFilters['resto:tileid'] = array (
                'key' => 'mgrs',
                'osKey' => 'tileid',
                'operation' => '=',
                'options' => 'auto',
                'title' => 'MGRS tile identifier',
                'pattern' => '^[0-6][0-9][A-Za-z]([A-Za-z]){0,2}%?$',
                'keyword' => array (
                        'value' => '{:mgrs:}',
                        'type' => 'mgrs'
                )
        );
        
        $this->searchFilters['resto:s2TakeId'] = array (
                'key' => 's2TakeId',
                'osKey' => 's2TakeId',
                'operation' => '=',
                'title' => 'mission data take identifier',
                'pattern' => '^G(?:S2A|S2B)_\d{8}T\d{6}_\d{6}_N\d\d\.\d\d$'
        );
        
    }

    /**
     * Add feature to the {collection}.features table following the class model
     * 
     * @param array $data : array (MUST BE GeoJSON in abstract Model)
     * @param RestoCollection $collection : collection
     */
    public function storeFeature($data, $collection) {
        return parent::storeFeature($this->parse(join('',$data), $collection), $collection);
    }

    /**
     * Update feature within {collection}.features table following the class model
     *
     * @param array $data : array (MUST BE GeoJSON in abstract Model)
     * @param string $featureIdentifier : the id of the feature (not obligatory)
     * @param string $featureTitle : the title of the feature (not obligatory)
     * @param RestoCollection $collection
     *
     */
    public function updateFeature($feature, $data) {
        return parent::updateFeature($feature, $this->parse(join('',$data), $feature->collection, true));
    }
    
    /**
     * Create JSON feature from new resource xml string
     *
    <product>
        <title>S1A_IW_OCN__2SDV_20150727T044706_20150727T044731_006992_0097D1_F6DA</title>
        <resourceSize>6317404</resourceSize>
        <startTime>2015-07-27T04:47:06.611</startTime>
        <stopTime>2015-07-27T04:47:31.061</stopTime>
        <productType>OCN</productType>
        <missionId>S1A</missionId>
        <processingLevel>1</processingLevel>
        <mode>IW</mode>
        <absoluteOrbitNumber>6992</absoluteOrbitNumber>
        <orbitDirection>ASCENDING</orbitDirection>
        <s2takeid>38865</s2takeid>
        <cloudcover>0.0</cloudcover>
        <instrument>Multi-Spectral Instrument</instrument>
        <footprint>POLYGON ((-161.306549 21.163258,-158.915909 21.585093,-158.623169 20.077986,-160.989746 19.652864,-161.306549 21.163258))</footprint>
    </product>
     *
     * @param string $xml
     * @param RestoCollection $collection
     * @return array GeoJson feature
     * 
     */
    private function parse($xml, $collection, $partiel = false) {
    	
        $dom = new DOMDocument();
        if (!@$dom->loadXML(rawurldecode($xml))) {
            RestoLogUtil::httpError(500, 'Invalid feature description - Resource file');
        }
        
    	$props = array(
    	    // Common properties
    	    'productIdentifier' => $this->Filter('title'),
    	    'title' => $this->Filter('title'),
    	    'resourceSize' => $this->Filter('resourceSize'),
    	    'resourceChecksum' => $this->Filter('checksum'),
    	    'startDate' => $this->Filter('startTime'),
    	    'completionDate' => $this->Filter('stopTime'),
    	    'productType' => $this->Filter('productType'),
    	    'processingLevel' => $this->Filter('processingLevel'),
    	    'platform' =>  $this->Filter('missionId'),
    	    'sensorMode' => $this->Filter('mode'),
    	    'orbitNumber' => $this->Filter('absoluteOrbitNumber'),
    	    'relativeOrbitNumber' => $this->Filter('relativeOrbitNumber'),
    	    'orbitDirection' => $this->Filter('orbitDirection', 'strtolower'),
    	    'instrument'=> $this->Filter('instrument'),
    	    'cloudCover' => $this->Filter('cloudCover'),
    	    'isNrt' => $this->Filter('isNrt'),
    	    'realtime' => $this->Filter('realtime'),
    	    'dhusIngestDate' => $this->Filter('dhusIngestDate'),
    	    'quicklook' => $this->Filter(null, $this->getLocation, array($dom)),
    	    'authority' => $this->Filter(null, function (){ return 'ESA'; }),
    	    // Sentinel-2 specifities
    	    'mgrs' => $this->Filter('title', $this->getMGRSLocation),
    	    's2TakeId' => $this->Filter('s2takeid'),
    	    'bareSoil' => $this->Filter('bareSoilPercentage'),
    	    'highProbaClouds' => $this->Filter('highProbaCloudsPercentage'),
    	    'mediumProbaClouds' => $this->Filter('mediumProbaCloudsPercentage'),
    	    'lowProbaClouds' => $this->Filter('lowProbaCloudsPercentage'),
    	    'snowIce' => $this->Filter('snowIcePercentage'),
    	    'vegetation' => $this->Filter('vegetationPercentage'),
    	    'water' => $this->Filter('waterPercentage')
    	);

    	/*
         * Parses DOM Document.
         */
        foreach($props as $modelKey => $filter) {
            list($tagName, $callback, $params) = $filter;
            
            if ($dom->getElementsByTagName($tagName)->length || $partiel === false) {
                $type = $this->getDbType($modelKey);                
                $required = $this->getDbValueRequired($modelKey);
                if (isset($tagName)) {
                    $params = array($this->getElementByName($dom, $tagName, $type, $required));
                }                
                $props[$modelKey] = call_user_func_array($callback, $params);
            }
            else {
                unset($props[$modelKey]);
            }
        }
    	
    	/*
    	 * Footprint
    	 */
    	$geometry = null;
    	if ($dom->getElementsByTagName('footprint')->length || $partiel === false) {
    	    $footprint = $this->getElementByName($dom, 'footprint', null, true);
    	    
        	// Simplify polygon
    	    $polygon = $collection->context->dbDriver->execute(RestoDatabaseDriver::SIMPLIFY_GEOMETRY, array('wkt' => $footprint));
        	$polygon = RestoGeometryUtil::wktPolygonToArray($polygon);
        	$geometry = array( 'type' => 'Polygon', 'coordinates' => array($polygon) );
    	}
    	
        /*
         * Initialize feature
         */
        return array(
            'type' => 'Feature',
            'geometry' => $geometry,
            'properties' => $props
      );
    }

    /**
     *
     * @param string $tagName
     * @param mixed $callback
     * @param string|null $params
     * @return array[]|string[]
     */
    function Filter($tagName, $callback = null, $params = array()) {
        if (!function_exists('$callback')){
            $params = array($tagName);
            $callback = function($value){ return $value; };
        }
        return array($tagName, $callback, $params ? $params : array());
    }

    /**
     * Returns MGRS location from single tile product name (Single Tile Naming Convention)
     * @param string $title single tile name
     * @return (string|null) MGRS location
     */
    function getMGRSLocation($title){
        $mgrs = null;
        if (!empty($title)){
            $mgrs = substr($title, 39, 5);
        }
        return $mgrs;
    }

    /**
     * 
     * @param unknown $dom
     * @return string
     */
    function getLocation($dom) {
        $startTime = $this->getElementByName($dom, 'startTime', null, true);
        $startTime = explode("T", $startTime);
        $result = str_replace("-","/", $startTime[0]);
        $missionId = $this->getElementByName($dom, 'missionId', null, true);
        $title= $this->getElementByName($dom, 'title', null, true);
        return $result. "/" . $missionId . "/".$title;
    }
}
