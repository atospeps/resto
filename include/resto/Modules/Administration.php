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
 * resto administration module
 * 
 * Authors :
 * 
 *      jerome[dot]gasperi[at]gmail[dot]com
 *      remi[dot]mourembles[at]capgemini[dot]com
 * 
 * This module provides html hmi to administrate RESTo
 * 
 * ** Administration **
 * 
 * 
 *    |          Resource                                                             |     Description
 *    |_______________________________________________________________________________|______________________________________
 *    |  GET     administration/users                                                 |  Display users informations
 *    |  POST    administration/users                                                 |  Create user
 *    |  GET     administration/users/history                                         |  Get all users history
 *    |  POST    administration/users/{userid}                                        |  Update user group
 *    |  POST    administration/users/{userid}/rights                                 |  Add new rights for {userid}
 *    |  GET     administration/users/{userid}/rights                                 |  Get detailed rights for {userid}
 *    |  POST    administration/users/{userid}/rights/delete                          |  Remove all rights to a specific product for {userid}
 *    |  POST    administration/users/{userid}/rights/update                          |  Update a specific right to collection for {userid}
 *    |  GET     administration/users/{userid}/history                                |  Get history for {userid}
 *    |  POST    administration/users/{userid}/activate                               |  Activate {userid}
 *    |  POST    administration/users/{userid}/deactivate                             |  Deactivate {userid}
 *    |  GET     administration/collections                                           |  Display all collections
 *    |  POST    administration/collections                                           |  Update a specific right to collection for userOrGroup
 *    |  GET     administration/stats/users                                           |  Get stats about users
 *    |  GET     administration/stats/users/{userid}                                  |  Get stats about user {userid}
 *    |  GET     administration/stats/collections                                     |  Get stats about collections
 * 
 */
class Administration extends RestoModule {
    /*
     * Resto context
     */
    public $context;

    /*
     * Current user (only set for administration on a single user)
     */
    public $user = null;

    /*
     * segments
     */
    public $segments;

    /**
     * Constructor
     * 
     * @param RestoContext $context
     * @param array $options : array of module parameters
     */
    public function __construct($context, $user, $options = array()) {

        parent::__construct($context, $options);

        // Set user
        $this->user = $user;

        // Set context
        $this->context = $context;
    }

    /**
     * Run 
     * 
     * @param array $segments
     * @throws Exception
     */
    public function run($segments, $data = array()) {
        
        if ($this->user->profile['groupname'] !== 'admin') {
            /*
             * Only administrators can access to administration
             */
            RestoLogUtil::httpError(401);
        }
        
        if ($this->context->method === 'POST' && $this->context->outputFormat !== 'json') {
            /*
             * Only JSON can be posted
             */
            RestoLogUtil::httpError(404);
        }

        $this->segments = $segments;
        $method = $this->context->method;

        /*
         * Switch on HTTP methods
         */
        switch ($method) {
            case 'GET':
                return $this->processGET();
            case 'POST':
                return $this->processPOST($data);
            case 'PUT':
                return $this->processPUT($data);
            default:
                RestoLogUtil::httpError(404);
        }
    }

    /**
     * Process on HTTP method POST on /administration
     * 
     * @throws Exception
     */
    private function processPOST($data) {

        /*
         * Can't post file on /administration
         */
        if (!isset($this->segments[0])) {
            RestoLogUtil::httpError(404);
        }
        /*
         * Switch on url segments
         */ else {
            switch ($this->segments[0]) {
                case 'users':
                    return $this->processPostUsers($data);
                case 'collections':
                    return $this->processPostCollections();
                default:
                    RestoLogUtil::httpError(404);
            }
        }
    }

    /**
     * Process on HTTP method PUT on /administration
     * 
     * @throws Exception
     */
    private function processPUT($data) {        
        /*
         * Switch on url segments
         */ 
        switch ($this->segments[0]) {
            case 'users':
                return $this->processPutUsers($data);
            default:
                RestoLogUtil::httpError(404);
        }
    }

    /**
     * Process on HTTP method GET on /administration
     * 
     * @throws Exception
     */
    private function processGET() {

        switch ($this->segments[0]) {
            case 'users':
                return $this->processGetUsers();
            case 'collections':
                return $this->processGetCollections();
            case 'stats':
                return $this->processStatistics();
            default:
                RestoLogUtil::httpError(404);
        }
    }

