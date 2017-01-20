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
 * RESTo Sentinel-1 model 
 * 
 * Input metadata is an XML file 
 * 
 */
class RestoModel_sentinel1 extends RestoModel {
    
    public $extendedProperties = array(
        'swath' => array(
            'name' => 'swath',
            'type' => 'TEXT'
        ),
        'polarisation' => array(
            'name' => 'polarisation',
            'type' => 'TEXT'
        ),
        'missionTakeId' => array(
            'name' => 'missiontakeid',
            'type' => 'INTEGER'
        ),
        'cycleNumber' => array(
                'name' => 'cyclenumber',
                'type' => 'INTEGER'
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
        
        $this->searchFilters['eo:polarisation'] = array (
                'key' => 'polarisation',
                'osKey' => 'polarisation',
                'operation' => '=',
                'options' => 'auto'
        );

        $this->searchFilters['eo:swath'] = array (
                'key' => 'swath',
                'osKey' => 'swath',
                'operation' => '=',
                'options' => 'auto'
        );

        $this->searchFilters['resto:cycleNumber'] = array (
                'key' => 'cycleNumber',
                'osKey' => 'cycleNumber',
                'operation' => 'interval',
                'minInclusive' => 1,
                'quantity' => array (
                        'value' => 'cyclenumber'
                )
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
        $partial_indetifier = substr($product_indetifier, 0, -4);
        return parent::hasOldFeature($partial_indetifier . '%', $collection);
    }
    
    /**
     * Create JSON feature from xml string
     * 
     * @param {String} $xml : $xml string
     */
    private function parse($xml) {
        
        $dom = new DOMDocument();
        $dom->loadXML(rawurldecode($xml));
        /* 
         * adsHeader is a tag only found in the old xml version
         */
        $verifyVersion = $dom->getElementsByTagName('adsHeader');
        if($verifyVersion->length == 0){
            /* 
             * We parse the file with the new version
             */
            return $this->parseNew($dom);
        }else{
            /* 
             * We parse the file with the old version 
             */
            return $this->parseOld($dom);
        }
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
    <processingLevel>2</processingLevel>
    <mode>IW</mode>
    <absoluteOrbitNumber>6992</absoluteOrbitNumber>
    <orbitDirection>ASCENDING</orbitDirection>
    <swath>IW</swath>
    <polarisation>VV VH</polarisation>
    <missiontakeid>38865</missiontakeid>
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
        /*
         * Performs an inversion of the specified Sentinel-1 quicklooks footprint (inside the ZIP files, i.e SAFE product).
         * The datahub systematically performs an inversion of the Sentinel-1 quicklooks taking as input the quicklook images (.png) inside
         * the ZIP files (i.e. as produced by the S1 ground segment).
         */
        $polygon = RestoGeometryUtil::wktPolygonToArray($this->getElementByName($dom, 'footprint'));
        $polygon = array(SentinelUtil::reorderSafeFootprintToDhus($polygon, $orbitDirection));

        /*
         * Initialize feature
         */
        $feature = array(
                'type' => 'Feature',
                'geometry' => array(
                        'type' => 'Polygon',
                        'coordinates' => $polygon,
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
                    'relativeOrbitNumber' => $this->getElementByName($dom, 'relativeOrbitNumber'),
                    'cycleNumber' => $this->getElementByName($dom, 'cycle'),
                    'orbitDirection' => $orbitDirection,
                    'swath' => $this->getElementByName($dom, 'swath'),
                    'polarisation' => $this->getElementByName($dom, 'polarisation'),
                    'missionTakeId' => $this->getElementByName($dom, 'missiontakeid'),
                	'instrument'=> $this->getElementByName($dom, 'instrument'),
                    'quicklook'=> $this->getLocation($dom),
                    'cloudCover' => 0,
                    'isNrt' => $this->getElementByName($dom,'isNrt'),
                    'realtime' => $this->getElementByName($dom,'realtime'),
                    'dhusIngestDate' => $this->getElementByName($dom, 'dhusIngestDate')
                )
        );

        return $feature;
    }

    /**
     * Create JSON feature from old resource xml string
     *
 <product>
  <adsHeader>
    <missionId>S1A</missionId>
    <productType>GRD</productType>
    <polarisation>VV</polarisation>
    <mode>IW</mode>
    <swath>IW</swath>
    <startTime>2014-10-03T18:47:39.842715</startTime>
    <stopTime>2014-10-03T18:48:08.834276</stopTime>
    <absoluteOrbitNumber>2669</absoluteOrbitNumber>
    <missionDataTakeId>12181</missionDataTakeId>
    <imageNumber>001</imageNumber>
  </adsHeader>
  (...)
  <geolocationGrid>
    <geolocationGridPointList count="231">
      <geolocationGridPoint>
        <azimuthTime>2014-10-03T18:47:39.842455</azimuthTime>
        <slantRangeTime>5.364633780973990e-03</slantRangeTime>
        <line>0</line>
        <pixel>0</pixel>
        <latitude>6.588778439216060e+01</latitude>
        <longitude>1.785002983064243e+02</longitude>
    (...)
     *
     * @param {DOMDocument} $dom : $dom DOMDocument
     */
    private function parseOld($dom){
        /*
         * Retreives geolocation grid point
         */
        $geolocationGridPoint = $dom->getElementsByTagName('geolocationGridPoint');
        /*
         * Retreives orbit direction
         */
        $orbitDirection = strtolower($dom->getElementsByTagName('pass')->item(0)->nodeValue);
        /*
         * Performs an inversion of the specified Sentinel-1 quicklooks footprint (inside the ZIP files, i.e SAFE product).
         * The datahub systematically performs an inversion of the Sentinel-1 quicklooks taking as input the quicklook images (.png) inside 
         * the ZIP files (i.e. as produced by the S1 ground segment).
         */
        $polygon = SentinelUtil::readFootprintFromGeolocationGridPoint($geolocationGridPoint, $orbitDirection);

        /*
         * Initialize feature
        */
        $feature = array(
                'type' => 'Feature',
                'geometry' => array(
                        'type' => 'Polygon',
                        'coordinates' => array($polygon)
                ),
                'properties' => array(
                        'productIdentifier' => $dom->getElementsByTagName('title')->item(0)->nodeValue,
                        'title' => $dom->getElementsByTagName('title')->item(0)->nodeValue,
                        'resourceSize' => $dom->getElementsByTagName('resourceSize')->item(0)->nodeValue,
                        'authority' => 'ESA',
                        'startDate' => $dom->getElementsByTagName('startTime')->item(0)->nodeValue,
                        'completionDate' => $dom->getElementsByTagName('stopTime')->item(0)->nodeValue,
                        'productType' => $dom->getElementsByTagName('productType')->item(0)->nodeValue,
                        'processingLevel' => 'LEVEL1',
                        'platform' => $dom->getElementsByTagName('missionId')->item(0)->nodeValue,
                        'sensorMode' => $dom->getElementsByTagName('mode')->item(0)->nodeValue,
                        'orbitNumber' => $dom->getElementsByTagName('absoluteOrbitNumber')->item(0)->nodeValue,
                        'orbitDirection' => $orbitDirection,
                        'swath' => $dom->getElementsByTagName('swath')->item(0)->nodeValue,
                        'polarisation' => $dom->getElementsByTagName('polarisation')->item(0)->nodeValue,
                        'missionTakeId' => $dom->getElementsByTagName('missionDataTakeId')->item(0)->nodeValue,
                        'quicklook'=> $this->getLocation($dom),
                        'cloudCover' => 0,
                        'dhusIngestDate' => $this->getElementByName($dom, 'dhusIngestDate')
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
