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
 * Tag module
 * 
 * This module compute tags from feature.
 * 
 * It requires the iTag library (https://github.com/jjrom/itag)
 * 
 */
class Tag extends RestoModule {

    /**
     * Constructor
     * 
     * @param RestoContext $context
     * @param RestoContext $user
     */
    public function __construct($context, $user) {
        parent::__construct($context, $user);
    }

    /**
     * Run module - this function should be called by Resto.php
     * 
     * @param array $segments : route segments
     * @param array $data : POST or PUT parameters
     * 
     * @return string : result from run process in the $context->outputFormat
     */
    public function run($segments, $data = array()) {
        
        /*
         * Only autenticated user.
         */
        if ($this->user->profile['userid'] == -1) {
            RestoLogUtil::httpError(401);
        }

        /*
         * Only administrators can access to administration
         */
        if (!$this->user->isAdmin()) {
            RestoLogUtil::httpError(403);
        }

        /*
         * Switch on HTTP methods
         */
        switch ($this->context->method) {
            case 'PUT':
                return $this->processPUT($segments, $data);
            default:
                RestoLogUtil::httpError(404);
        }

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
     * Refresh tags for feature
     * 
     * @param RestoFeature $feature
     */
    public function refresh($feature) {
        $featureArray = $feature->toArray();
        $this->context->dbDriver->update(RestoDatabaseDriver::KEYWORDS, array(
            'feature' => $feature,
            'keywords' => $this->getKeywords($featureArray['properties'], $featureArray['geometry'], $feature->collection)
        ));
    }

    /**
     *
     * Process HTTP PUT request on users
     *
     *      {featureid}   
     *
     * @param array $segments
     * @param array $data
     */
    private function processPUT($segments, $data) {

        /*
         * Check route pattern
         */
        if (!isset($segments[1]) || isset($segments[2])) {
            RestoLogUtil::httpError(404);
        }
        
        /*
         * First segment is the feature identifier
         */
        $feature = new RestoFeature($this->context, $this->user, array(
            'featureIdentifier' => $segments[0]
        ));
        if (!isset($feature)) {
            RestoLogUtil::httpError(404, 'Feature does not exist');
        }
        
        /*
         * Second segment is the action
         */
        switch ($segments[1]) {
            case 'refresh':
                $this->refresh($feature, $data);
                return RestoLogUtil::success('Recompute keywords for feature ' . $feature->identifier);
            default:
                RestoLogUtil::httpError(404);
        }
    }    
}