    /**
     * Process when GET on /administration/collections
     * 
     * @throws Exception
     */
    private function processGetCollections() {

        /*
         * Get on /administration/collections
         */
        if (isset($this->segments[1])) {
            RestoLogUtil::httpError(404);
        } else {
            $rights = array();
            $this->groups = $this->context->dbDriver->get(RestoDatabaseDriver::GROUPS);
            $this->collections = $this->context->dbDriver->get(RestoDatabaseDriver::COLLECTIONS_DESCRIPTIONS);
            foreach ($this->collections as $collection => $description) {
                $item = array();
                $item['name'] = $collection;
                $item['groups'] = array();
                foreach ($this->groups as $group) {
                    $itemGroup = array();
                    $restoRights = new RestoRights($group['id'], $group['groupname'], $this->context);
                    $itemGroup['name'] = $group['groupname'];
                    $itemGroup['rights'] = $restoRights->getRights($collection);
                    $item['groups'][$group['groupname']] = $itemGroup;
                }
                $rights[$collection] = $item;
            }

            return $this->to($rights);
        }
    }

    /**
     * Process when POST on /administration/collections
     * 
     * @throws Exception
     */
    private function processPostCollections() {
        if (isset($this->segments[1])) {
            RestoLogUtil::httpError(404);
        }
        /*
         * Update rights
         */ else {
            return $this->updateRights();
        }
    }

    /**
     * Process get on /administration/users/{userid}
     * 
     * @throws Exception
     */
    private function processGetUser() {
        if ($this->segments[2] == 'history') {
            /**
             * Process get on /administration/users/{userid}/history
             * 
             * Return the history for user associated to {userid}
             */
            $this->startIndex = 0;
            $this->numberOfResults = 12;
            $this->keyword = null;
            $this->collectionFilter = null;
            $this->service = null;
            $this->orderBy = null;
            $this->ascordesc = null;
            $this->method = null;
            /*
             * Get request params
             */
            if (filter_input(INPUT_GET, 'startIndex')) {
                $this->startIndex = htmlspecialchars(filter_input(INPUT_GET, 'startIndex'), ENT_QUOTES);
            }
            if (filter_input(INPUT_GET, 'numberOfResults')) {
                $this->numberOfResults = htmlspecialchars(filter_input(INPUT_GET, 'numberOfResults'), ENT_QUOTES);
            }
            if (filter_input(INPUT_GET, 'collection')) {
                $this->collectionFilter = htmlspecialchars(filter_input(INPUT_GET, 'collection'), ENT_QUOTES);
            }
            if (filter_input(INPUT_GET, 'service')) {
                $this->service = htmlspecialchars(filter_input(INPUT_GET, 'service'), ENT_QUOTES);
            }
            if (filter_input(INPUT_GET, 'method')) {
                $this->method = htmlspecialchars(filter_input(INPUT_GET, 'method'), ENT_QUOTES);
            }
            if (filter_input(INPUT_GET, 'orderBy')) {
                $this->orderBy = htmlspecialchars(filter_input(INPUT_GET, 'orderBy'), ENT_QUOTES);
            }
            if (filter_input(INPUT_GET, 'ascordesc')) {
                $this->ascordesc = htmlspecialchars(filter_input(INPUT_GET, 'ascordesc'), ENT_QUOTES);
            }

            $options = array(
                'orderBy' => $this->orderBy,
                'ascOrDesc' => $this->ascordesc,
                'collectionName' => $this->collectionFilter,
                'service' => $this->service,
                'method' => $this->method,
                'startIndex' => $this->startIndex,
                'numberOfResults' => $this->numberOfResults
            );

            $this->historyList = $this->getHistory($this->segments[1], $options);

            return $this->to($this->historyList);
        } else if ($this->segments[2] == 'rights') {

            /*
             * Process get on /administration/users/{userid}/rights
             * 
             * Get rights on all collections and features for user associated to {userid}
             * 
             */
            $user = new RestoUser($this->context->dbDriver->get(RestoDatabaseDriver::USER_PROFILE, array('userid' => $this->segments[1])), $this->context);

            $rights = array();
            $collections = $this->context->dbDriver->get(RestoDatabaseDriver::COLLECTIONS_DESCRIPTIONS);

            $fullRights = $user->getFullRights();

            foreach ($collections as $collectionName => $description) {

                $rights[$collectionName] = $user->getRights($collectionName);
                if (isset($fullRights[$collectionName])) {
                    $rights[$collectionName]['features'] = $fullRights[$collectionName]['features'];
                }
            }

            return RestoLogUtil::success('Rights for ' . $user->profile['userid'], array(
                        'userid' => $user->profile['userid'],
                        'groupname' => $user->profile['groupname'],
                        'rights' => $rights
            ));
        } else {
            RestoLogUtil::httpError(404);
        }
    }

