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
 * RESTo Sentinel-2 model 
 * 
 * Input metadata is an XML file 
 * 
 */
class RestoModel_sentinel2 extends RestoModel {
    
    public $extendedProperties = array(
        's2TakeId' => array(
            'name' => 's2takeid',
            'type' => 'TEXT'
        ),
        'orbitDirection' => array(
            'name' => 'orbitDirection',
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

        $this->searchFilters['eo:orbitDirection'] = array (
                'key' => 'orbitDirection',
                'osKey' => 'orbitDirection',
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
    public function updateFeature($data, $featureIdentifier=null, $featureTitle=null, $collectionName) {
        return parent::updateFeature($this->parse(join('',$data)), $featureIdentifier, $featureTitle, $collectionName);
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
    	$orbitDirection = strtolower($dom->getElementsByTagName('orbitDirection')->item(0)->nodeValue);

    	$polygon = RestoGeometryUtil::wktPolygonToArray($dom->getElementsByTagName('footprint')->item(0)->nodeValue);
    	
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
                    'productIdentifier' => isset($dom->getElementsByTagName('title')->item(0)->nodeValue) ? $dom->getElementsByTagName('title')->item(0)->nodeValue : NULL,
                    'title' => isset($dom->getElementsByTagName('title')->item(0)->nodeValue) ? $dom->getElementsByTagName('title')->item(0)->nodeValue : NULL,
                    'resourceSize' => isset($dom->getElementsByTagName('resourceSize')->item(0)->nodeValue) ? $dom->getElementsByTagName('resourceSize')->item(0)->nodeValue : NULL,
                    'authority' => 'ESA',
                    'startDate' => isset($dom->getElementsByTagName('startTime')->item(0)->nodeValue) ? $dom->getElementsByTagName('startTime')->item(0)->nodeValue : NULL,
                    'completionDate' => isset($dom->getElementsByTagName('stopTime')->item(0)->nodeValue) ? $dom->getElementsByTagName('stopTime')->item(0)->nodeValue : NULL,
                    'productType' => isset($dom->getElementsByTagName('productType')->item(0)->nodeValue) ? $dom->getElementsByTagName('productType')->item(0)->nodeValue : NULL,
                    'processingLevel' => isset($dom->getElementsByTagName('processingLevel')->item(0)->nodeValue) ? $dom->getElementsByTagName('processingLevel')->item(0)->nodeValue : NULL,
                    'platform' =>  isset($dom->getElementsByTagName('platform')->item(0)->nodeValue) ? $dom->getElementsByTagName('platform')->item(0)->nodeValue : NULL,
                    'sensorMode' => isset($dom->getElementsByTagName('mode')->item(0)->nodeValue) ? $dom->getElementsByTagName('mode')->item(0)->nodeValue : NULL,
                    'orbitNumber' => isset($dom->getElementsByTagName('absoluteOrbitNumber')->item(0)->nodeValue) ? $dom->getElementsByTagName('absoluteOrbitNumber')->item(0)->nodeValue : NULL,
                    'orbitDirection' => $orbitDirection,
                    'instrument'=> isset($dom->getElementsByTagName('instrument')->item(0)->nodeValue) ? $dom->getElementsByTagName('instrument')->item(0)->nodeValue : NULL,
                    'quicklook'=> $this->getLocation($dom),
                    's2TakeId' => isset($dom->getElementsByTagName('s2takeid')->item(0)->nodeValue) ? $dom->getElementsByTagName('s2takeid')->item(0)->nodeValue : NULL,
                    'cloudCover' => isset($dom->getElementsByTagName('cloudcover')->item(0)->nodeValue) ? $dom->getElementsByTagName('cloudcover')->item(0)->nodeValue : NULL,
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
