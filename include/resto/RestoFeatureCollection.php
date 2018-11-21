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
    // TODO
            else {
                $this->defaultModel = new RestoModel_default();
            $this->collections = $collections;
         /*   reset($collections);
            $this->defaultCollection = $this->collections[key($collections)];
            $this->defaultModel = $this->defaultCollection->model;
            */
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
            ),
            'collections' => $this->collections
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
            )
        );
    }
    
    /**
     * Return Link
     * 
     * @param string $rel
     * @param string $title
     * @param array $params
     * @return array
     */
    private function getLink($rel, $title, $params) {
        
        /*
         * Do not set count if equal to default limit
         */
        if (isset($params['count']) && $params['count'] === $this->context->dbDriver->resultsPerPage) {
            unset($params['count']);
        }
            
        return array(
            'rel' => $rel,
            'type' => RestoUtil::$contentTypes['json'],
            'title' => $this->context->dictionary->translate($title),
            'href' => RestoUtil::updateUrl($this->context->getUrl(false), $this->writeRequestParams(array_merge($this->context->query, $params)))
        );
    }
    
    /**
     * Get start, next and last page from limit and offset
     *
     * @param array $count
     * @param integer $limit
     * @param integer $offset
     */
    private function getPaging($count, $limit, $offset) {
        /*
         * If first page contains no features count must be 0 not estimated value
         */
        if ($offset == 0 && count($this->restoFeatures) == 0){
            $count = array(
                'total' => 0,
                'isExact' => true
            );
        }

        /*
         * Default paging
         */
        $paging = array(
            'count' => $count,
            'startPage' => 1,
            'nextPage' => 1,
            'totalPage' => 0
        );
        if (count($this->restoFeatures) > 0) {

            $startPage = ceil(($offset + 1) / $limit);

            /*
             * Tricky part if count is estimate, then
             * the total count is the maximum between the database estimate
             * and the pseudo real count based on the retrieved features count
             */
            if (!$count['isExact']) {
                $count['total'] = max(count($this->restoFeatures) + (($startPage - 1) * $limit), $count['total']);
            }
            $totalPage = ceil($count['total'] / $limit);
            $paging = array(
                'count' => $count,
                'startPage' => $startPage,
                'nextPage' => $startPage + 1,
                'totalPage' => $totalPage
            );
        }

        return $paging;
    }

    /**
     * Return query array from search filters
     * 
     * @param array $searchFilters
     * @return array
     */
    private function cleanFilters($searchFilters) {
        $query = array();
        $exclude = array(
            'count',
            'startIndex',
            'startPage'
        );
        foreach ($searchFilters as $key => $value) {
            if (in_array($key, $exclude)) {
                continue;
            }
            $query[$key] = $key === 'searchTerms' ? stripslashes($value) : $value;
        }
        ksort($query);
        return $query;
    }
    
    /**
     * Get ATOM subtitle - construct from $this->description['properties']['title']
     * 
     * @return string
     */
    private function getATOMSubtitle() {
        $subtitle = '';
        if (isset($this->description['properties']['totalResults'])) {
            $subtitle = $this->context->dictionary->translate($this->description['properties']['totalResults'] === 1 ? '_oneResult' : '_multipleResult', $this->description['properties']['totalResults']);
        }
        $previous = isset($this->description['properties']['links']['previous']) ? '<a href="' . RestoUtil::updateUrlFormat($this->description['properties']['links']['previous'], 'atom') . '">' . $this->context->dictionary->translate('_previousPage') . '</a>&nbsp;' : '';
        $next = isset($this->description['properties']['links']['next']) ? '&nbsp;<a href="' . RestoUtil::updateUrlFormat($this->description['properties']['links']['next'], 'atom') . '">' . $this->context->dictionary->translate('_nextPage') . '</a>' : '';
        $subtitle .= isset($this->description['properties']['startIndex']) ? '&nbsp;|&nbsp;' . $previous . $this->context->dictionary->translate('_pagination', $this->description['properties']['startIndex'], $this->description['properties']['startIndex'] + 1) . $next : '';
        return $subtitle;
    }
    
    /**
     * Analyse searchTerms
     * 
     * @param array $params
     */
    private function analyze($params) {
        
        /*
         * No searchTerms specify - leave input search filters untouched
         */
        if (empty($params['searchTerms'])) {
            return array(
                'searchFilters' => $params,
                'analysis' => array(
                    'query' => ''
                )
            );
        }
        
        /*
         * Analyse query
         */
        $analysis = $this->queryAnalyzer->analyze($params['searchTerms']);
        
        /*
         * Language
         */
        $language = $analysis['language'];
                
        /*
         * Not understood - return error
         */
        
        if (empty($analysis['analyze']->{$language})||(empty($analysis['analyze']->{$language}->{'what'}) && empty($analysis['analyze']->{$language}->{'when'}) && empty($analysis['analyze']->{$language}->{'where'}))) {
            return array(
                'notUnderstood' => true,
                'searchFilters' => $params,
                'analysis' => $analysis
            );
        }
                
        /*
         * What
         */
        $params = $this->setWhatFilters($analysis['analyze']->{$language}->{'what'}, $params);
        
        /*
         * When
         */
       $params = $this->setWhenFilters($analysis['analyze']->{$language}->{'when'}, $params);
             
        /*
         * Where
         */
       $params = $this->setWhereFilters($analysis['analyze']->{$language}->{'where'}, $params);
       
       
        return array(
            'searchFilters' => $params,
            'analysis' => $analysis
        );
    }
    
    /**
     * Set what filters from query analysis
     * 
     * @param array $what
     * @param array $params
     * @param array $conversions
     */
    private function setWhatFilters($what, $params) {
        $params['searchTerms'] = array();
        $conversions = $this->queryAnalyzer->getConversions();
        
        foreach($what as $key => $value) {
            if ($key === 'searchTerms') {
                for ($i = count($value); $i--;) {
                    $params['searchTerms'][] = $value[$i]->{'value'};
                }
            } else if ($key === 'collection') {
                for ($i = count($value); $i--;) {
                    $this->defaultCollection = $this->collections[$value[$i]->{'value'}] ;
                    $params['collection'] = $value[$i]->{'value'};
                }
              
            } else {
                $newKey = isset($conversions[$key]) ? $conversions[$key] : $key ;
                for ($i = count($value); $i--;) {   
                    $params[$newKey][] = is_array($value[$i]->{'value'}) ? json_encode($value[$i]->{'value'}) : $value[$i]->{'value'};
                }
                $params[$newKey] = join('|', $params[$newKey]);
            }
        }
        
        return $params;
    }
    
    /**
     * Set when filters from query analysis
     * 
     * @param array $when
     * @param array $params
     */
    private function setWhenFilters($when, $params) {
        foreach($when as $whenItem) {
            
            /*
             * times is an array of time:start/time:end pairs
             * TODO : Currently only one pair is supported
             */
            if ($whenItem->{'time'}->{'operator'} === 'in') {
                $params = array_merge($params, $this->timesToOpenSearch($whenItem->{'time'}->{'intervals'}));
            }
        }
        return $params;
    }
    
    /**
     * 
     * @param array $times
     */
    private function timesToOpenSearch($times) { 
    
        $params = array();
        for ($i = 0, $ii = count($times); $i < $ii; $i++) {
            foreach($times[$i] as $key => $value) {
                if($key === 'end') {
                    $date = DateTime::createFromFormat('Y-m-d', $value);
                    $date->setTime(23,59,59);
                    $params['time:end'] = $date->format("Y-m-d\TH:i:s\Z");
                }
                if($key === 'start') {
                    $params['time:start'] = date("Y-m-d\TH:i:s\Z", strtotime($value));
                }
            }
        }
        return $params;
    }
    
    /**
     * Set location filters from query analysis
     * 
     * @param array $where
     * @param array $params
     */
    private function setWhereFilters($where, $params) {
        for ($i = count($where); $i--;) {
            
            /*
             * Geometry
             */
            if($where[$i]->{'geo'}->{'type'}==='Point') {
                $params['geo:lon'] = $where[$i]->{'geo'}->{'coordinates'}[0];
                $params['geo:lat'] = $where[$i]->{'geo'}->{'coordinates'}[1];
            }
            /*
             * Geometry
             */
            else {
            $params['resto:geometry'] = $where[$i]->{'geo'};
            }
        }
        $params['searchTerms'] = join(' ', $params['searchTerms']);
        return $params;
    }

    /**
     * Return storage information of specified data
     * @param array $data data
     * @param number $timeout timeout
     * @return array storage information of inputs data
     */
    private function getStorageInfo($data, $timeout=30) {
        
        $result = array();
        /*
         * Storage informations
         */
        if (isset($data) && !empty($this->context->hpssRestApi['getStorageInfo'])){
            $curl = curl_init($this->context->hpssRestApi['getStorageInfo']);
            $headers = array("Content-type: text/plain");
            curl_setopt_array($curl, array (
                    CURLOPT_RETURNTRANSFER => 1,
                    CURLOPT_POST => 1,
                    CURLOPT_HTTPHEADER => $headers,
                    CURLOPT_POSTFIELDS => implode(' ', $data),
                    CURLOPT_SSL_VERIFYHOST => 0,
                    CURLOPT_SSL_VERIFYPEER => 0,
                    CURLOPT_TIMEOUT => $timeout
            ));
            
            // Perform request
            $response = curl_exec($curl);
            $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            if ($response && $httpcode === 200){
                $result = json_decode($response, true);
            }

            if(curl_errno($curl)){
                $error = curl_error($curl);
                error_log($error, 0);
            }
            curl_close($curl);
        }
        return $result;        
    }
}