    /**
     * Process when GET on /administration/users
     * 
     * @throws Exception
     */
    private function processGetUsers() {
        /*
         * Get user creation MMI
         */
        if (isset($this->segments[1])) {
            if ($this->segments[1] == 'history') {

                $this->startIndex = 0;
                $this->numberOfResults = 12;
                $this->keyword = null;
                $this->collectionFilter = null;
                $this->service = null;
                $this->orderBy = null;
                $this->ascordesc = null;
                $this->method = null;
                if (filter_input(INPUT_GET, 'startIndex')) {
                    $this->startIndex = htmlspecialchars(filter_input(INPUT_GET, 'startIndex'), ENT_QUOTES);
                }
                if (filter_input(INPUT_GET, 'numberOfResults')) {
                    $this->numberOfResults = htmlspecialchars(filter_input(INPUT_GET, 'numberOfResults'), ENT_QUOTES);
                }
                if (filter_input(INPUT_GET, 'collection')) {
                    $this->collectionFilter = htmlspecialchars(filter_input(INPUT_GET, 'collection'), ENT_QUOTES);
                }
                if (filter_input(INPUT_GET, 'service')) {
                    $this->service = htmlspecialchars(filter_input(INPUT_GET, 'service'), ENT_QUOTES);
                }
                if (filter_input(INPUT_GET, 'method')) {
                    $this->method = htmlspecialchars(filter_input(INPUT_GET, 'method'), ENT_QUOTES);
                }
                if (filter_input(INPUT_GET, 'orderBy')) {
                    $this->orderBy = htmlspecialchars(filter_input(INPUT_GET, 'orderBy'), ENT_QUOTES);
                }
                if (filter_input(INPUT_GET, 'ascordesc')) {
                    $this->ascordesc = htmlspecialchars(filter_input(INPUT_GET, 'ascordesc'), ENT_QUOTES);
                }

                $options = array(
                    'orderBy' => $this->orderBy,
                    'ascOrDesc' => $this->ascordesc,
                    'collection' => $this->collectionFilter,
                    'service' => $this->service,
                    'method' => $this->method,
                    'startIndex' => $this->startIndex,
                    'numberOfResults' => $this->numberOfResults
                );

                $this->historyList = $this->getHistory(null, $options);

                return $this->to($this->historyList);
            } else {
                return $this->processGetUser();
            }
        } else {
            /*
             * Users list MMI
             */
            $this->min = 0;
            $this->number = 50;
            $this->keyword = null;
            if (filter_input(INPUT_GET, 'min')) {
                $this->min = htmlspecialchars(filter_input(INPUT_GET, 'min'), ENT_QUOTES);
            }
            if (filter_input(INPUT_GET, 'number')) {
                $this->number = htmlspecialchars(filter_input(INPUT_GET, 'number'), ENT_QUOTES);
            }
            if (filter_input(INPUT_GET, 'keyword')) {
                $this->keyword = htmlspecialchars(filter_input(INPUT_GET, 'keyword'), ENT_QUOTES);
                $this->global_search_val = htmlspecialchars(filter_input(INPUT_GET, 'keyword'), ENT_QUOTES);
            } else {
                $this->keyword = null;
                $this->global_search_val = $this->context->dictionary->translate('_menu_globalsearch');
            }
            $this->usersProfiles = $this->getUsersProfiles($this->keyword, $this->min, $this->number);

            return $this->to($this->usersProfiles);
        }
    }

    /**
     * Process when POST on /administration/users
     * 
     * @throws Exception
     */
    private function processPostUsers($data) {
        
        if (isset($this->segments[1])) {
            return $this->processPostUser($data);
        } else {
            /*
             * Insert user
             */
            return $this->createUser($data);
        }
    }

    /**
     * Process when post on /administration/users/{userid}
     * 
     * @throws Exception
     */
    private function processPostUser($data) {

        if (isset($this->segments[2])) {
            /*
             * Activate user
             */
            if ($this->segments[2] == 'activate') {
                return $this->activate();
            }
            /*
             * Deactivate user
             */ else if ($this->segments[2] == 'deactivate') {
                return $this->deactivate();
            }
            /*
             * Add rights to user
             */ else if ($this->segments[2] == 'rights') {
                return $this->processPostRights();
            } else {
                RestoLogUtil::httpError(404);
            }
        } else {
            /*
             * Update user
             */
            return $this->updateUser($data);
        }
    }
    
    /**
     * Process when PUT on /administration/users
     *
     * @throws Exception
     */
    private function processPutUsers($data) {
        /*
         * users/{userid}
         */
        if (isset($this->segments[1]) && !isset($this->segments[2])) {
            return $this->PUT_userProfile($this->segments[1], $data);
        }
    }

    /**
     * Process post on /administration/user/{userid}/rights
     * This post is different because it calls a delete method on rights
     * 
     * @throws Exception
     */
    private function processPostRights() {
        if (isset($this->segments[3])) {
            /*
             * This post delete rights passed with data
             */
            if ($this->segments[3] === 'delete') {
                return $this->deleteRights();
            } else if ($this->segments[3] === 'update') {
                return $this->updateRights();
            } else {
                RestoLogUtil::httpError(404);
            }
        } else {
            return $this->addRights();
        }
    }

