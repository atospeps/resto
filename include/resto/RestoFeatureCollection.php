
<?php
/*
 * Copyright 2014 Jérôme Gasperi
 *
 * Licensed under the Apache License, version 2.0 (the "License");
 * You may not use this file except in compliance with the License.
 * You may obtain a copy of the License at:
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
 * License for the specific language governing permissions and limitations
 * under the License.
 */

/**
 * RESTo FeatureCollection
 */
class RestoFeatureCollection {
    
    /*
     * Context
     */
    public $context;
    
    /*
     * User
     */
    public $user;
    
    /*
     * Parent collection
     */
    private $defaultCollection;
    
    /*
     * FeatureCollectionDescription
     */
    private $description;
    
    /*
     * Features
     */
    private $restoFeatures;
    
    /*
     * All collections
     */
    private $collections = array();
    
    /*
     * Model of the main collection
     */
    private $defaultModel;

    /*
     * Total number of resources relative to the query
     */
    private $paging = array();
    
    /*
     * Query analyzer
     */
    private $queryAnalyzer;
    
    /*
     * Storage mode constants
     */
    const STORAGE_MODE_DISK = 'disk';
    const STORAGE_MODE_STAGING = 'staging';
    const STORAGE_MODE_TAPE = 'tape';
    const STORAGE_MODE_UNAIVALABLE = 'unaivalable';
    const STORAGE_MODE_UNKNOWN = 'unknown';
    
    /**
     * Constructor 
     * 
     * @param RestoResto $context : Resto Context
     * @param RestoUser $user : Resto user
     * @param RestoCollection or array of RestoCollection $collections => First collection is the master collection !!
     */
    public function __construct($context, $user, $collections, $countFeature = false) {
        
        if (!isset($context) || !is_a($context, 'RestoContext')) {
            RestoLogUtil::httpError(500, 'Context is undefined or not valid');
        }
        
        $this->context = $context;
        $this->user = $user;
        if (isset($this->context->modules['QueryAnalyzer'])) {
            $this->queryAnalyzer = new QueryAnalyzer($this->context, $this->user);
        }
 
        $this->initialize($collections, $countFeature);
        
    }
  
    /**
     * Output product description as a PHP array
     * 
     * @param boolean publicOutput
     */
    public function toArray($publicOutput = false) {
        $features = array();
        for ($i = 0, $l = count($this->restoFeatures); $i < $l; $i++) {
            $features[] = $this->restoFeatures[$i]->toArray($publicOutput);
        }
        return array_merge($this->description, array('features' => $features));
    }
    
    /**
     * Output product description as a GeoJSON FeatureCollection
     * 
     * @param boolean $pretty : true to return pretty print
     */
    public function toJSON($pretty = false) {
        return RestoUtil::json_format($this->toArray(true), $pretty);
    }
    
    /**
     * Output product description as an ATOM feed
     */
    public function toATOM() {
        
        /*
         * Initialize ATOM feed
         */
        $atomFeed = new RestoATOMFeed($this->description['properties']['id'], isset($this->description['properties']['title']) ? $this->description['properties']['title'] : '', $this->getATOMSubtitle());
       
        /*
         * Set collection elements
         */
        $atomFeed->setCollectionElements($this->description['properties']);
        
        /*
         * Add one entry per product
         */
        $atomFeed->addEntries($this->restoFeatures, $this->context);

        /*
         * Return ATOM result
         */
        return $atomFeed->toString();
    }
    
    /**
     * Initialize RestoFeatureCollection from database
     * 
     * @param RestoCollection or array of RestoCollection $collections
     * @return type
     */
    private function initialize($collections, $countFeature = false) {
        if (!isset($collections) || (is_array($collections) && count($collections) === 0)) {
            $this->defaultModel = new RestoModel_default();
        }
        else if (!is_array($collections)) {
            $this->defaultCollection = $collections;
            $this->defaultModel = $this->defaultCollection->model;
        }
        else {
            $this->collections = $collections;
            reset($collections);
            $this->defaultCollection = $this->collections[key($collections)];
            $this->defaultModel = $this->defaultCollection->model;
        }
        
        if(!$countFeature) {
            return $this->loadFromStore();
        }
    }

