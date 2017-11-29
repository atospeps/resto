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

/*
 * Required includes
 */
require 'PostgreSQL/Functions_general.php';
require 'PostgreSQL/Functions_cart.php';
require 'PostgreSQL/Functions_processingcart.php';
require 'PostgreSQL/Functions_jobs.php';
require 'PostgreSQL/Functions_collections.php';
require 'PostgreSQL/Functions_facets.php';
require 'PostgreSQL/Functions_features.php';
require 'PostgreSQL/Functions_filters.php';
require 'PostgreSQL/Functions_rights.php';
require 'PostgreSQL/Functions_users.php';
require 'PostgreSQL/Functions_groups.php';
require 'PostgreSQL/Functions_proactive.php';
require 'PostgreSQL/Functions_wpsrights.php';
require 'PostgreSQL/Functions_geometry.php';

/**
 * RESTo PostgreSQL Database
 */
class RestoDatabaseDriver_PostgreSQL extends RestoDatabaseDriver {
    
    /*
     * Facet Util reference
     */
    public $facetUtil;
    
    /**
     * Constructor
     * 
     * @param array $config
     * @param RestoCache $cache
     * @throws Exception
     */
    public function __construct($config, $cache) {
        
        parent::__construct($config, $cache);
        
        $this->dbh = $this->getHandler($config);
        
        $this->facetUtil = new RestoFacetUtil();
        
        if (isset($config['resultsPerPage'])) {
            $this->resultsPerPage = $config['resultsPerPage'];
        }
        
    }
    
