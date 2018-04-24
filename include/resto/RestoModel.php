<?php
/*
 * Copyright 2014 Jérôme Gasperi
 *
 * Licensed under the Apache License, version 2.0 (the "License");
 * You may not use this file except in compliance with the License.
 * You may obtain a copy of the License at:
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
 * License for the specific language governing permissions and limitations
 * under the License.
 */

/**
 * RESTo Model
 */
abstract class RestoModel {
    
    /*
     * Model name is mandatory and based on the name
     * of the class
     */
    public $name;
    
    /*
     * Mapping between RESTo model property keys (i.e. array keys - left column)
     * and RESTo database column names (i.e. array values - right column)
     */
    public $properties = array (
            'identifier' => array (
                    'name' => 'identifier',
                    'type' => 'TEXT',
                    'constraint' => 'UNIQUE' 
            ),
            'collection' => array (
                    'name' => 'collection',
                    'type' => 'TEXT' 
            ),
            'productIdentifier' => array (
                    'name' => 'productidentifier',
                    'type' => 'TEXT' 
            ),
            'parentIdentifier' => array (
                    'name' => 'parentIdentifier',
                    'type' => 'TEXT' 
            ),
            'title' => array (
                    'name' => 'title',
                    'type' => 'TEXT' 
            ),
            'description' => array (
                    'name' => 'description',
                    'type' => 'TEXT' 
            ),
            'organisationName' => array (
                    'name' => 'authority',
                    'type' => 'TEXT' 
            ),
            'startDate' => array (
                    'name' => 'startdate',
                    'type' => 'TIMESTAMP' 
            ),
            'completionDate' => array (
                    'name' => 'completiondate',
                    'type' => 'TIMESTAMP' 
            ),
            'productType' => array (
                    'name' => 'producttype',
                    'type' => 'TEXT' 
            ),
            'processingLevel' => array (
                    'name' => 'processinglevel',
                    'type' => 'TEXT' 
            ),
            'platform' => array (
                    'name' => 'platform',
                    'type' => 'TEXT' 
            ),
            'instrument' => array (
                    'name' => 'instrument',
                    'type' => 'TEXT' 
            ),
            'resolution' => array (
                    'name' => 'resolution',
                    'type' => 'NUMERIC' 
            ),
            'sensorMode' => array (
                    'name' => 'sensormode',
                    'type' => 'TEXT' 
            ),
            'orbitNumber' => array (
                    'name' => 'orbitnumber',
                    'type' => 'NUMERIC' 
            ),
            'quicklook' => array (
                    'name' => 'quicklook',
                    'type' => 'TEXT' 
            ),
            'thumbnail' => array (
                    'name' => 'thumbnail',
                    'type' => 'TEXT' 
            ),
            'metadata' => array (
                    'name' => 'metadata',
                    'type' => 'TEXT' 
            ),
            'metadataMimeType' => array (
                    'name' => 'metadata_mimetype',
                    'type' => 'TEXT' 
            ),
            'resource' => array (
                    'name' => 'resource',
                    'type' => 'TEXT' 
            ),
            'resourceMimeType' => array (
                    'name' => 'resource_mimetype',
                    'type' => 'TEXT' 
            ),
            'resourceSize' => array (
                    'name' => 'resource_size',
                    'type' => 'INTEGER' 
            ),
            'resourceChecksum' => array (
                    'name' => 'resource_checksum',
                    'type' => 'TEXT' 
            ),
            'wms' => array (
                    'name' => 'wms',
                    'type' => 'TEXT' 
            ),
            'updated' => array (
                    'name' => 'updated',
                    'type' => 'TIMESTAMP' 
            ),
            'published' => array (
                    'name' => 'published',
                    'type' => 'TIMESTAMP' 
            ),
            'cultivatedCover' => array (
                    'name' => 'lu_cultivated',
                    'type' => 'NUMERIC',
                    'constraint' => 'DEFAULT 0',
                    'notDisplayed' => true 
            ),
            'desertCover' => array (
                    'name' => 'lu_desert',
                    'type' => 'NUMERIC',
                    'contraint' => 'DEFAULT 0',
                    'notDisplayed' => true 
            ),
            'floodedCover' => array (
                    'name' => 'lu_flooded',
                    'type' => 'NUMERIC',
                    'contraint' => 'DEFAULT 0',
                    'notDisplayed' => true 
            ),
            'forestCover' => array (
                    'name' => 'lu_forest',
                    'type' => 'NUMERIC',
                    'constraint' => 'DEFAULT 0',
                    'notDisplayed' => true 
            ),
            'herbaceousCover' => array (
                    'name' => 'lu_herbaceous',
                    'type' => 'NUMERIC',
                    'constraint' => 'DEFAULT 0',
                    'notDisplayed' => true 
            ),
            'iceCover' => array (
                    'name' => 'lu_ice',
                    'type' => 'NUMERIC',
                    'constraint' => 'DEFAULT 0',
                    'notDisplayed' => true 
            ),
            'urbanCover' => array (
                    'name' => 'lu_urban',
                    'type' => 'NUMERIC',
                    'constraint' => 'DEFAULT 0',
                    'notDisplayed' => true 
            ),
            'waterCover' => array (
                    'name' => 'lu_water',
                    'type' => 'NUMERIC',
                    'constraint' => 'DEFAULT 0',
                    'notDisplayed' => true 
            ),
            'snowCover' => array (
                    'name' => 'snowcover',
                    'type' => 'NUMERIC' 
            ),
            'cloudCover' => array (
                    'name' => 'cloudcover',
                    'type' => 'NUMERIC' 
            ),
            'keywords' => array (
                    'name' => 'keywords',
                    'type' => 'TEXT' 
            ),
            'geometry' => array (
                    'name' => 'geometry',
                    'type' => 'GEOMETRY' 
            ),
            'hashes' => array (
                    'name' => 'hashes',
                    'type' => 'TEXT[]',
                    'notDisplayed' => true 
            ),
            'visible' => array (
                    'name' => 'visible',
                    'type' => 'INTEGER'
            ),
            'orbitDirection' => array(
                'name' => 'orbitDirection',
                'type' => 'TEXT'
            ),
            'newVersion' => array (
                    'name' => 'new_version',
                    'type' => 'TEXT'
            ) ,
            'isNrt' => array (
                    'name' => 'isnrt',
                    'type' => 'INTEGER'
            ) ,
            'realtime' => array (
                    'name' => 'realtime',
                    'type' => 'TEXT'
            ),
            'dhusIngestDate' => array (
                    'name' => 'dhusingestdate',
                    'type' => 'TIMESTAMP'
            ),
            'relativeOrbitNumber' => array (
                    'name' => 'relativeorbitnumber',
                    'type' => 'NUMERIC'
            )
    );
    