    /**
     * Create new user
     * 
     * @return type
     */
    private function createUser($data) {
        if ($data) {
            if (!isset($data['email'])) {
                RestoLogUtil::httpError(400, 'Email is not set');
            }

            if ($this->context->dbDriver->check(RestoDatabaseDriver::USER, array('email' => $data['email']))) {
                RestoLogUtil::httpError(3000);
            }
            $userInfo = $this->context->dbDriver->store(RestoDatabaseDriver::USER_PROFILE, array(
                'profile' => array(
                    'email' => $data['email'],
                    'password' => isset($data['password']) ? $data['password'] : null,
                    'username' => isset($data['username']) ? $data['username'] : null,
                    'givenname' => isset($data['givenname']) ? $data['givenname'] : null,
                    'lastname' => isset($data['lastname']) ? $data['lastname'] : null,
                	'organization' => isset($data['organization']) ? $data['organization'] : null,
	                'nationality' => isset($data['nationality']) ? $data['nationality'] : null,
	                'domain' => isset($data['domain']) ? $data['domain'] : null,
	                'use' => isset($data['use']) ? $data['use'] : null,
	                'country' => isset($data['country']) ? $data['country'] : null,
	                'adress' => isset($data['adress']) ? $data['adress'] : null,
	                'numtel' => isset($data['numtel']) ? $data['numtel'] : null,
	                'numfax' => isset($data['numfax']) ? $data['numfax'] : null,
	                'instantdownloadvolume' => isset($data['instantdownloadvolume']) ? $data['instantdownloadvolume'] : $this->context->instantDownloadLimit,
	                'weeklydownloadvolume' => isset($data['weeklydownloadvolume']) ? $data['weeklydownloadvolume'] : $this->context->weeklyDownloadLimit,
                    'activated' => 0
                ))
            );
            if (!isset($userInfo)) {
                RestoLogUtil::httpError(500, 'Database connection error');
            }
            return RestoLogUtil::success('User ' . $data['email'] . ' created');
        } else {
            RestoLogUtil::httpError(404);
        }
    }

    /**
     * updateUser - update new user in database
     * 
     * @throws Exception
     */
    private function updateUser($userParam) {
        if ($userParam) {
            try {
                $profile = $this->context->dbDriver->get(RestoDatabaseDriver::USER_PROFILE, array('userid' => $this->segments[1]));

                if (isset($userParam['groupname'])) {
                    $profile['groupname'] = $userParam['groupname'];
                }

                $this->context->dbDriver->update(RestoDatabaseDriver::USER_PROFILE, array('profile' => $profile));
                return array('status' => 'success', 'message' => 'success');
            } catch (Exception $e) {
                RestoLogUtil::httpError($e->getCode(), $e->getMessage());
            }
        } else {
            RestoLogUtil::httpError(404);
        }
    }

    private function updateRights() {

        try {
            /*
             * Get posted data
             */
            $postedData = array();
            $postedData['emailorgroup'] = htmlspecialchars(filter_input(INPUT_POST, 'emailorgroup'), ENT_QUOTES);
            $postedData['collection'] = htmlspecialchars(filter_input(INPUT_POST, 'collection'), ENT_QUOTES);
            $postedData['feature'] = htmlspecialchars(filter_input(INPUT_POST, 'feature'), ENT_QUOTES);
            $postedData['field'] = htmlspecialchars(filter_input(INPUT_POST, 'field'), ENT_QUOTES);
            $postedData['value'] = htmlspecialchars(filter_input(INPUT_POST, 'value'), ENT_QUOTES);
        
            $emailorgroup = $postedData['emailorgroup'];
            $collectionName = ($postedData['collection'] === '') ? null : $postedData['collection'];
            $featureId = ($postedData['feature'] === '') ? null : $postedData['feature'];

            /*
             * Posted rights
             */
            $rights = array($postedData['field'] => $postedData['value']);

            $params = array();
            $params['emailOrGroup'] = $emailorgroup;
            $params['collectionName'] = $collectionName;
            $params['featureIdentifier'] = $featureId;
            $params['rights'] = $rights;
            $right = $this->context->dbDriver->get(RestoDatabaseDriver::RIGHTS, $params);

            if (!$right) {

                /*
                 * Store rights
                 */
                $this->storeQuery('create', $params['collectionName'], $params['featureIdentifier']);
                $this->context->dbDriver->store(RestoDatabaseDriver::RIGHTS, $params);

                /*
                 * Success information
                 */
                return array('status' => 'success', 'message' => 'success');
            } else {
                /*
                 * Upsate rights
                 */
                $this->storeQuery('update', $params['collectionName'], $params['featureIdentifier']);
                $this->context->dbDriver->update(RestoDatabaseDriver::RIGHTS, $params);


                /*
                 * Success information
                 */
                return array('status' => 'success', 'message' => 'success');
            }
        } catch (Exception $e) {
            RestoLogUtil::httpError($e->getCode(), $e->getMessage());
        }
    }