    /**
     * Get object by typename
     * 
     * @param string $typeName
     * @param array $params
     * @return type
     */
    public function get($typeName, $params = array())
    {
        switch ($typeName) {
            
            /*
             * Get Database Handler
             */
            case parent::HANDLER:
                return $this->getHandler($params);
            
            /*
             * Get cart items
             */
            case parent::CART_ITEMS:
                $cartFunctions = new Functions_cart($this);
                return $cartFunctions->getCartItems($params['email']);
            
            /*
             * Get processing cart items
             */
            case parent::PROCESSING_CART_ITEMS:
                $processingCartFunctions = new Functions_processingcart($this);
                return $processingCartFunctions->getProcessingCartItems($params['context'], $params['user']);
            
            /*
             * Get processing item
             */
            case parent::PROCESSING_JOBS_ITEM:
                $jobsFunctions = new Functions_jobs($this);
                return $jobsFunctions->get($params['userid'], $params['jobid']);

            /*
             * Get processing items
             */
            case parent::PROCESSING_JOBS_ITEMS:
                $jobsFunctions = new Functions_jobs($this);
                return $jobsFunctions->get($params['userid'], null, isset($params['filters']) ? $params['filters'] : null);
                
            /*
             * Get processing items
             */
            case parent::PROCESSING_JOBS_CHECK:
                $jobsFunctions = new Functions_jobs($this);
                return $jobsFunctions->get(-1, null, $params['filters']);
                
            /*
             * Get processing items
             */
            case parent::PROCESSING_JOBS_STATS:
                $jobsFunctions = new Functions_jobs($this);
                return $jobsFunctions->getStats($params['userid']);
            
            
            /*
             * Get collections descriptions
             */
            case parent::COLLECTIONS_DESCRIPTIONS:
                $collectionsFunctions = new Functions_collections($this);
                return $collectionsFunctions->getCollectionsDescriptions(isset($params['collectionName']) ? $params['collectionName'] : null);
            
            /*
             * Get feature description
             */
            case parent::FEATURE_DESCRIPTION:
                $featuresFunctions = new Functions_features($this);
                return $featuresFunctions->getFeatureDescription($params['context'], $params['user'], $params['featureIdentifier'], isset($params['collection']) ? $params['collection'] : null, isset($params['filters']) ? $params['filters'] : array());
            /*
             * Get feature description by id
             */
            case parent::FEATURE_DESCRIPTION_BY_TITLE:
                $featuresFunctions = new Functions_features($this);
                return $featuresFunctions->getFeatureDescriptionByTitle($params['context'], $params['user'], $params['featureTitle'], isset($params['collection']) ? $params['collection'] : null, isset($params['filters']) ? $params['filters'] : array());
            /*
             * Get all versions of a product
             */
            case parent::FEATURE_ALL_VERSIONS:
                $featuresFunctions = new Functions_features($this);
                return $featuresFunctions->getAllVersions($params['context'], $params['user'], $params['productIdentifier'], $params['dhusIngestDate'], $params['collection'], $params['pattern']);

            /*
             * Get feature collections description
             */
            case parent::FEATURES_DESCRIPTIONS:
                $featuresFunctions = new Functions_features($this);
                return $featuresFunctions->search($params['context'], $params['user'], $params['collection'], $params['filters'], $params['options']);                

            /*
             * Get Keywords
             */
            case parent::KEYWORDS:
                $generalFunctions = new Functions_general($this);
                return $generalFunctions->getKeywords($params['language'], isset($params['types']) ? $params['types'] : array());
            
            /*
             * Get orders
             */    
            case parent::ORDERS:
                $cartFunctions = new Functions_cart($this);
                return $cartFunctions->getOrders($params['email'], isset($params['orderId']) ? $params['orderId'] : null);
            
            /*
             * Get order size
             */    
            case parent::ORDER_SIZE:
                $cartFunctions = new Functions_cart($this);
                return $cartFunctions->getOrderSize($params['order']);
                
            /*
             * Get rights
             */
            case parent::RIGHTS:
                $rightsFunctions = new Functions_rights($this);
                return $rightsFunctions->getRights($params['emailOrGroup'], isset($params['collectionName']) ? $params['collectionName'] : null, isset($params['featureIdentifier']) ? $params['featureIdentifier'] : null);
            
            /*
             * Get rights
             */
            case parent::RIGHTS_FULL:
                $rightsFunctions = new Functions_rights($this);
                return $rightsFunctions->getFullRights($params['emailOrGroup'], isset($params['collectionName']) ? $params['collectionName'] : null, isset($params['featureIdentifier']) ? $params['featureIdentifier'] : null);
            
            /*
             * Get statistics
             */
            case parent::STATISTICS:
                $facetsFunctions = new Functions_facets($this);
                return $facetsFunctions->getStatistics($params['collectionName'], $params['facetFields']);
            
            /*
             * Get statistics
             */
            case parent::SHARED_LINK:
                $generalFunctions = new Functions_general($this);
                return $generalFunctions->createSharedLink($params['resourceUrl'], $params['email'], $params['duration']);
            
            /*
             * Get encrypted user password
             */
            case parent::USER_PASSWORD:
                $usersFunctions = new Functions_users($this);
                return $usersFunctions->getUserPassword($params['email']);
            
            /*
             * Get user profile
             */
            case parent::USER_PROFILE:
                $usersFunctions = new Functions_users($this);
                return $usersFunctions->getUserProfile(isset($params['email']) ? $params['email'] : $params['userid'], isset($params['password']) ? $params['password'] : null);

            /*
             * Get groups
             */
            case parent::GROUPS:
                $groupsFunctions = new Functions_groups($this);
                return $groupsFunctions->getGroups();
                
            /*
             * Get group
             */
            case parent::GROUP:
                $groupsFunctions = new Functions_groups($this);
                return $groupsFunctions->getGroup($params['gidOrGroupName']);
                
            /*
             * Get group description
             */
            case parent::GROUP_DESCRIPTIONS:
                $groupsFunctions = new Functions_groups($this);
                return $groupsFunctions->getGroup($params['id']);
                
            /*
             * Get groups with WPS enable
             */
            case parent::WPS_GROUPS:
                $groupsFunctions = new Functions_groups($this);
                return $groupsFunctions->getGroups(true);
                
            /*
             * Get WPS rights
             */
            case parent::WPS_GROUP_RIGHTS:
                $wpsRightsFunctions = new Functions_wpsrights($this);
                return $wpsRightsFunctions->getWpsGroupRights($params['groupid']);
                
            /*
             * Get Proactive accounts
             */
            case parent::PROACTIVE_ACCOUNTS:
                $proactiveFunctions = new Functions_proactive($this);
                return $proactiveFunctions->getAccounts();
                
            /*
             * Get Proactive account
             */
            case parent::PROACTIVE_ACCOUNT:
                $proactiveFunctions = new Functions_proactive($this);
                return $proactiveFunctions->getAccount($params['id']);
                
            /*
             * Get user downloaded volume the last 7 days 
             */
            case parent::USER_DOWNLOADED_WEEKLY:
                $usersFunctions = new Functions_users($this);
                return $usersFunctions->getUserLastWeekDownloaded($params['identifier']);
            
            default:
                return null;
        }
    }
    
    
    /**
     * Execute action
     * 
     * @param string $typeName
     * @param array $params
     * @return type
     */
    public function execute($typeName, $params = array()) {
        switch ($typeName) {
                 
            /*
             * Activate user
             */
            case parent::ACTIVATE_USER:
                $usersFunctions = new Functions_users($this);
                return $usersFunctions->activateUser($params['userid'], $params['activationCode']);
            
            /*
             * Deactivate user
             */
            case parent::DEACTIVATE_USER:
                $usersFunctions = new Functions_users($this);
                return $usersFunctions->deactivateUser($params['userid']);
            
            /*
             * Deactivate user
             */
            case parent::DISCONNECT_USER:
                $usersFunctions = new Functions_users($this);
                return $usersFunctions->revokeToken($params['token']);

            /*
             * Simplify WKT polygon 
             */
            case parent::SIMPLIFY_GEOMETRY:
                $geometryFunctions = new Functions_geometry($this);
                return $geometryFunctions->simplifyPolygon($params['wkt']);
                
            default:
                return null;
        }
    }
    
    
    /**
     * Return true if object exist
     * 
     * @param string $typeName
     * @param array $params
     * @return type
     */
    public function check($typeName, $params = array()) {
        switch ($typeName) {
            
            /*
             * True if collection exists
             */
            case parent::COLLECTION:
                $collectionsFunctions = new Functions_collections($this);
                return $collectionsFunctions->collectionExists($params['collectionName']);
            
            /*
             * True if feature exists
             */
            case parent::FEATURE:
                $featuresFunctions = new Functions_features($this);
                return $featuresFunctions->featureExists($params['featureIdentifier'], isset($params['schema']) ? $params['schema'] : null);
            
            /*
             * Check if we get multiples products identifier or realtime.
             */
            case parent::FEATURE_S1_REALTIME:
                $featuresFunctions = new Functions_features($this);
                return $featuresFunctions->checkRealtimeExists($params['collectionName'], $params['realtime'], $params['pattern']);
            
            /*
             * True if user is item is in cart
             */
            case parent::CART_ITEM:
                $cartFunctions = new Functions_cart($this);
                return $cartFunctions->isInCart($params['itemId']);
            
            /*
             * True if user is item is in cart
             */
            case parent::PROCESSING_CART_ITEM:
                $cartFunctions = new Functions_processingcart($this);
                return $cartFunctions->isInProcessingCart($params['itemId']);
            
            /*
             * True if user is license is signed
             */
            case parent::LICENSE_SIGNED:
                $usersFunctions = new Functions_users($this);
                return $usersFunctions->isLicenseSigned($params['email'], $params['collectionName']);
                
            /*
             * True if schema exists
             */
            case parent::SCHEMA:
                $generalFunctions = new Functions_general($this);
                return $generalFunctions->schemaExists($params['name']);
                
            /*
             * True if shared link is valid
             */
            case parent::SHARED_LINK:
                $generalFunctions = new Functions_general($this);
                return $generalFunctions->isValidSharedLink($params['resourceUrl'], $params['token']);
                
            /*
             * True if table exists
             */
            case parent::TABLE:
                $generalFunctions = new Functions_general($this);
                return $generalFunctions->tableExists($params['name'], isset($params['schema']) ? $params['schema'] : 'public');
                
            /*
             * True if table is empty
             */
            case parent::TABLE_EMPTY:
                $generalFunctions = new Functions_general($this);
                return $generalFunctions->tableIsEmpty($params['name'], isset($params['schema']) ? $params['schema'] : 'public');
            
            /*
             * True if user is license is signed
             */
            case parent::TOKEN_REVOKED:
                $usersFunctions = new Functions_users($this);
                return $usersFunctions->isTokenRevoked($params['token']);
                
            /*
             * True if user exists
             */
            case parent::USER:
                $usersFunctions = new Functions_users($this);
                return $usersFunctions->userExists($params['email']);
            
        	/*
        	 * True if the group exist
        	 */
            case parent::GROUPS:
                $groupsFunctions = new Functions_groups($this);
                return $groupsFunctions->isGroupExists($params['groupname']);

            /*
             * True if the password is correct
             */
            case parent::USER_PASSWORD:
                $usersFunctions = new Functions_users($this);
                return $usersFunctions->checkPassword($params['id'], $params['password']);
                
            default:
                return null;
        }
    }
    
