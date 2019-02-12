<?php

/*
 * RESTo
 * 
 * RESTo - REstful Semantic search Tool for geOspatial 
 * 
 * Copyright 2013 Jérôme Gasperi <https://github.com/jjrom>
 * 
 * jerome[dot]gasperi[at]gmail[dot]com
 * 
 * 
 * This software is governed by the CeCILL-B license under French law and
 * abiding by the rules of distribution of free software.  You can  use,
 * modify and/ or redistribute the software under the terms of the CeCILL-B
 * license as circulated by CEA, CNRS and INRIA at the following URL
 * "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and  rights to copy,
 * modify and redistribute granted by the license, users are provided only
 * with a limited warranty  and the software's author,  the holder of the
 * economic rights,  and the successive licensors  have only  limited
 * liability.
 *
 * In this respect, the user's attention is drawn to the risks associated
 * with loading,  using,  modifying and/or developing or reproducing the
 * software by the user in light of its specific status of free software,
 * that may mean  that it is complicated to manipulate,  and  that  also
 * therefore means  that it is reserved for developers  and  experienced
 * professionals having in-depth computer knowledge. Users are therefore
 * encouraged to load and test the software's suitability as regards their
 * requirements in conditions enabling the security of their systems and/or
 * data to be ensured and,  more generally, to use and operate it in the
 * same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL-B license and that you accept its terms.
 * 
 */

/**
 * RESTo Sentinel-3 model 
 * 
 * Input metadata is an XML file 
 * 
 */
class RestoModel_sentinel3 extends RestoModel {

    public $extendedProperties = array(
            'cycleNumber' => array(
                'name' => 'cyclenumber',
                'type' => 'INTEGER'
            ),
            'approxSize' => array(
                'name' => 'approxSize',
                'type' => 'TEXT',
                'required' => false
            ),
            'ecmwfType' => array(
                'name' => 'ecmwfType',
                'type' => 'TEXT',
                'required' => false
            ),
            'processingName' => array(
                'name' => 'processingName',
                'type' => 'TEXT',
                'required' => false
            ),
            'onlineQualityCheck' => array(
                'name' => 'onlineQualityCheck',
                'type' => 'TEXT',
                'required' => false
            ),
    );

    /**
     * Constructor
     * 
     * @param RestoContext $context : Resto context
     * @param RestoContext $user : Resto user
     */
    public function __construct() {
        parent::__construct();
        
        $this->searchFilters['resto:cycleNumber'] = array (
                'key' => 'cycleNumber',
                'osKey' => 'cycleNumber',
                'operation' => 'interval',
                'title' => 'Cycle number',
                'minInclusive' => 1,
                'quantity' => array (
                        'value' => 'cyclenumber'
                )
        );

        $this->searchFilters['resto:ecmwfType'] = array (
                'key' => 'ecmwfType',
                'osKey' => 'ecmwfType',
                'title' => 'ECMWF Type',
                'operation' => '='
        );

        $this->searchFilters['resto:onlineQualityCheck'] = array (
                'key' => 'onlineQualityCheck',
                'osKey' => 'onlineQualityCheck',
                'title' => 'Online Quality Check',
                'operation' => '='
        );

        $this->searchFilters['resto:processingName'] = array (
                'key' => 'processingName',
                'osKey' => 'processingName',
                'title' => 'Processing name',
                'operation' => '='
        );
    }

    /**
     * Add feature to the {collection}.features table following the class model
     * 
     * @param array $data : array (MUST BE GeoJSON in abstract Model)
     * @param RestoCollecction $collectionName : collection
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
     * Create JSON feature from xml string
     * @param string $xml
     * @param RestoCollection $collection
     * @return array GeoJson feature
     */
    private function parse($xml, $collection, $partiel = false){
        
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
            'cloudCover' => $this->Filter(null, function(){ return 0; }),
            'isNrt' => $this->Filter('isNrt'),
            'realtime' => $this->Filter('realtime'),
            'dhusIngestDate' => $this->Filter('dhusIngestDate'),
            'quicklook' => $this->Filter(null, '$this->getLocation', array($dom)),
            'authority' => $this->Filter(null, function (){ return 'ESA'; }),
            // Sentinel-3 specifities
            'cycleNumber' => $this->Filter('cycle'),
            'approxSize' => $this->Filter('approxSize'),
            'ecmwfType' => $this->Filter('ecmwfType'),
            'processingName' => $this->Filter('processingName'),
            'onlineQualityCheck' => $this->Filter('onlineQualityCheck')
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
