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
 * RESTo Database
 */
abstract class RestoDatabaseDriver {
    
    /*
     * Constant
     */
    const ACTIVATE_USER = 1;
    const CART_ITEM = 2;
    const CART_ITEMS = 3;
    const COLLECTION = 4;
    const COLLECTIONS = 5;
    const COLLECTIONS_DESCRIPTIONS = 6;
    const DEACTIVATE_USER = 7;
    const DISCONNECT_USER = 8;
    const FACET = 9;
    const FACETS = 10;
    const FEATURE = 11;
    const FEATURES = 12;
    const FEATURES_DESCRIPTIONS = 13;
    const FEATURE_DESCRIPTION = 14;
    const GROUPS = 15;
    const HANDLER = 16;
    const KEYWORDS = 17;
    const LICENSE_SIGNED = 18;
    const ORDER = 19;
    const ORDERS = 20;
    const QUERY = 21;
    const RIGHTS = 22;
    const RIGHTS_FULL = 23;
    const SCHEMA = 24;
    const SHARED_LINK = 25;
    const SIGN_LICENSE = 26;
    const STATISTICS = 27;
    const TABLE = 28;
    const TABLE_EMPTY = 29;
    const TOKEN_REVOKED = 30;
    const USER = 31;
    const USER_PASSWORD = 32;
    const USER_PROFILE = 33;
    const USER_LIMIT = 34;
    const ORDER_SIZE = 35;
    const GROUP_DESCRIPTIONS = 36;
    const USER_DOWNLOADED_WEEKLY = 37;
    const FEATURE_DESCRIPTION_BY_TITLE = 38;
    const FEATURE_FACETS = 39;
    const FEATURE_ALL_VERSIONS = 40;
    const FEATURE_VERSION = 41;
    const FEATURE_S1_REALTIME = 42;
    const SIMPLIFY_GEOMETRY = 43;
    const PROCESSING_CART_ITEM = 44;
    const PROCESSING_CART_ITEMS = 45;
    const PROCESSING_JOBS_ITEM = 46;
    const PROCESSING_JOBS_ITEMS = 47;
    const PROCESSING_JOBS_STATS = 48;
    const PROCESSING_JOBS_DATA = 49;
    const PROCESSING_JOBS_CHECK = 50;
    const PROACTIVE = 51;
    const PROACTIVE_ACCOUNTS = 52;
    const PROACTIVE_ACCOUNT = 53;
    const WPS_RIGHTS = 54;
    const WPS_GROUP_RIGHTS = 55;
    const WPS_GROUPS = 56;
    const GROUP = 57;
    
    
    /*
     * Results per page
     */
    public $resultsPerPage = 20;

    /*
     * Cache object
     */
    public $cache = null;
    
    /*
     * Database handler
     */
    public $dbh;
    
    /**
     * Constructor
     * 
     * @param array $config
     * @param RestoCache $cache
     * @throws Exception
     */
    public function __construct($config, $cache) {
        $this->cache = isset($cache) ? $cache : new RestoCache(null);
    } 
    
    /**
     * List object by type name
     * 
     * @return array
     * @throws Exception
     */
    abstract public function get($typeName);
    
    /**
     * Check if $typeName constraint is true
     * 
     * @param string $typeName - object type name ('collection', 'feature', 'user')
     * @param array $params
     * @return boolean
     * @throws Exception
     */
    abstract public function check($typeName, $params);

    /**
     * Execute action
     * 
     * @param string $typeName - object type name ('collection', 'feature', 'user')
     * @param array $params
     * @return boolean
     * @throws Exception
     */
    abstract public function execute($typeName, $params);
    
    /**
     * Return normalized $sentence i.e. in lowercase and without accents
     * This function is superseed in RestoDabaseDriver_PostgreSQL and use
     * the inner function normalize($sentence) defined in installDB.sh
     * 
     * @param string $sentence
     */
    abstract public function normalize($sentence);
    
    /**
     * Remove object from database
     * 
     * @param Object $object
     */
    abstract public function remove($object);

    /**
     * Store object within database
     * 
     * @param string $typeName
     * @param array $params
     * @throws Exception
     */
    abstract public function store($typeName, $params);

    /**
     * Update object within database
     * 
     * @param string $typeName
     * @param array $params
     * @throws Exception
     */
    abstract public function update($typeName, $params);
    
    /**
     * Close database handler
     */
    abstract public function closeDbh();

    /**
     * Quotes a string for use in a query.
     * Places quotes around the input string (if required) and escapes special characters
     *
     * @param string $string
     */
    public function quote($input, $default=null){
        return isset($input) ? '\'' . pg_escape_string($input) . '\'' : $default;
    }
}