    /**
     * Remove object
     * 
     * @param string $typeName
     * @param array $params
     * @return type
     */
    public function remove($typeName, $params = array())
    {
        switch ($typeName) {
            
            /*
             * Remove collection
             */
            case parent::COLLECTION:
                $collectionsFunctions = new Functions_collections($this);
                return $collectionsFunctions->removeCollection($params['collection']);

            /*
             * Remove facet
             */
            case parent::FACET:
                $facetsFunctions = new Functions_facets($this);
                return $facetsFunctions->removeFacet($params['hash'], $params['collectionName']);
                
            /*
             * Remove all facets
             */
            case parent::FACETS:
                $facetsFunctions = new Functions_facets($this);
                return $facetsFunctions->removeAllFacets();
                    
            /*
             * Remove feature
             */
            case parent::FEATURE:
                $featuresFunctions = new Functions_features($this);
                return $featuresFunctions->removeFeature($params['feature']);
            
            /*
             * Remove cart item
             */
            case parent::CART_ITEM:
                $cartFunctions = new Functions_cart($this);
                return $cartFunctions->removeFromCart($params['email'], $params['itemId']);
            
            /*
             * Remove all cart items
             */
            case parent::CART_ITEMS:
                $cartFunctions = new Functions_cart($this);
                return $cartFunctions->clearCart($params['email']);
            
            /*
             * Remove processing cart item
             */
            case parent::PROCESSING_CART_ITEM:
                $cartFunctions = new Functions_processingcart($this);
                return $cartFunctions->removeFromProcessingCart($params['userid'], $params['itemId']);
            
            /*
             * Remove all processing cart items
             */
            case parent::PROCESSING_CART_ITEMS:
                $cartFunctions = new Functions_processingcart($this);
                return $cartFunctions->clearProcessingCart($params['userid']);

            /*
             * Remove processing item
             */
            case parent::PROCESSING_JOBS_ITEM:
                $jobsFunctions = new Functions_jobs($this);
                return $jobsFunctions->remove($params['userid'], $params['jobid']);
            
            /*
             * Remove processing item
             */
            case parent::PROCESSING_JOBS_DATA:
                $jobsFunctions = new Functions_jobs($this);
                return $jobsFunctions->delete($params['userid'], $params['jobid']);

            /*
             * Remove all processing items
             */
            case parent::PROCESSING_JOBS_ITEMS:
                $jobsFunctions = new Functions_jobs($this);
                return $jobsFunctions->clear($params['userid']);
                
            /*
             * Remove collection/feature rights for user
             */
            case parent::RIGHTS:
                $rightsFunctions = new Functions_rights($this);
                return $rightsFunctions->deleteRights($params['emailOrGroup'], $params['collectionName'],  $params['featureIdentifier']);

            /*
             * Remove group
             */
            case parent::GROUPS:
                $groupsFunctions = new Functions_groups($this);
                return $groupsFunctions->removeGroup($params['groupId']);
                
            /*
             * Remove group
             */
            case parent::PROACTIVE_ACCOUNT:
                $proactiveFunctions = new Functions_proactive($this);
                return $proactiveFunctions->removeAccount($params['accountId']);
                
            /*
             * Remove group
             */
            case parent::WPS_GROUP_RIGHTS:
                $wpsRightsFunctions = new Functions_wpsrights($this);
                return $wpsRightsFunctions->removeWpsRights($params['groupId']);
            
                
                
            default:
                return null;
        }
    }
    