    /**
     * Add rights 
     * 
     * @throws Exception
     */
    private function addRights() {
        try {
            /*
             * Get posted data
             */
            $postedData = array();
            $postedData['emailorgroup'] = htmlspecialchars(filter_input(INPUT_POST, 'emailorgroup'), ENT_QUOTES);
            $postedData['collection'] = htmlspecialchars(filter_input(INPUT_POST, 'collection'), ENT_QUOTES);
            $postedData['featureIdentifier'] = htmlspecialchars(filter_input(INPUT_POST, 'featureid'), ENT_QUOTES);
            $postedData['search'] = htmlspecialchars(filter_input(INPUT_POST, 'search'), ENT_QUOTES);
            $postedData['visualize'] = htmlspecialchars(filter_input(INPUT_POST, 'visualize'), ENT_QUOTES);
            $postedData['download'] = htmlspecialchars(filter_input(INPUT_POST, 'download'), ENT_QUOTES);
            $postedData['canput'] = htmlspecialchars(filter_input(INPUT_POST, 'canput'), ENT_QUOTES);
            $postedData['canpost'] = htmlspecialchars(filter_input(INPUT_POST, 'canpost'), ENT_QUOTES);
            $postedData['candelete'] = htmlspecialchars(filter_input(INPUT_POST, 'candelete'), ENT_QUOTES);
            $postedData['filters'] = filter_input(INPUT_POST, 'filters') === 'null' ? null : htmlspecialchars(filter_input(INPUT_POST, 'filters'), ENT_QUOTES);
            $postedData['wps'] = htmlspecialchars(filter_input(INPUT_POST, 'wps'), ENT_QUOTES);

            if (!$this->context->dbDriver->check(RestoDatabaseDriver::FEATURE, $postedData)) {
                throw new Exception('Feature does not exists', 4004);
            }


            $emailorgroup = $postedData['emailorgroup'];
            $collectionName = ($postedData['collection'] === '') ? null : $postedData['collection'];
            $featureIdentifier = ($postedData['featureIdentifier'] === '') ? null : $postedData['featureIdentifier'];

            /*
             * Posted rights
             */
            $rights = array('search' => $postedData['search'], 'visualize' => $postedData['visualize'], 'download' => $postedData['download'], 'canput' => $postedData['canput'], 'canpost' => $postedData['canpost'], 'candelete' => $postedData['candelete'], 'filters' => $postedData['filters'], 'wps' => $postedData['wps']);

            /*
             * Store rights
             */
            $params = array();
            $params['emailOrGroup'] = $emailorgroup;
            $params['collectionName'] = $collectionName;
            $params['featureIdentifier'] = $featureIdentifier;
            $params['rights'] = $rights;

            if ($this->context->dbDriver->get(RestoDatabaseDriver::RIGHTS, $params) !== null) {
                throw new Exception('Right already exists for this feature', 4004);
            }

            $this->storeQuery('create', $params['collectionName'], $params['featureIdentifier']);
            $this->context->dbDriver->store(RestoDatabaseDriver::RIGHTS, $params);

            /*
             * Success information
             */
            return array('status' => 'success', 'message' => 'success');
        } catch (Exception $e) {
            RestoLogUtil::httpError($e->getCode(), $e->getMessage());
        }
    }

    /**
     * Delete rights
     * 
     * @throws Exception
     */
    private function deleteRights() {

        try {
            $rights = array();
            $rights['emailOrGroup'] = htmlspecialchars(filter_input(INPUT_POST, 'emailorgroup'), ENT_QUOTES);
            $rights['collectionName'] = htmlspecialchars(filter_input(INPUT_POST, 'collection'), ENT_QUOTES);
            $rights['featureIdentifier'] = htmlspecialchars(filter_input(INPUT_POST, 'featureid'), ENT_QUOTES);

            $rights['collectionName'] = $rights['collectionName'] === '' ? null : $rights['collectionName'];
            $rights['featureIdentifier'] = $rights['featureIdentifier'] === '' ? null : $rights['featureIdentifier'];

            $this->storeQuery('remove', $rights['collectionName'], $rights['featureIdentifier']);
            $this->context->dbDriver->remove(RestoDatabaseDriver::RIGHTS, $rights);

            return array('status' => 'success', 'message' => 'success');
        } catch (Exception $e) {
            RestoLogUtil::httpError($e->getCode());
        }
    }