    /**
     * Set featureCollection from database
     */
    private function loadFromStore() {
        
        /*
         * Request start time
         */
        $this->requestStartTime = microtime(true);
        
        /*
         * Clean search filters
         */
        $originalFilters = $this->getOriginalFilters();
        
        /*
         * Result par page
         */
        $limit =$this->context->dbDriver->resultsPerPage;
        if(isset($originalFilters['count']) && is_numeric($originalFilters['count'])) {
            /*
             * Number of returned results is never greater than MAXIMUM_LIMIT
             */
            $limit = min($originalFilters['count'], isset($this->defaultModel->searchFilters['count']['maxInclusive']) ? $this->defaultModel->searchFilters['count']['maxInclusive'] : 500);
            /*
             * Number of returned results is never lower than MINIMUM_LIMIT
             */
            $limit = max($limit, isset($this->defaultModel->searchFilters['count']['minInclusive']) ? $this->defaultModel->searchFilters['count']['minInclusive'] : 1);
        }
        
        /*
         * Compute offset based on startPage or startIndex
         */
        $offset = $this->getOffset($originalFilters, $limit);
        
        /*
         * Query Analyzer 
         */
        $analysis = $this->analyze($originalFilters);
        
        /*
         * Completely not understood query - return an empty result without
         * launching a search on the database
         */
        if (isset($analysis['notUnderstood'])) {
             $this->restoFeatures = array();
             $this->paging = $this->getPaging(array(
                 'total' => 0,
                 'isExact' => true
             ), $limit, $offset);
        }
        /*
         * Read features from database
         */   
        else {
            $this->loadFeatures($analysis['searchFilters'], $limit, $offset);
        }
        
        /*
         * Set description
         */
        $this->setDescription($analysis, $offset, $limit);
        
    }
    
    /**
     * Set description
     * 
     * @param array $analysis
     * @param integer $offset
     * @param integer $limit
     */
    private function setDescription($analysis, $offset, $limit) {
        
        /*
         * Query is made from request parameters
         */
        $query = $this->cleanFilters($analysis['searchFilters']);
        
        /*
         * Sort results
         */
        $this->description = array(
            'type' => 'FeatureCollection',
            'properties' => array(
                'title' => $analysis['analysis']['query'],
                'id' => RestoUtil::UUIDv5((isset($this->defaultCollection) ? $this->defaultCollection->name : '*') . ':' . json_encode($query)),
                'totalResults' => $this->paging['count']['total'],
                'exactCount' => $this->paging['count']['isExact'],
                'startIndex' => $offset + 1,
                'itemsPerPage' => count($this->restoFeatures),
                'totalItemsPerPage' => $limit,
                'query' => array(
                    'searchFilters' => $analysis['searchFilters'],
                    'analysis' => $analysis['analysis'],
                    'processingTime' => microtime(true) - $this->requestStartTime
                ),
                'links' => $this->getLinks($limit, $offset)
            )
        );
    }
    
    /**
     * Return an array of request parameters formated for output url
     * 
     * @param {array} $params - input params
     * 
     */
    private function writeRequestParams($params) {

        $arr = array();

        foreach ($params as $key => $value) {

            /*
             * Support key tuples
             */
            if (is_array($value)) {
                for ($i = 0, $l = count($value); $i < $l; $i++) {
                    if (isset($this->defaultModel->searchFilters[$key]['osKey'])) {
                        $arr[$this->defaultModel->searchFilters[$key]['osKey'] . '[]'] = $value[$i];
                    }
                    else {
                        $arr[$key . '[]'] = $value;
                    }
                }
            }
            else {
                if (isset($this->defaultModel->searchFilters[$key]['osKey'])) {
                    $arr[$this->defaultModel->searchFilters[$key]['osKey']] = $value;
                }
                else {
                    $arr[$key] = $value;
                }
            }
        }
        
        return $arr;
    }
    
    /**
     * Set restoFeatures and collections array
     *
     * @param array $params
     * @param integer $limit
     * @param integer $offset
     * @param integer $realCount
     */
    private function loadFeatures($params, $limit, $offset) {
        
        /*
         * Convert productIdentifier to identifier if needed
         */
        if (isset($params['geo:uid']) && !RestoUtil::isValidUUID($params['geo:uid'])) {
            if (isset($this->defaultCollection)) {
                $params['geo:uid'] = RestoUtil::UUIDv5($this->defaultCollection->name . ':' . strtoupper($params['geo:uid']));
            }
        }
        
        /*
         * Get features array from database
         */
        $featuresArray = $this->context->dbDriver->get(RestoDatabaseDriver::FEATURES_DESCRIPTIONS, array(
            'context' => $this->context,
            'user' => $this->user,
            'collection' => isset($this->defaultCollection) ? $this->defaultCollection : null,
            'filters' => $params,
            'options' => array(
                'limit' => $limit,
                'offset' => $offset
            )
        ));

        /*
         * Load collections array
         */
        $postData = array();
        $storageInfos = array();
        for ($i = 0, $l = count($featuresArray['features']); $i < $l; $i++) {
            // If NRT, stoage mode is disk
            if (isset($featuresArray['features'][$i]['properties']['isNrt']) && $featuresArray['features'][$i]['properties']['isNrt'] == 1){
                $storageInfos[$featuresArray['features'][$i]['properties']['title']] = array('storage' => self::STORAGE_MODE_DISK);
                continue;
            }
            if (isset($featuresArray['features'][$i]['properties']['hpssResource'])) {
                $postData[] = $featuresArray['features'][$i]['properties']['hpssResource'];
                continue;
            }
            //$storageInfos[$featuresArray['features'][$i]['properties']['title']] = self::STORAGE_MODE_UNKNOWN;
        }
        if (!empty($postData)) {
            $postData = $this->getStorageInfo($postData);
        }
        $storageInfos = array_merge($storageInfos, $postData);
        
        for ($i = 0, $l = count($featuresArray['features']); $i < $l; $i++) {
            if (isset($this->collections) && !isset($this->collections[$featuresArray['features'][$i]['properties']['collection']])) {
                $this->collections[$featuresArray['features'][$i]['properties']['collection']] = new RestoCollection($featuresArray['features'][$i]['properties']['collection'], $this->context, $this->user, array('autoload' => true));
            }
            $name = $featuresArray['features'][$i]['properties']['title'];
            $featuresArray['features'][$i]['properties']['storage'] = array('mode' => isset($storageInfos[$name]['storage']) 
                    ? $storageInfos[$name]['storage'] : self::STORAGE_MODE_UNKNOWN);
            $feature = new RestoFeature($this->context, $this->user, array(
                'featureArray' => $featuresArray['features'][$i],
                'collection' => isset($this->collections) && isset($featuresArray['features'][$i]['properties']['collection']) && $this->collections[$featuresArray['features'][$i]['properties']['collection']] ? $this->collections[$featuresArray['features'][$i]['properties']['collection']] : $this->defaultCollection
            ));
            if (isset($feature)) {
                $this->restoFeatures[] = $feature;
            }
        }

        /*
         * Compute paging
         */
        $this->paging = $this->getPaging($featuresArray['count'], $limit, $offset);
    }

