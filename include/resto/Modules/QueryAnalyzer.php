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
 * QueryAnalyzer module
 * 
 * Extract OpenSearch EO search parameters from
 * an input string (i.e. searchTerms)
 * A typical searchTerms query can be anything :
 * 
 *      searchTerms = "spot5 images with forest in france between march 2012 and may 2012"
 * 
 * The query analyzer converts this string into comprehensive request.
 * 
 * For instance the previous string will be transformed as :
 *  
 *      eo:platform = SPOT5
 *      time:start = 2012-01-03T00:00:00Z
 *      time:end = 2012-31-05T00:00:00Z
 *      geo:box = POLYGON(( ...coordinates of France country...))
 *      searchTerms = landuse:forest
 * 
 * IMPORTANT : if a word is prefixed by 'xxx=' then QueryAnalyzer considered the string as a key=value pair
 * 
 * Some notes :
 *
 * # Dates
 * 
 * Detected dates format are :
 *      
 *      ISO8601 : see isISO8601($str) in lib/functions.php (e.g 2010-10-23)
 *      <month> <year> (e.g. may 2010)
 *      <year> <month> (e.g. 2010 may)
 *      <day> <month> <year> (e.g. 10 may 2010)
 *      <year> <month> <day> (e.g. 2010 may 10)
 * 
 * # Detected patterns
 * 
 * ## When ?
 * 
 *      <today>
 *      <tomorrow>
 *      <yesterday>
 * 
 *      <after> "date"
 *      <before> "date"
 *      
 *      <between> "date" <and> "date"
 *      <between> "month" <and> "month" (year)
 *      <between> "day" <and> "day" (month) (year)
 *      
 *      <in> "date"
 * 
 *      <last> "(year|month|day)"
 *      <last> "numeric" "(year|month|day)"
 *      "numeric" <last> "(year|month|day)"
 *      "(year|month|day)" <last>
 * 
 *      <next> "(year|month|day)"
 *      <next> "numeric" "(year|month|day)"
 *      "numeric" <next> "(year|month|day)"
 *      "(year|month|day)" <next>
 * 
 *      <since> "numeric" "(year|month|day)"
 *      <since> "month" "year"
 *      <since> "date"
 *      <since> "numeric" <last> "(year|month|day)"
 *      <since> <last> "numeric" "(year|month|day)"
 *      <since> <last> "(year|month|day)"
 *      <since> "(year|month|day)" <last>
 * 
 *      "numeric" "(year|month|day)" <ago>
 * 
 * 
 * A 'modifier' is a term which modify the way following term(s) are handled.
 * Known <modifier> and expected "terms" are :
 * 
 *      <with> "keyword"
 *      <with> "quantity"   // equivalent to "quantity" <greater> (than) 0 "unit"
 * 
 *      <without> "keyword"
 *  
 *      <without> "quantity"   // equivalent to "quantity" <equal> 0 "unit"
 * 
 *      "quantity" <lesser> (than) "numeric" "unit"
 *      "quantity" <greater> (than) "numeric" "unit"
 *      "quantity" <equal> (to) "numeric" "unit"
 *      <lesser> (than) "numeric" "unit" (of) "quantity" 
 *      <greater> (than) "numeric" "unit" (of) "quantity"
 *      <equal> (to) "numeric" "unit" (of) "quantity"
 * 
 *      
 *     
 *      <month>
 *      <season>
 * 
 * @param array $params
 */
class QueryAnalyzer extends RestoModule {

    /*
     * Error messages
     */
    const INVALID_UNIT = 'INVALID_UNIT';
    const LOCATION_NOT_FOUND = 'LOCATION_NOT_FOUND';
    const MISSING_ARGUMENT = 'MISSING_ARGUMENT';
    const MISSING_UNIT = 'MISSING_UNIT';
    const NOT_UNDERSTOOD = 'NOT_UNDERSTOOD';
       
    /**
     * Constructor
     * 
     * @param RestoContext $context
     * @param RestoUser $user
     * @param RestoModel $model
     */
    public function __construct($context, $user, $model = null) {
        parent::__construct($context, $user);
    }

    /**
     * Run module - this function should be called by Resto.php
     * 
     * @param array $elements : route element
     * @param array $data : POST or PUT parameters
     * 
     * @return string : result from run process in the $context->outputFormat
     */
    public function run($elements, $data = array()) {
        
        /*
         * Only GET method on 'search' route with json outputformat is accepted
         */
        if ($this->context->method !== 'GET' || count($elements) !== 0) {
            RestoLogUtil::httpError(404);
        }
        $query = isset($this->context->query['searchTerms']) ? $this->context->query['searchTerms'] : (isset($this->context->query['q']) ? $this->context->query['q'] : null);
        
        return $this->analyze($query);
        
    }
  
       /**
        * Function to analyze a query in natural language
        * 
        * @param string $query
        * @return array containing the query, the language, the analyze and the processing time
        */
    public function analyze($query) {
        $startTime = microtime(true);
        
        if (!isset($query)) {
            RestoLogUtil::httpError(400, 'Missing mandatory searchTerms');
        }
        
        
        $result = $this->executeQuery($query);
        $analyses =json_decode($result, false)->{'analyses'};
        
        
        return array(
            'query' => $query,
            'language' => $this->getlanguage($analyses),
            'analyze' => $analyses,
            'processingTime' => microtime(true) - $startTime
        );
    }
    
    /**
     * Execute Http query =>API for peps semantic analyze
     * 
     * @param string $query query in natural language
     * @return string body result of the request
     */
    private function executeQuery($query) {
        
        $data = array();
        $data['q'] = urlencode($query);
        $data['start_year'] = isset($this->options['start_year']) ? $this->options['start_year'] : "2017" ;
        
        $options = isset($this->options['curlOpts']) ? $this->options['curlOpts'] : array() ;
        
        $url = isset($this->options['analysis_route']) ? $this->options['analysis_route'] : "";
        
        $response = Curl::Get($url, $data, $options);
        
        if (!isset($response)) {
            RestoLogUtil::httpError(500, 'ERROR when querying the API for peps semantic analyze');
        }
        
        return $response;
    }
    
    /**
     * Return the main language of the query
     * If two languages are detected, the dictionary language (of the context) is used
     * 
     * @param array() $analyses semantic analysis
     * @return string language
     */
    private function getlanguage($analyses) {
        $language = $this->context->dictionary->language;
        if(count($analyses) === 1) {
            $language = key($analyses);
        }
        return $language;
    }
    
    
    /**
     * Return conversions between query analyzer property keys and Resto model property keys
     * 
     * @return array
     */
    public function getConversions() {
        return isset($this->options['conversions']) ? $this->options['conversions'] : array();
    }
    
}