    /**
     * Activate user
     * 
     * @throws Exception
     */
    private function activate() {



        try {
            $params = array();
            $params['userid'] = $this->segments[1];
            $this->context->dbDriver->execute(RestoDatabaseDriver::ACTIVATE_USER, $params);
            return array('status' => 'success', 'message' => 'success');
        } catch (Exception $e) {
            throw new Exception($e->getMessage, $e->getCode);
        }
    }

    /**
     * Deactivate user
     * 
     * @throws Exception
     */
    private function deactivate() {



        try {
            $params = array();
            $params['userid'] = $this->segments[1];
            $this->context->dbDriver->execute(RestoDatabaseDriver::DEACTIVATE_USER, $params);
            return array('status' => 'success', 'message' => 'success');
        } catch (Exception $e) {
            throw new Exception($e->getMessage, $e->getCode);
        }
    }

    /**
     * Process statistics
     * 
     * @return type
     * @throws Exception
     */
    private function processStatistics() {
        switch ($this->segments[1]) {
            case 'collections':
                return $this->to($this->statisticsService());
            case 'users':
                if (!isset($this->segments[2])) {
                    return $this->to($this->statisticsUsers());
                } else if (isset($this->segments[2]) && !isset($this->segments[3])) {
                    return $this->to($this->statisticsUser($this->segments[2]));
                } else {
                    throw new Exception(null, 404);
                }
                break;
            default:
                break;
        }
    }

    /**
     * Statistics over users
     * 
     * @return type
     */
    private function statisticsUsers() {
        /**
         * nb users
         * nb download
         * nb visualize
         * nb 
         */
        $statistics = array();
        $statistics['users'] = $this->countUsers();
        $statistics['download'] = $this->countService('download');
        $statistics['search'] = $this->countService('search');
        $statistics['visualize'] = $this->countService('resource');
        $statistics['insert'] = $this->countService('insert');
        $statistics['create'] = $this->countService('create');
        $statistics['update'] = $this->countService('update');
        $statistics['remove'] = $this->countService('remove');
        return $statistics;
    }
    
    /**
     * 
     * @param string $userid
     * @return array:
     */
    private function statisticsUser($userid) {

        $statistics = array();
        /*
         * User statistics for each collection
         */
        $collectionStats = array();
        $collections = $this->context->dbDriver->get(RestoDatabaseDriver::COLLECTIONS_DESCRIPTIONS);
        foreach ($collections as $collection => $description) {
            $collection_statistics = array();
            $collection_statistics['download'] = $this->countService('download', $collection, $userid);
            $collection_statistics['search'] = $this->countService('search', $collection, $userid);
            $collection_statistics['visualize'] = $this->countService('resource', $collection, $userid);
            $collection_statistics['insert'] = $this->countService('insert', $collection, $userid);
            $collection_statistics['create'] = $this->countService('create', $collection, $userid);
            $collection_statistics['update'] = $this->countService('update', $collection, $userid);
            $collection_statistics['remove'] = $this->countService('remove', $collection, $userid);
            $collectionStats[$collection] = $collection_statistics;
        }
        $statistics["collectionStats"] = $collectionStats;
        
        /**
         * Get total user download volume
         */
        $query = 'SELECT resourceid FROM usermanagement.history  WHERE service=\'download\' AND userid=\'' . pg_escape_string($userid) . '\'';
        $results = pg_fetch_all($this->context->dbDriver->query($query));
        $features = array ();
        if($results) {
            foreach ($results as $item) {
                $id = $item['resourceid'];
                if ($features[$id]) {
                    $features[$id] += 1;
                } else {
                    $features[$id] = 1;
                }
            }
        }

        // Compute the total size of this features
        $totalsize = 0;
        foreach ($features as $key => $value) {
            $query = 'SELECT resource_size FROM resto.features  WHERE identifier=\'' . pg_escape_string($key) . '\'';
            $results = pg_fetch_all($this->context->dbDriver->query($query));
            $totalsize += $results[0]['resource_size'] * $value;
        }

        $statistics["downloadVolume"] = $totalsize / 1000000;
        
        return $statistics;
    }

