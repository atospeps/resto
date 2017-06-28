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
                    'type' => 'TEXT'
            ),
            'ecmwfType' => array(
                    'name' => 'ecmwfType',
                    'type' => 'TEXT'
            ),
            'processingName' => array(
                    'name' => 'processingName',
                    'type' => 'TEXT'
            ),
            'onlineQualityCheck' => array(
                    'name' => 'onlineQualityCheck',
                    'type' => 'TEXT'
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
        return parent::updateFeature($feature, $this->parse(join('',$data), $feature->collection));
    }
    
    /**
     * Create JSON feature from xml string
     * @param string $xml
     * @param RestoCollection $collection
     * @return array GeoJson feature
     */
    private function parse($xml, $collection){

        $dom = new DOMDocument();
        if (!@$dom->loadXML(rawurldecode($xml))) {
            RestoLogUtil::httpError(500, 'Invalid feature description - Resource file');
        }
        
        /*
         * Retreives orbit direction
         */
        $orbitDirection = strtolower($this->getElementByName($dom, 'orbitDirection'));

        // Simplify polygon
        $polygon = $collection->context->dbDriver->execute(RestoDatabaseDriver::SIMPLIFY_GEOMETRY, array('wkt' => $this->getElementByName($dom, 'footprint')));
        $polygon = RestoGeometryUtil::wktPolygonToArray($polygon);

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
                    'resourceChecksum' => $this->getElementByName($dom, 'checksum'),
                    'authority' => 'ESA',
                    'startDate' => $this->getElementByName($dom, 'startTime'),
                    'completionDate' => $this->getElementByName($dom, 'stopTime'),
                    'productType' => $this->getElementByName($dom, 'productType'),
                    'processingLevel' => $this->getElementByName($dom, 'processingLevel'),
                    'platform' =>  $this->getElementByName($dom, 'missionId'),
                    'sensorMode' => $this->getElementByName($dom, 'mode'),
                    'orbitNumber' => $this->getElementByName($dom, 'absoluteOrbitNumber'),
                    'relativeOrbitNumber' => $this->getElementByName($dom, 'relativeOrbitNumber'),
                    'cycleNumber' => $this->getElementByName($dom, 'cycle'),
                    'orbitDirection' => $orbitDirection,
                    'instrument'=> $this->getElementByName($dom, 'instrument'),
                    'quicklook'=> $this->getLocation($dom),
                    'cloudCover' => 0,
                    'isNrt' => $this->getElementByName($dom, 'isNrt'),
                    'realtime' => $this->getElementByName($dom, 'realtime'),
                    'dhusIngestDate' => $this->getElementByName($dom, 'dhusIngestDate'),
                    'approxSize' => $this->getElementByName($dom, 'approxSize'),
                    'ecmwfType' => $this->getElementByName($dom, 'ecmwfType'),
                    'processingName' => $this->getElementByName($dom, 'processingName'),
                    'onlineQualityCheck' => $this->getElementByName($dom, 'onlineQualityCheck')
                )
      );

      return $feature;
    }

    function getLocation($dom) {
        $startTime = $dom->getElementsByTagName('startTime')->item(0)->nodeValue;
        $startTime = explode("T", $startTime);
        $result = str_replace("-","/",$startTime[0]);
        $missionId = $dom->getElementsByTagName('missionId')->item(0)->nodeValue;
        $title= $dom->getElementsByTagName('title')->item(0)->nodeValue;
        return $result."/".$missionId."/".$title;
    }
}
