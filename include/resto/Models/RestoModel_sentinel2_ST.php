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
        'relativeOrbitNumber' => array(
                'name' => 'relativeorbitnumber',
                'type' => 'TEXT'
        ),
        'mgrs' => array(
                'name' => 'mgrs',
                'type' => 'TEXT'
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
                'options' => 'auto'
        );
    }

    /**
     * Add feature to the {collection}.features table following the class model
     * 
     * @param array $data : array (MUST BE GeoJSON in abstract Model)
     * @param string $collectionName : collection name
     */
    public function storeFeature($data, $collectionName) {
        return parent::storeFeature($this->parse(join('',$data)), $collectionName);
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
        return parent::updateFeature($feature, $this->parse(join('',$data)));
    }
    
    /**
     * We verify if the feature has an (or multiple) old feature/s. We update them in order to have
     * a reerence to the new feature, and make them invisible
     *
     * @param string $product_indetifier
     * @param string collectionName
     */
    public function hasOldFeature($product_indetifier, $collection){
        $partial_indetifier = substr($product_indetifier, 0, -53) . '%' . substr($product_indetifier, 40);
        return parent::hasOldFeature($partial_indetifier, $collection);
    }
    
    /**
     * Create JSON feature from xml string
     * 
     * @param {String} $xml : $xml string
     */
    private function parse($xml) {
        
        $dom = new DOMDocument();
        $dom->loadXML(rawurldecode($xml));
        
        return $this->parseNew($dom);
    }

    /**
     * Create JSON feature from new resource xml string
     *
     * <product>
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
     * @param {DOMDocument} $dom : $dom DOMDocument
     */
    private function parseNew($dom){
    	
    	/*
    	 * Retreives orbit direction
    	 */
    	$orbitDirection = strtolower($this->getElementByName($dom, 'orbitDirection'));

    	$polygon = RestoGeometryUtil::wktPolygonToArray($this->getElementByName($dom, 'footprint'));
    	
        /*
         * Initialize feature
         */
        $feature = array(
                'type' => 'Feature',
                'geometry' => array(
                        'type' => 'Polygon',
                        'coordinates' => array($polygon),
                ),
                'properties' => array(
                    'productIdentifier' => $this->getElementByName($dom, 'title'),
                    'title' => $this->getElementByName($dom, 'title'),
                    'resourceSize' => $this->getElementByName($dom, 'resourceSize'),
                    'authority' => 'ESA',
                    'startDate' => $this->getElementByName($dom, 'startTime'),
                    'completionDate' => $this->getElementByName($dom, 'stopTime'),
                    'productType' => $this->getElementByName($dom, 'productType'),
                    'processingLevel' => $this->getElementByName($dom, 'processingLevel'),
                    'platform' =>  $this->getElementByName($dom, 'missionId'),
                    'sensorMode' => $this->getElementByName($dom, 'mode'),
                    'orbitNumber' => $this->getElementByName($dom, 'absoluteOrbitNumber'),
                    'orbitDirection' => $orbitDirection,
                    'instrument'=> $this->getElementByName($dom, 'instrument'),
                    'quicklook'=> $this->getLocation($dom),
                    's2TakeId' => $this->getElementByName($dom, 's2takeid'),
                    'cloudCover' => $this->getElementByName($dom, 'cloudCover'),
                    'isNrt' => $this->getElementByName($dom, 'isNrt'),
                    'realtime' => $this->getElementByName($dom, 'realtime'),
                    'dhusIngestDate' => $this->getElementByName($dom, 'dhusIngestDate'),
                    'relativeOrbitNumber' => $this->getElementByName($dom, 'relativeOrbitNumber'),
                    'mgrs' => $this->getMGRSLocation($this->getElementByName($dom, 'title'))
                )
      );

      return $feature;
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

    function getLocation($dom) {
        $startTime = $this->getElementByName($dom, 'startTime');
        $startTime = explode("T", $startTime);
        $result = str_replace("-","/", $startTime[0]);
        $missionId = $this->getElementByName($dom, 'missionId');
        $title= $this->getElementByName($dom, 'title');
        return $result. "/" . $missionId . "/".$title;
    }
}