    /**
     * statisticsService - services stats on collections
     * 
     * @param int $userid
     * @return type
     */
    private function statisticsService($userid = null) {
        $startDate = isset($this->context->query['startDate']) ? $this->context->query['startDate'] : null;
        $endDate = isset($this->context->query['endDate']) ? $this->context->query['endDate'] : null;
        $statistics = array();
        
        /*
         * Compute total downloaded volume
         */
        $productVolume = 0;
        $query = 'SELECT sum(resource_size) FROM resto.features INNER JOIN usermanagement.history ON resto.features.identifier = usermanagement.history.resourceid WHERE service=\'download\'';
        if($startDate && $endDate) {
            $query .= ' AND querytime>\'' . pg_escape_string($startDate) . '\' AND querytime<\'' . pg_escape_string($endDate) . '\'';
        }
        $results = pg_fetch_assoc(pg_query($this->context->dbDriver->dbh, $query));
        if($results) {
            $productVolume = $results['sum'];
        }
        
        /*
         * Statistics for each collections
         */
        $productQuantity = 0;
        $collectionStats = array();
        $collections = $this->context->dbDriver->get(RestoDatabaseDriver::COLLECTIONS_DESCRIPTIONS);
        foreach ($collections as $collection => $description) {
            $collection_statistics = array();
            $collection_statistics['download'] = $this->countService('download', $collection, $userid, $startDate, $endDate);
            $productQuantity += $collection_statistics['download']['count']; 
            $collection_statistics['search'] = $this->countService('search', $collection, $userid, $startDate, $endDate);
            $collection_statistics['visualize'] = $this->countService('resource', $collection, $userid, $startDate, $endDate);
            $collection_statistics['insert'] = $this->countService('insert', $collection, $userid, $startDate, $endDate);
            $collection_statistics['create'] = $this->countService('create', $collection, $userid, $startDate, $endDate);
            $collection_statistics['update'] = $this->countService('update', $collection, $userid, $startDate, $endDate);
            $collection_statistics['remove'] = $this->countService('remove', $collection, $userid, $startDate, $endDate);
            $collectionStats[$collection] = $collection_statistics;
        }
        $statistics["collectionStats"] = $collectionStats;
        $statistics["productQuantity"] = $productQuantity;
        $statistics["productVolume"] = $productVolume / 1000000;
        
        return $statistics;
    }

    /**
     * Output collection description as a JSON stream
     * 
     * @param boolean $pretty : true to return pretty print
     */
    public function toJSON($pretty = false) {



        return RestoUtil::json_format($this->data, $pretty);
    }

    /**
     * to - return method depending on return type
     * 
     * @param String $file
     * @param array $data
     * @return method
     * @throws Exception
     */
    private function to($data) {
        return $data;
    }

    /**
     * Get users profile
     * 
     * @param type $keyword
     * @param type $min
     * @param type $number
     * @return array
     * @throws Exception
     */
    public function getUsersProfiles($keyword = null, $min = 0, $number = 50) {

        try {
            $results = pg_query($this->context->dbDriver->dbh, 'SELECT userid, email, groupname, username, givenname, lastname, organization, nationality, domain, use, country, adress, numtel, numfax, instantdownloadvolume, weeklydownloadvolume, registrationdate, activated FROM usermanagement.users ' . (isset($keyword) ? 'WHERE email LIKE \'%' . $keyword . '%\' OR username LIKE \'%' . $keyword . '%\' OR groupname LIKE \'%' . $keyword . '%\' OR givenname LIKE \'%' . $keyword . '%\' OR lastname LIKE \'%' . $keyword . '%\'' : '') . ' LIMIT ' . $number . ' OFFSET ' . $min);
            if (!$results) {
                throw new Exception();
            }
        } catch (Exception $e) {
            RestoLogUtil::httpError(500, 'Cannot get profiles for users');
        }
        $usersProfile = array();
        while ($user = pg_fetch_assoc($results)) {
            if (!$user) {
                return $usersProfile;
            }
            $user['activated'] = $user['activated'] === "1" ? true : false;
            $user['registrationdate'] = substr(str_replace(' ', 'T', $user['registrationdate']), 0, 19) . 'Z';

            $usersProfile[] = $user;
        }

        return $usersProfile;
    }

    /**
     * Count history logs per service
     * 
     * @param string $service : i.e. one of 'download', 'search', etc.
     * @param string $collectionName
     * @param integer $userid
     * @return integer
     * @throws Exception
     */
    public function countService($service, $collectionName = null, $userid = null, $startDate = null, $endDate = null) {
        $query = 'SELECT count(gid) FROM usermanagement.history WHERE service=\'' . pg_escape_string($service) . '\'';
        $query .= isset($collectionName) ? ' AND collection=\'' . pg_escape_string($collectionName) . '\'' : ''; 
        $query .= isset($userid) ? ' AND userid=\'' . pg_escape_string($userid) . '\'' : '';
        if(isset($startDate) && isset($endDate)) {
            $query .= ' AND querytime>\'' . pg_escape_string($startDate) . '\' AND querytime<\'' . pg_escape_string($endDate) . '\'';
        }
        $results = pg_query($this->context->dbDriver->dbh, $query);
        if (!$results) {
            RestoLogUtil::httpError(500, 'Database connection error');
        }
        return pg_fetch_assoc($results);
    }