    /**
     * Clean input parameters
     *  - change parameter keys to model parameter key
     *  - remove unset parameters
     *  - remove all HTML tags from input to avoid XSS injection
     *  - check that filter value is valid regarding the model definition
     */
    private function getOriginalFilters() {
        $params = array();
        foreach ($this->context->query as $key => $value) {
            foreach (array_keys($this->defaultModel->searchFilters) as $filterKey) {
                if ($key === $this->defaultModel->searchFilters[$filterKey]['osKey']) {
                    $params[$filterKey] = preg_replace('/<.*?>/', '', $value);
                    $this->defaultModel->validateFilter($filterKey, $params[$filterKey]);
                }
            }
        }
        return $params;
    }
    
    /**
     * Search offset - first element starts at offset 0
     * Note: startPage has preseance over startIndex if both are specified in request
     * (see CEOS-BP-006 requirement of CEOS OpenSearch Best Practice document)
     *     
     * @param type $params
     */
    private function getOffset($params, $limit) {
        $offset = 0;
        if (isset($params['startPage']) && is_numeric($params['startPage']) && $params['startPage'] > 0) {
            $offset = (($params['startPage'] - 1) * $limit);
        }
        else if (isset($params['startIndex']) && is_numeric($params['startIndex']) && $params['startIndex'] > 0) {
            $offset = ($params['startIndex']) - 1;
        }
        return $offset;
    }
    
    /**
     * Get navigation links (i.e. next, previous, first, last)
     * 
     * @param integer $limit
     * @param integer $offset
     * @return array
     */
    private function getLinks($limit, $offset) {
        
        /*
         * Base links are always returned
         */
        $links = $this->getBaseLinks();

        /*
         * Start page cannot be lower than 1
         */
        if ($this->paging['startPage'] > 1) {
            
            /*
             * Previous URL is the previous URL from the self URL
             * 
             */
            $links[] = $this->getLink('previous', '_previousCollectionLink', array(
                'startPage' => max($this->paging['startPage'] - 1, 1),
                'count' => $limit));
            
            /*
             * First URL is the first search URL i.e. with startPage = 1
             */
            $links[] = $this->getLink('first', '_firstCollectionLink', array(
                'startPage' => 1,
                'count' => $limit)
            );
        }

        /*
         * Theorically, startPage cannot be greater than the one from lastURL
         * ...but since we use a count estimate it is not possible to know the
         * real last page. So always set a nextPage !
         */
        if (count($this->restoFeatures) >= $limit) {
            
            /*
             * Next URL is the next search URL from the self URL
             */
            $links[] = $this->getLink('next', '_nextCollectionLink', array(
                'startPage' => $this->paging['nextPage'],
                'count' => $limit)
            );
            
            /*
             * Last URL has the highest startIndex
             */
            $links[] = $this->getLink('last', '_lastCollectionLink', array(
                'startPage' => max($this->paging['totalPage'], 1),
                'count' => $limit)
            );
        }
        return $links;
    }
    
    /**
     * Return base links (i.e. links always present in response)
     */
    private function getBaseLinks() {
        return array(
            array(
                'rel' => 'self',
                'type' => RestoUtil::$contentTypes['json'],
                'title' => $this->context->dictionary->translate('_selfCollectionLink'),
                'href' => RestoUtil::updateUrl($this->context->getUrl(false), $this->writeRequestParams($this->context->query))
            ),
            array(
                'rel' => 'search',
                'type' => 'application/opensearchdescription+xml',
                'title' => $this->context->dictionary->translate('_osddLink'),
                'href' => $this->context->baseUrl . '/api/collections/' . (isset($this->defaultCollection) ? $this->defaultCollection->name . '/' : '') . 'describe.xml'