    /**
     * Store object
     * 
     * @param string $typeName
     * @param array $params
     * @return type
     */
    public function store($typeName, $params = array())
    {
        switch ($typeName) {
            
            /*
             * Store cart item
             */
            case parent::CART_ITEM:
                $cartFunctions = new Functions_cart($this);
                return $cartFunctions->addToCart($params['email'], $params['item']);
            
            /*
             * Store processing cart item
             */
            case parent::PROCESSING_CART_ITEM:
                $cartFunctions = new Functions_processingcart($this);
                return $cartFunctions->addToProcessingCart($params['userid'], $params['item']);
            
            /*
             * Store processing item
             */
            case parent::PROCESSING_JOBS_ITEM:
                $jobsFunctions = new Functions_jobs($this);
                return $jobsFunctions->add($params['userid'], $params['data'], $params['context']);
                
             /*
             * Store collection
             */
            case parent::COLLECTION:
                $collectionsFunctions = new Functions_collections($this);
                return $collectionsFunctions->storeCollection($params['collection']);
            
            /*
             * Store facets
             */
            case parent::FACETS:
                $facetsFunctions = new Functions_facets($this);
                return $facetsFunctions->storeFacets($params['facets'], $params['collectionName']);
                
            /*
             * Store feature
             */
            case parent::FEATURE:
                $featuresFunctions = new Functions_features($this);
                return $featuresFunctions->storeFeature($params['collection'], $params['featureArray']);
                
            /*
             * Store feature facets
             */
            case parent::FEATURE_FACETS:
                $featuresFunctions = new Functions_features($this);
                return $featuresFunctions->storeKeywordsFacets($params['collection'], $params['keywords']);
                
            
            /*
             * Store cart item
             */
            case parent::ORDER:
                $cartFunctions = new Functions_cart($this);
                return $cartFunctions->placeOrder($params['email'], isset($params['items']) ? $params['items'] : null);
            
            /*
             * Store query
             */
            case parent::QUERY:
                $generalFunctions = new Functions_general($this);
                return $generalFunctions->storeQuery($params['userid'], $params['query']);
            
            /*
             * Store rights
             */
            case parent::RIGHTS:
                $rightsFunctions = new Functions_rights($this);
                return $rightsFunctions->storeRights($params['rights'], $params['emailOrGroup'], $params['collectionName'], isset($params['featureIdentifier']) ? $params['featureIdentifier'] : null, isset($params['productIdentifier']) ? $params['productIdentifier'] : null);
            
            /*
             * Store user profile
             */
            case parent::USER_PROFILE:
                $usersFunctions = new Functions_users($this);
                return $usersFunctions->storeUserProfile($params['profile']);

            /*
             * Store group
             */
            case parent::GROUPS:
                $groupsFunctions = new Functions_groups($this);
                return $groupsFunctions->createGroup($params['groupName'], $params['groupDescription'], $params['groupCanWps'], $params['groupProactiveId']);
            
            /*
             * Store WPS rights for the specified group
             */
            case parent::WPS_GROUP_RIGHTS:
                $wpsrightsFunctions = new Functions_wpsrights($this);
                return $wpsrightsFunctions->storeWpsRights($params['groupId'], $params['wpsRights']);
                
            /*
             * Store Proactive account
             */
            case parent::PROACTIVE:
                $proactiveFunctions = new Functions_proactive($this);
                return $proactiveFunctions->createAccount($params['accountLogin'], $params['accountPassword']);
            
                
            default:
                return null;
        }
    }
   