    /**
     * Count history logs per service
     * 
     * @param boolean $activated
     * @param string $groupname
     * @return integer
     * @throws Exception
     */
    public function countUsers($activated = null, $groupname = null) {
        $results = pg_query($this->context->dbDriver->dbh, 'SELECT COUNT(*) FROM usermanagement.users ' . (isset($activated) ? (' WHERE activated=\'' . ($activated === true ? 't' : 'f') . '\'') : '') . (isset($groupname) ? ' AND groupname=\'' . pg_escape_string($groupname) . '\'' : ''));
        if (!$results) {
            RestoLogUtil::httpError(500, 'Database connection error');
        }
        return pg_fetch_assoc($results);
    }

    /**
     * Get user history
     * 
     * @param integer $userid
     * @param array $options
     *          
     *      array(
     *         'orderBy' => // order field (default querytime),
     *         'ascOrDesc' => // ASC or DESC (default DESC)
     *         'collectionName' => // collection name
     *         'service' => // 'search', 'download' or 'visualize' (default null),
     *         'startIndex' => // (default 0),
     *         'numberOfResults' => // (default 50),
     *         'maxDate' => // 
     *         'minDate' => // 
     *     )
     *          
     * @return array
     * @throws Exception
     */
    public function getHistory($userid = null, $options = array()) {

        $result = array();

        $orderBy = isset($options['orderBy']) ? $options['orderBy'] : 'querytime';
        $ascOrDesc = isset($options['ascOrDesc']) ? $options['ascOrDesc'] : 'DESC';
        $startIndex = isset($options['startIndex']) ? $options['startIndex'] : 0;
        $numberOfResults = isset($options['numberOfResults']) ? $options['numberOfResults'] : 50;

        $where = array();
        if (isset($userid)) {
            $where[] = 'userid=' . pg_escape_string($userid);
        }
        if (isset($options['service'])) {
            $where[] = 'service=\'' . pg_escape_string($options['service']) . '\'';
        }
        if (isset($options['method'])) {
            $where[] = 'method=\'' . pg_escape_string($options['method']) . '\'';
        }
        if (isset($options['collection'])) {
            $where[] = 'collection=\'' . pg_escape_string($options['collection']) . '\'';
        }
        if (isset($options['maxDate'])) {
            $where[] = 'querytime <=\'' . pg_escape_string($options['maxDate']) . '\'';
        }
        if (isset($options['minDate'])) {
            $where[] = 'querytime >=\'' . pg_escape_string($options['minDate']) . '\'';
        }

        $results = pg_query($this->context->dbDriver->dbh, 'SELECT gid, userid, method, service, collection, resourceid, query, querytime, url, ip FROM usermanagement.history' . (count($where) > 0 ? ' WHERE ' . join(' AND ', $where) : '') . ' ORDER BY ' . pg_escape_string($orderBy) . ' ' . pg_escape_string($ascOrDesc) . ' LIMIT ' . $numberOfResults . ' OFFSET ' . $startIndex);
        while ($row = pg_fetch_assoc($results)) {
            $result[] = $row;
        }
        return $result;
    }
    


    /**
     * Process HTTP PUT request on user
     *
     *    users/{userid}                                |  Update {userid}
     *
     * @param string $emailOrId
     * @param array $data
     */
    private function PUT_userProfile($emailOrId, $data) {
        $profile = array();
        $user = $this->user;
        if ($user->profile['groupname'] !== 'admin') {
            RestoLogUtil::httpError(403);
        }

        if (!ctype_digit($emailOrId)) {
            $profile['email'] = strtolower(base64_decode($emailOrId));
        } else {
            $profile['id'] = $emailOrId;
        }
        
        // For each modifiable value get the value
        foreach (array_values(array(
                'username', 'givenname', 'lastname',
                'organization', 'nationality', 'domain',
                'use', 'country', 'adress',
                'numtel', 'numfax',	'instantdownloadvolume',
                'weeklydownloadvolume')) as $field) {
            if (isset($data[$field])) {
                $profile[$field] = $data[$field];
            }
        }
         
        // Check if groupname exists
        if(isset($data['groupname'])) {
            if(!$this->context->dbDriver->check(RestoDatabaseDriver::GROUPS, array('groupname' => $data['groupname']))) {
                RestoLogUtil::httpError(404, "Can't update user, the group " . $data['groupname'] . " does not exist");
            }
            $profile['groupname'] = $data['groupname'];
        }

        if ($this->context->dbDriver->update(RestoDatabaseDriver::USER_PROFILE, array('profile' => $profile))) {
            return RestoLogUtil::success('User updated');
        }
        RestoLogUtil::httpError(400);
    }
}