    /*
     * OpenSearch search filters
     *
     * 'key' :
     * RESTo model property name
     * 'osKey' :
     * OpenSearch property name in template urls
     * 'operation' :
     * Search operation (keywords, intersects, distance, =, <=, >=)
     * 'htmlFilter' : 
     * If set to true then this filter is added to the text/html OpenSearch <Url>
     *
     *
     * Below properties follow the "Paramater extension" (http://www.opensearch.org/Specifications/OpenSearch/Extensions/Parameter/1.0/Draft_2)
     *
     * 'minimum' :
     * Minimum number of times this parameter must be included in the search request (default 0)
     * 'maximum' :
     * Maximum number of times this parameter must be included in the search request (default 1)
     * 'pattern' :
     * Regular expression against which the parameter's value
     * Pattern follows Javascript (http://www.ecma-international.org/publications/standards/Ecma-262.htm)
     * 'title' :
     * Tooltip
     * 'minExclusive'
     * Minimum value for the element that cannot be reached
     * 'maxExclusive'
     * Maximum value for the element that cannot be reached
     * 'options'
     * List of possible values. Two ways
     * 1. Array of predefined value/label
     * array(
     * array(
     * 'value'
     * 'label'
     * ),
     * ...
     * )
     * 2. 'auto'
     * In this case will be computed from facets table
     */
    public $searchFilters = array (
            'searchTerms' => array (
                    'key' => 'hashes',
                    'osKey' => 'q',
                    'operation' => 'keywords',
                    'title' => 'Free text search' 
            ),
            'count' => array (
                    'osKey' => 'maxRecords',
                    'minInclusive' => 1,
                    'maxInclusive' => 500,
                    'title' => 'Number of results returned per page (default 20, max 500)' 
            ),
            'startIndex' => array (
                    'osKey' => 'index',
                    'minInclusive' => 1,
                    'title' => 'Index of the first result returned'
            ),
            'startPage' => array (
                    'osKey' => 'page',
                    'minInclusive' => 1,
                    'title' => 'Results page to return'
            ),
            'language' => array (
                    'osKey' => 'lang',
                    'pattern' => '^[a-z]{2}$',
                    'title' => 'Two letters language code according to ISO 639-1' 
            ),
            'geo:uid' => array (
                    'key' => 'identifier',
                    'osKey' => 'identifier',
                    'operation' => '=',
                    'title' => 'Either resto identifier or productIdentifier'
            ),
            'geo:geometry' => array (
                    'key' => 'geometry',
                    'osKey' => 'geometry',
                    'operation' => 'intersects',
                    'title' => 'Defined in Well Known Text standard (WKT) with coordinates in decimal degrees (EPSG:4326)',
            ),
            'geo:box' => array (
                    'key' => 'geometry',
                    'osKey' => 'box',
                    'operation' => 'intersects',
                    'title' => 'Defined by \'west, south, east, north\' coordinates of longitude, latitude, in decimal degrees (EPSG:4326)',
                    'pattern' => '^[0-9\.\,\-]*$'
            ),
            'geo:name' => array (
                    'key' => 'geometry',
                    'osKey' => 'location',
                    'operation' => 'distance',
                    'title' => 'Location string e.g. Paris, France' 
            ),
            'geo:lon' => array (
                    'key' => 'geometry',
                    'osKey' => 'lon',
                    'operation' => 'distance',
                    'title' => 'Longitude expressed in decimal degrees (EPSG:4326) - should be used with geo:lat',
                    'minInclusive' => -180,
                    'maxInclusive' => 180
            ),
            'geo:lat' => array (
                    'key' => 'geometry',
                    'osKey' => 'lat',
                    'operation' => 'distance',
                    'title' => 'Latitude expressed in decimal degrees (EPSG:4326) - should be used with geo:lon',
                    'minInclusive' => -90,
                    'maxInclusive' => 90
            ),
            'geo:radius' => array (
                    'key' => 'geometry',
                    'osKey' => 'radius',
                    'operation' => 'distance',
                    'title' => 'Expressed in meters - should be used with geo:lon and geo:lat',
                    'minInclusive' => 1
            ),
            'time:start' => array (
                    'key' => 'startDate',
                    'osKey' => 'startDate',
                    'operation' => '>=',
                    'title' => 'Beginning of the time slice of the search query. Format should follow RFC-3339',
                    'pattern' => '^[0-9]{4}-[0-9]{2}-[0-9]{2}(T[0-9]{2}:[0-9]{2}:[0-9]{2}(\.[0-9]+)?(|Z|[\+\-][0-9]{2}:[0-9]{2}))?$'
            ),
            'time:end' => array (
                    // Force the search query to do mapping 'time:end' with 'startDate' properties (on RESTo model)
                    'key' => 'startDate', //'completionDate',
                    'osKey' => 'completionDate',
                    'operation' => '<=',
                    'title' => 'End of the time slice of the search query. Format should follow RFC-3339',
                    'pattern' => '^[0-9]{4}-[0-9]{2}-[0-9]{2}(T[0-9]{2}:[0-9]{2}:[0-9]{2}(\.[0-9]+)?(|Z|[\+\-][0-9]{2}:[0-9]{2}))?$'
            ),
            'eo:parentIdentifier' => array (
                    'key' => 'parentIdentifier',
                    'osKey' => 'parentIdentifier',
                    'operation' => '=',
                    'title' => 'Parent identifier'
            ),
            'eo:productType' => array (
                    'key' => 'productType',
                    'osKey' => 'productType',
                    'operation' => '=',
                    'options' => 'auto',
                    'title' => 'Product type'
            ),
            'eo:processingLevel' => array (
                    'key' => 'processingLevel',
                    'osKey' => 'processingLevel',
                    'operation' => '=',
                    'options' => 'auto',
                    'title' => 'Processing level'
            ),
            'eo:platform' => array (
                    'key' => 'platform',
                    'osKey' => 'platform',
                    'operation' => '=',
                    'keyword' => array (
                            'value' => '{:platform:}',
                            'type' => 'platform' 
                    ),
                    'options' => 'auto',
                    'title' => 'Mission/Satellite identifier (3 letters)'
            ),
            'eo:instrument' => array (
                    'key' => 'instrument',
                    'osKey' => 'instrument',
                    'operation' => '=',
                    'keyword' => array (
                            'value' => '{:instrument:}',
                            'type' => 'instrument' 
                    ),
                    'options' => 'auto',
                    'title' => 'Instrument'
            ),
            'eo:resolution' => array (
                    'key' => 'resolution',
                    'osKey' => 'resolution',
                    'operation' => 'interval',
                    'title' => 'Spatial resolution expressed in meters',
                    'pattern' => '^(?:[1-9]\d*|0)?(?:\.\d+)?$',
                    'quantity' => array (
                            'value' => 'resolution',
                            'unit' => 'm' 
                    ) 
            ),
            'eo:organisationName' => array (
                    'key' => 'organisationName',
                    'osKey' => 'organisationName',
                    'operation' => '=',
                    'title' => 'Organisation name'
            ),
            'eo:orbitDirection' => array (
                'key' => 'orbitDirection',
                'osKey' => 'orbitDirection',
                'operation' => '=',
                'options' => 'auto',
                'title' => 'Orbit direction'
            ),
            'eo:orbitNumber' => array (
                    'key' => 'orbitNumber',
                    'osKey' => 'orbitNumber',
                    'operation' => 'interval',
                    'minInclusive' => 1,
                    'quantity' => array (
                            'value' => 'orbit' 
                    ),
                    'title' => 'Orbit number'
            ),
            'eo:sensorMode' => array (
                    'key' => 'sensorMode',
                    'osKey' => 'sensorMode',
                    'operation' => '=',
                    'options' => 'auto',
                    'title' => 'Sensor mode'
            ),
            'eo:cloudCover' => array (
                    'key' => 'cloudCover',
                    'osKey' => 'cloudCover',
                    'operation' => 'interval',
                    'title' => 'Cloud cover expressed in percent',
                    /*'minInclusive' => 0,
                    'maxInclusive' => 100,*/
                    'pattern' => '^(\[|\]|[0-9])?[0-9]+$|^[0-9]+?(\[|\])$|^(\[|\])[0-9]+,[0-9]+(\[|\])$',
                    'quantity' => array (
                            'value' => 'cloud',
                            'unit' => '%' 
                    ) 
            ),
            'eo:snowCover' => array (
                    'key' => 'snowCover',
                    'osKey' => 'snowCover',
                    'operation' => 'interval',
                    'title' => 'Snow cover expressed in percent',
                    'minInclusive' => 0,
                    'maxInclusive' => 100,
                    'quantity' => array (
                            'value' => 'snow',
                            'unit' => '%' 
                    ) 
            ),
            'resto:cultivatedCover' => array (
                    'key' => 'cultivatedCover',
                    'osKey' => 'cultivatedCover',
                    'operation' => 'interval',
                    'title' => 'Cultivated area expressed in percent',
                    'minInclusive' => 0,
                    'maxInclusive' => 100,
                    'quantity' => array (
                            'value' => 'cultivated',
                            'unit' => '%' 
                    ) 
            ),
            'resto:desertCover' => array (
                    'key' => 'desertCover',
                    'osKey' => 'desertCover',
                    'operation' => 'interval',
                    'title' => 'Desert area expressed in percent',
                    'minInclusive' => 0,
                    'maxInclusive' => 100,
                    'quantity' => array (
                            'value' => 'desert',
                            'unit' => '%' 
                    ) 
            ),
            'resto:floodedCover' => array (
                    'key' => 'floodedCover',
                    'osKey' => 'floodedCover',
                    'operation' => 'interval',
                    'title' => 'Flooded area expressed in percent',
                    'minInclusive' => 0,
                    'maxInclusive' => 100,
                    'quantity' => array (
                            'value' => 'flooded',
                            'unit' => '%' 
                    ) 
            ),
            'resto:forestCover' => array (
                    'key' => 'forestCover',
                    'osKey' => 'forestCover',
                    'operation' => 'interval',
                    'title' => 'Forest area expressed in percent',
                    'minInclusive' => 0,
                    'maxInclusive' => 100,
                    'quantity' => array (
                            'value' => 'forest',
                            'unit' => '%' 
                    ) 
            ),
            'resto:herbaceousCover' => array (
                    'key' => 'herbaceousCover',
                    'osKey' => 'herbaceousCover',
                    'operation' => 'interval',
                    'title' => 'Herbaceous area expressed in percent',
                    'minInclusive' => 0,
                    'maxInclusive' => 100,
                    'quantity' => array (
                            'value' => 'herbaceous',
                            'unit' => '%' 
                    ) 
            ),
            'resto:iceCover' => array (
                    'key' => 'iceCover',
                    'osKey' => 'iceCover',
                    'operation' => 'interval',
                    'title' => 'Ice area expressed in percent',
                    'minInclusive' => 0,
                    'maxInclusive' => 100,
                    'quantity' => array (
                            'value' => 'ice',
                            'unit' => '%' 
                    ) 
            ),
            'resto:urbanCover' => array (
                    'key' => 'urbanCover',
                    'osKey' => 'urbanCover',
                    'operation' => 'interval',
                    'title' => 'Urban area expressed in percent',
                    'minInclusive' => 0,
                    'maxInclusive' => 100,
                    'quantity' => array (
                            'value' => 'urban',
                            'unit' => '%' 
                    ) 
            ),
            'resto:waterCover' => array (
                    'key' => 'waterCover',
                    'osKey' => 'waterCover',
                    'operation' => 'interval',
                    'title' => 'Water area expressed in percent',
                    'minInclusive' => 0,
                    'maxInclusive' => 100,
                    'quantity' => array (
                            'value' => 'water',
                            'unit' => '%' 
                    ) 
            ),
            'dc:date' => array (
                    'key' => 'updated',
                    'osKey' => 'updated',
                    'operation' => '>=',
                    'pattern' => '^[0-9]{4}-[0-9]{2}-[0-9]{2}(T[0-9]{2}:[0-9]{2}:[0-9]{2}(\.[0-9]+)?(|Z|[\+\-][0-9]{2}:[0-9]{2}))?$',
                    'title' => 'Last update of the product within database'
            ),
            'resto:publishedBegin' => array (
                    'key' => 'published',
                    'osKey' => 'publishedBegin',
                    'operation' => '>=',
                    'title' => 'Begin publication date',
                    'pattern' => '^[0-9]{4}-[0-9]{2}-[0-9]{2}(T[0-9]{2}:[0-9]{2}:[0-9]{2}(\.[0-9]+)?(|Z|[\+\-][0-9]{2}:[0-9]{2}))?$'
            ),
            'resto:publishedEnd' => array (
                    'key' => 'published',
                    'osKey' => 'publishedEnd',
                    'operation' => '<=',
                    'title' => 'End publication date',
                    'pattern' => '^[0-9]{4}-[0-9]{2}-[0-9]{2}(T[0-9]{2}:[0-9]{2}:[0-9]{2}(\.[0-9]+)?(|Z|[\+\-][0-9]{2}:[0-9]{2}))?$'
            ),
            'resto:isNrt' => array (
                    'key' => 'isNrt',
                    'osKey' => 'isNrt',
                    'operation' => '=',
                    'minInclusive' => 0,
                    'maxInclusive' => 1,
                    'title' => 'Near RealTime Products' 
            		
            ),
            'resto:realtime' => array (
                    'key' => 'realtime',
                    'osKey' => 'realtime',
                    'operation' => '=',
            		'title' => 'Near RealTime Products',
                    'keyword' => array (
                            'value' => '{:realtime:}',
                            'type' => 'realtime' 
                    ),
                    'options' => 'auto' 
            ),
            'resto:relativeOrbitNumber' => array (
                    'key' => 'relativeOrbitNumber',
                    'osKey' => 'relativeOrbitNumber',
                    'operation' => 'interval',
                    'minInclusive' => 1,
                    'title' => 'Relative orbit number', 
                    'quantity' => array (
                            'value' => 'relativeorbit'
                    )
            ),
    );
    public $extendedProperties = array ();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->name = get_class($this);
        $this->properties = array_merge($this->properties, $this->extendedProperties);
    }
    
    /**
     * Return property database column type
     *
     * @param type $modelKey
     */
    public function getDbType($modelKey) {
        if (!isset($this->properties[$modelKey])) {
            return null;
        }
        
        switch (strtoupper($this->properties[$modelKey]['type'])) {
            case 'INTEGER' :
                return 'integer';
            case 'NUMERIC' :
                return 'float';
            case 'TIMESTAMP' :
                return 'date';
            case 'GEOMETRY' :
                return 'geometry';
            case 'TEXT[]' :
                return 'array';
            default :
                return 'string';
        }
    }
    
    /**
     * Return property database column name
     *
     * @param string $modelKey : RESTo model key
     * @return array
     */
    public function getDbKey($modelKey) {
        if (!isset($modelKey) || !isset($this->properties[$modelKey]) || !is_array($this->properties[$modelKey])) {
            return null;
        }
        return $this->properties[$modelKey]['name'];
    }
    
    /**
     * Remap properties array accordingly to $inputMapping array
     *
     * $inputMapping array structure:
     *
     * array(
     * 'propertyNameInInputFile' => 'restoPropertyName' or array('restoPropertyName1', 'restoPropertyName2)
     * )
     *
     * @param Array $properties
     */
    public function mapInputProperties($properties) {
        if (property_exists($this, 'inputMapping')) {
            foreach ($this->inputMapping as $key => $arr) {
                if (isset($properties[$key])) {
                    if (!is_array($arr)) {
                        $arr = Array (
                                $arr 
                        );
                    }
                    for($i = count($arr); $i--;) {
                        $properties[$arr[$i]] = $properties[$key];
                    }
                    unset($properties[$key]);
                }
            }
        }
        /*
         * Remove unknown properties (i.e. properties not in model)
         */
        foreach (array_keys($properties) as $key) {
            if (!isset($this->properties[$key])) {
                unset($properties[$key]);
            }
        }
        return $properties;
    }
    
    /**
     * Store feature within {collection}.features table following the class model
     *
     * @param array $data : array (MUST BE GeoJSON in abstract Model)
     * @param RestoCollection $collection
     *
     */
    public function storeFeature($data, $collection)
    {
        /*
         * Assume input file or stream is a JSON Feature
         */
        if (!RestoGeometryUtil::isValidGeoJSONFeature($data)) {
            RestoLogUtil::httpError(500, 'Invalid feature description');
        }

        /*
         * Remap properties between RESTo model and input
         * GeoJSON Feature file
         */
        $properties = $this->mapInputProperties($data['properties']);

        if (empty($properties['title'])) {
            RestoLogUtil::httpError(500, 'Invalid feature description - Title is not defined');
        }
        
        if (empty($properties['orbitDirection'])) {
            RestoLogUtil::httpError(500, 'Invalid feature description - Orbit direction is not defined');
        }
        
        if (empty($properties['relativeOrbitNumber'])) {
            RestoLogUtil::httpError(500, 'Invalid feature description - Relative orbit number is not defined');
        }
        
        if (empty($properties['orbitNumber'])) {
            RestoLogUtil::httpError(500, 'Invalid feature description - Absolute orbit number is not defined');
        }
        
        if (empty($properties['resourceSize'])) {
            RestoLogUtil::httpError(500, 'Invalid feature description - Resource size is not defined');
        }
        
        if (empty($properties['dhusIngestDate'])) {
            RestoLogUtil::httpError(500, 'Invalid feature description - DHUS ingest date is not defined');
        }

        if (empty($properties['realtime'])) {
            RestoLogUtil::httpError(500, 'Invalid feature description - Realtime is not set');
        }

        /*
         * Compute unique identifier
        */
        if (!isset($data['id']) || !RestoUtil::isValidUUID($data['id'])) {
            $featureIdentifier = $collection->toFeatureId((isset($properties['productIdentifier']) ? $properties['productIdentifier'] : md5(microtime() . rand())));
        } else {
            $featureIdentifier = $data['id'];
        }

        /*
         * First check if feature is already in database
         * (do this before getKeywords to avoid iTag process)
         */
        $schemaName = '_' . strtolower($collection->name);
        if ($collection->context->dbDriver->check(RestoDatabaseDriver::FEATURE, array(
                'featureIdentifier' => $featureIdentifier,
                'schema' => $schemaName
        ))) {
            RestoLogUtil::httpError(409, 'Feature ' . $featureIdentifier . ' already in database');
        }
        
        /*
         * For S1 collection and $context->obsolescenceS1useDhusIngestDate == false
         *    Check if we get multiples products version with the same realtime
         */
        if ($collection->name === 'S1' &&
            $collection->context->obsolescenceS1useDhusIngestDate === false &&
            $collection->context->dbDriver->check(RestoDatabaseDriver::FEATURE_S1_REALTIME, array(
                    'collectionName' => 'S1',
                    'realtime' => $properties['realtime'],
                    'pattern' => $this->getFeatureVersionPattern($properties['title'], 'S1')
            ))
        ) 
        {
                RestoLogUtil::httpError(409, 'multiple product versions with same realtime (' . $properties['realtime'] . ': ' . $properties['title'] . ')');
        }
        
        /*
         * Store feature
         */
        $collection->context->dbDriver->store(RestoDatabaseDriver::FEATURE, array (
                'collection' => $collection,
                'featureArray' => array (
                        'type' => 'Feature',
                        'id' => $featureIdentifier,
                        'geometry' => $data['geometry'],
                        'properties' => array_merge($properties, array (
                            'keywords' => $this->getKeywords($properties, $data['geometry'], $collection)
                        ))
                )
        ));

        $feature = new RestoFeature($collection->context, $collection->user, array (
                'featureIdentifier' => $featureIdentifier,
                'collection' => $collection
        ));

        /*
         * Updates old versions (obsolete) of feature
         */
        $this->updateFeatureVersions($feature);

        return $feature;
    }
    
    /**
     * Updates versions of specified feature
     * 
     * @param RestoFeature $feature
     */
    private function updateFeatureVersions(RestoFeature $feature)
    {
        $featureArray = $feature->toArray();
        $properties = $featureArray['properties'];
        $collection = $feature->collection;

        /*
         * Updates product versions visibility
         */
        
        // get all the product versions ordered by the newest first
        $allVersions = $collection->context->dbDriver->get(RestoDatabaseDriver::FEATURE_ALL_VERSIONS, array(
            'context' => $collection->context,
            'collection' => $collection,
            'pattern' => $this->getFeatureVersionPattern($properties['productIdentifier'], $collection->name)
        ));
        
        $count = count($allVersions);
        // if there is more than one version of the product
        if ($count > 1) {

            $lastVersion = array_shift($allVersions);

            // in all cases, the newest version is set to visible
            $collection->context->dbDriver->update(RestoDatabaseDriver::FEATURE_VERSION, array(
                'collection' => $collection,
                'featuresArray' => array($lastVersion),
                'visible' => 1,
                'newVersion' => null
            ));
            // the other versions (old versions) become invisible            
            $collection->context->dbDriver->update(RestoDatabaseDriver::FEATURE_VERSION, array(
                'collection' => $collection,
                'featuresArray' => $allVersions,
                'visible' => 0,
                'newVersion' => $lastVersion['identifier']
            ));
        }
    }

    /**
     * Update feature within {collection}.features table following the class model
     *
     * @param RestoFeature feature : feature to update
     * @param array $data : array (MUST BE GeoJSON in abstract Model)
     */
    public function updateFeature($feature, $data, $obsolescence = true) {

        /*
         * Assume input file or stream is a JSON Feature
         */
        if (!RestoGeometryUtil::isValidGeoJSONFeature($data)) {
            RestoLogUtil::httpError(500, 'Invalid feature description');
        }

        /*
         * Remap properties between RESTo model and input
         * GeoJSON Feature file
         */
        $properties = $this->mapInputProperties($data['properties']);

        if (empty($properties['title'])) {
            RestoLogUtil::httpError(500, 'Invalid feature description - Title is not set');
        }
        
        if (empty($properties['orbitDirection'])) {
            RestoLogUtil::httpError(500, 'Invalid feature description - Orbit direction is not set');
        }
        
        if (empty($properties['resourceSize'])) {
            RestoLogUtil::httpError(500, 'Invalid feature description - Resource size is not set');
        }
        
        if (empty($properties['dhusIngestDate'])) {
            RestoLogUtil::httpError(500, 'Invalid feature description - DHUS ingest date is not set');
        }

        if (empty($properties['realtime'])) {
            RestoLogUtil::httpError(500, 'Invalid feature description - Realtime is not set');
        }

        /*
         * Updates feature
        */
        $feature->collection->context->dbDriver->update(RestoDatabaseDriver::FEATURE, array (
                'collection' => $feature->collection,
                'featureArray' => array (
                        'type' => 'Feature',
                        'id' => $feature->identifier,
                        'geometry' => $data['geometry'],
                        'properties' => array_merge($properties, array (
                                'keywords' => $this->getKeywords($properties, $data['geometry'], $feature->collection),
                                'visible' => 1, // force visibility of products to true (updateFeatureVersions will update visibility),
                                'newVersion' => null
                        ))
                )
        ));

        if ($obsolescence === true){
            $this->updateFeatureVersions($feature);
        }
    }

    /**
     * Get facet fields from model
     */
    public function getFacetFields() {
        $facetFields = array (
                'collection',
                'continent'
        );
        foreach (array_values($this->searchFilters) as $filter) {
            if (isset($filter['options']) && $filter['options'] === 'auto') {
                $facetFields[] = $filter['key'];
            }
        }
        return $facetFields;
    }
    
    /**
     * Check if value is valid for a given filter regarding the model
     *
     * @param string $filterKey
     * @param string $value
     *
     */
    public function validateFilter($filterKey, $value) {
    
        /*
         * Check pattern for string
         */
        if (isset($this->searchFilters[$filterKey]['pattern'])) {
            if (preg_match('\'' . $this->searchFilters[$filterKey]['pattern'] . '\'', $value) !== 1) {
                RestoLogUtil::httpError(400, 'Value for "' . $this->searchFilters[$filterKey]['osKey'] . '" must follow the pattern ' . $this->searchFilters[$filterKey]['pattern']);
            }
        }
        /*
         * Check pattern for number
         * By know, we bypass "count" as can't manage a correct error display
         */
        else if ($filterKey != "count" && (isset($this->searchFilters[$filterKey]['minInclusive']) || isset($this->searchFilters[$filterKey]['maxInclusive']))) {
            if (!is_numeric($value)) {
                RestoLogUtil::httpError(400, 'Value for "' . $this->searchFilters[$filterKey]['osKey'] . '" must be numeric');
            }
            if (isset($this->searchFilters[$filterKey]['minInclusive']) && $value < $this->searchFilters[$filterKey]['minInclusive']) {
                RestoLogUtil::httpError(400, 'Value for "' . $this->searchFilters[$filterKey]['osKey'] . '" must be greater than ' . ($this->searchFilters[$filterKey]['minInclusive'] - 1));
            }
            if (isset($this->searchFilters[$filterKey]['maxInclusive']) && $value > $this->searchFilters[$filterKey]['maxInclusive']) {
                RestoLogUtil::httpError(400, 'Value for "' . $this->searchFilters[$filterKey]['osKey'] . '" must be lower than ' . ($this->searchFilters[$filterKey]['maxInclusive'] + 1));
            }
        }
    
        return true;
    }
    
    protected function getElementByName($dom, $tagName, $type = NULL) {
        $value = NULL;
        
        switch ($type) {
            case 'NUMERIC':
            case 'INTEGER':
              $value = empty($dom->getElementsByTagName($tagName)->item(0)->nodeValue) ? NULL : $dom->getElementsByTagName($tagName)->item(0)->nodeValue;
              break;  
            default:
                $value = isset($dom->getElementsByTagName($tagName)->item(0)->nodeValue) ? $dom->getElementsByTagName($tagName)->item(0)->nodeValue : NULL;
        }
        return $value;
        
    }
    
    /**
     * Compute keywords from properties array
     *
     * @param array $properties
     * @param array $geometry (GeoJSON)
     * @param RestoCollection $collection
     */
    private function getKeywords($properties, $geometry, $collection) {
    
        /*
         * Keywords utilities
         */
        $keywordsUtil = new RestoKeywordsUtil();
    
        /*
         * Initialize keywords array
        */
        $keywords = isset($properties['keywords']) ? $properties['keywords'] : array ();
    
        /*
         * Validate keywords
        */
        if (!$keywordsUtil->areValids($keywords)) {
            RestoLogUtil::httpError(500, 'Invalid keywords property elements');
        }
    
        return array_merge($keywords, $keywordsUtil->computeKeywords($properties, $geometry, $collection));
    }

    /**
     * 
     * @param unknown $productIdentifier
     * @param unknown $collection
     * @return Ambigous <NULL, string>
     */
    private function getFeatureVersionPattern($productIdentifier, $collection)
    {
        $length = strlen($productIdentifier);
        
        $regexFeatureVersions = null;
        switch ($collection) {
            case 'S1' :
                /* 
                 * ignore checksum (CCCC)
                 *      MMM_BB_TTTR_LFPP_YYYYMMDDTHHMMSS_YYYYMMDDTHHMMSS_OOOOOO_DDDDDD_CCCC
                 *      pattern version ==> MMM_BB_TTTR_LFPP_YYYYMMDDTHHMMSS_YYYYMMDDTHHMMSS_OOOOOO_DDDDDD
                 */
                $regexFeatureVersions = substr($productIdentifier, 0, $length - 5);
                break;
            case 'S2' :
                /* 
                 * ignore ... (yyyymmddThhmmss)
                 *    MMM_CCCC_TTTTTTTTTT_ssss_yyyymmddThhmmss_ROOO_VYYYYMMTDDHHMMSS_YYYYMMTDDHHMMSS
                 *    pattern version ==> MMM_CCCC_TTTTTTTTTT_ssss_ROOO_VYYYYMMTDDHHMMSS_YYYYMMTDDHHMMSS
                 */
                $regexFeatureVersions = substr($productIdentifier, 0, 24) . substr($productIdentifier, 40);
                break;
            case 'S2ST' :
                /* 
                 * ignore processing baseline number (xxyy)
                 *      MMM_MSIL1C_YYYYMMDDTHHMMSS_Nxxyy_ROOO_Txxxxx_YYYYMMDDTHHMMSS
                 *      pattern version==> MMM_MSIL1C_YYYYMMDDTHHMMSS_N_ROOO_Txxxxx_YYYYMMDDTHHMMSS
                 */
                $regexFeatureVersions = substr($productIdentifier, 0, 28) . substr($productIdentifier, 32);
                break;
            case 'S3' :
                /* 
                 * ignore product creation date + timeliness
                 *      MMM_OL_L_TTTTTT_yyyymmddThhmmss_YYYYMMDDTHHMMSS_YYYYMMDDTHHMMSS_IIIIIIIIIIIIIIIII_GGG_P_XX_NNN
                 *      pattern version => MMM_OL_L_TTTTTT_YYYYMMDDTHHMMSS_YYYYMMDDTHHMMSS_IIIIIIIIIIIIIIIII_GGG_P_NNN
                 */
                $regexFeatureVersions = substr($productIdentifier, 0, 48)
                . substr($productIdentifier, 64, 24)
                . substr($productIdentifier, 91);
                break;
            default :
                break;
        }
        
        return $regexFeatureVersions;
    }
}