    /**
     * Update object
     * 
     * @param string $typeName
     * @param array $params
     * @return type
     */
    public function update($typeName, $params = array()) {
        switch ($typeName) {
            
            /*
             * Update cart item
             */
            case parent::CART_ITEM:
                $cartFunctions = new Functions_cart($this);
                return $cartFunctions->updateCart($params['email'], $params['itemId'], $params['item']);
            
            /*
             * Update processing cart item
             */
            case parent::PROCESSING_CART_ITEM:
                $cartFunctions = new Functions_processingcart($this);
                return $cartFunctions->updateProcessingCart($params['userid'], $params['itemId'], $params['item']);
            
            /*
             * Update processing item
             */
            case parent::PROCESSING_JOBS_ITEM:
                $jobsFunctions = new Functions_jobs($this);
                return $jobsFunctions->update($params['userid'], $params['data'], $params['context']);
                
             /*
             * Update rights
             */
            case parent::RIGHTS:
                $rightsFunctions = new Functions_rights($this);
                return $rightsFunctions->updateRights($params['rights'], $params['emailOrGroup'], $params['collectionName'], $params['featureIdentifier']);
            
            /*
             * Update user profile
             */
            case parent::USER_PROFILE:
                $usersFunctions = new Functions_users($this);
                return $usersFunctions->updateUserProfile($params['profile']);

            /*
             * Update group
             */
            case parent::GROUPS:
                $groupsFunctions = new Functions_groups($this);
                return $groupsFunctions->updateGroup($params);
                
            /*
             * Update proactive account
             */
            case parent::PROACTIVE_ACCOUNT:
                $proactiveFunctions = new Functions_proactive($this);
                return $proactiveFunctions->updateAccount($params['accountId'], $params['accountLogin'], $params['accountPassword']);
                
            /*
             * Store feature
             */
            case parent::FEATURE:
                $featuresFunctions = new Functions_features($this);
                return $featuresFunctions->updateFeature($params['collection'], $params['featureArray']);

            /*
             * Update NRT product (visibility on api search results and add new version link)
             */
            case parent::FEATURE_VERSION:
                $featuresFunctions = new Functions_features($this);
                return $featuresFunctions->updateFeatureVersions($params['collection'], $params['featuresArray'], $params['visible'], $params['newVersion']);

            /*
             * Update Keywords
             */
            case parent::KEYWORDS:
                $featuresFunctions = new Functions_features($this);
                return $featuresFunctions->updateFeatureKeywords($params['feature'], $params['keywords']);

            default:
                return null;
        }
    }
    
    /**
     * Close database handler
     */
    public function closeDbh() {
        if (isset($this->dbh)) {
            pg_close($this->dbh);
        }
    }
    
    /**
     * Return $sentence in lowercase, without accent and with "'" character 
     * replaced by a space
     * 
     * This function is superseed in RestoDabaseDriver_PostgreSQL and use
     * the inner function lower(unaccent($sentence)) defined in installDB.sh
     * 
     * @param string $sentence
     */
    public function normalize($sentence) {
        try {
            if (!isset($sentence)) {
                throw new Exception();
            }
            $results = pg_query($this->dbh, 'SELECT lower(unaccent(\'' . pg_escape_string($sentence) . '\')) as normalized');
            if (!$results) {
                throw new Exception();
            }
            $result = pg_fetch_assoc($results);
            return str_replace('\'', ' ', $result['normalized']);
        } catch (Exception $e) {
            return $sentence;
        }
    }
    
    /**
     * Perform query on database
     * 
     * @param string $query
     * @param integer $errorCode
     * @param string $errorMessage
     * @return Database result
     * @throws Exception
     */
    public function query($query, $errorCode = 500, $errorMessage = null) {
        try {
            $results = pg_query($this->dbh, $query);
            if (!$results) {
                throw new Exception();
            }
            return $results;
        }
        catch (Exception $e) {
            RestoLogUtil::httpError($errorCode, isset($errorMessage) ? $errorMessage : 'Database connection error');
        }
    }
    
    /**
     * Convert database query result into array
     * 
     * @param DatabaseResult $results
     * @return array
     */
    public function fetch($results) {
        $output = pg_fetch_all($results);
        return $output === false ? array() : $output;
    }
    
    /**
     * Convert database query result into associative array
     * 
     * @param DatabaseResult $results
     * @return array
     */
    public function fetch_assoc($results) {
        $output = pg_fetch_assoc($results);
        return $output === false ? array() : $output;
    }
    
    /**
     * Return PostgreSQL database handler
     * 
     * @param array $options
     * @throws Exception
     */
    private function getHandler($options = array()) {
    
        $dbh = null;
        
        if (isset($options) && isset($options['dbname'])) {
            try {
                $dbInfo = array(
                    'dbname=' . $options['dbname'],
                    'user=' . $options['user'],
                    'password=' . $options['password']
                );
                /*
                 * If host is specified, then TCP/IP connection is used
                 * Otherwise socket connection is used
                 */
                if (isset($options['host'])) {
                    $dbInfo[] = 'host=' . $options['host'];
                    $dbInfo[] = 'port=' . (isset($options['port']) ? $options['port'] : '5432');
                }
                $dbh = pg_connect(join(' ', $dbInfo));
                if (!$dbh) {
                    throw new Exception();
                }
            } catch (Exception $e) {
                RestoLogUtil::httpError(500, 'Database connection error');
            }
        }   

        return $dbh;
    }

}
