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
 * RESTo REST router for POST requests
 */
class RestoRoutePOST extends RestoRoute {
      
    /**
     *
     * Process HTTP POST request
     * 
     *    api/collections/search                        |  Search on all collections
     *    api/collections/{collection}/search           |  Search on {collection}
     *    
     *    api/users/connect                             |  Connect user
     *    api/users/disconnect                          |  Disconnect user
     *    api/users/resetPassword                       |  Reset password
     * 
     *    collections                                   |  Create a new {collection}            
     *    collections/{collection}                      |  Insert new product within {collection}
     *    
     *    groups                                        |  Create a new group      
     *    
     *    proactive                                     |  Create a new proactive account
     *          
     *    users                                         |  Add a user
     *    users/{userid}/cart                           |  Add new item in {userid} cart
     *    users/{userid}/processingcart                 |  Add new item in {userid} processing cart
     *    users/{userid}/orders                         |  Send an order for {userid}
     *    
     * 
     * @param array $segments
     */
    public function route($segments)
    {
        /*
         * Input data for POST request
         */
        $data = RestoUtil::readInputData($this->context->uploadDirectory);

        switch($segments[0]) {
            case 'api':
                return $this->POST_api($segments, $data);
            case 'collections':
                return $this->POST_collections($segments, $data);
            case 'groups':
                return $this->POST_groups($data);
            case 'proactive':
                return $this->POST_proactive($data);
            case 'users':
                return $this->POST_users($segments, $data);
            default:
                return $this->processModuleRoute($segments, $data);
        }
    }

    /**
     * 
     * Process HTTP POST request on api
     * 
     *    api/users/connect                             |  Connect user
     *    api/users/disconnect                          |  Disconnect user
     * 
     * @param array $segments
     * @param array $data
     */
    private function POST_api($segments, $data)
    {
        if (!isset($segments[1])) {
            RestoLogUtil::httpError(404);
        }

        /*
         * api/collections/search
         * api/collections/{collection}/search
         */
        if ($segments[1] === 'collections' &&
           ($segments[2] === 'search' ||
               (isset($segments[2], $segments[3]) && $segments[3] === 'search'))
        ) {
            $this->context->query = $data;
            return $this->POST_apiCollectionsSearch(isset($segments[3]) ? $segments[2] : null);
        }
        
        /*
         * api/users
         */
        else if ($segments[1] === 'users') {
            
            if (!isset($segments[2])) {
                RestoLogUtil::httpError(404);
            }
            
            /*
             * api/users/connect
             */
            if ($segments[2] === 'connect' && !isset($segments[3])) {
                return $this->POST_apiUsersConnect($data);
            }
            
            /*
             * api/users/disconnect
             */
            if ($segments[2] === 'disconnect' && !isset($segments[3])) {
                return $this->POST_apiUsersDisconnect();
            }
            
            /*
             * api/users/resetPassword
             */
            if ($segments[2] === 'resetPassword' && !isset($segments[3])) {
                return $this->POST_apiUsersResetPassword($data);
            }
            
        }
        /*
         * Process module
         */
        else {
            return $this->processModuleRoute($segments, $data);
        }
        
    }
    
    /**
     * Process
     * 
     *    api/collections/search                        |  Search on all collections
     *    api/collections/{collection}/search           |  Search on {collection}
     *    
     * @param string $collectionName
     * @throws Exception
     */
    private function POST_apiCollectionsSearch($collectionName = null)
    {
        /*
         * Search in one collection...or in all collections
         */
        $resource = isset($collectionName) ? new RestoCollection($collectionName, $this->context, $this->user, array('autoload' => true)) : new RestoCollections($this->context, $this->user, array('autoload' => true));
        $this->storeQuery('search', isset($collectionName) ? $collectionName : '*', null);

        return $resource->search();
        
    }
    
    /**
     * Process api/users/connect
     * 
     * @param array $data
     * @return type
     */
    private function POST_apiUsersConnect($data) {
        
        if (!isset($data['email']) || !isset($data['password'])) {
            RestoLogUtil::httpError(400);
        }

        /*
         * Disconnect user
         */
        if (isset($this->user->profile['email'])) {
            $this->user->disconnect();
        }
        
        /*
         * Get profile
         */
        try {
            $this->user = new RestoUser($this->context->dbDriver->get(RestoDatabaseDriver::USER_PROFILE, array('email' => strtolower($data['email']), 'password' => $data['password'])), $this->context);
            if (!isset($this->user->profile['email']) || $this->user->profile['activated'] !== 1) {
                throw new Exception();
            }
            
            $this->user->token = $this->context->createToken($this->user->profile['userid'], $this->user->profile,  $this->user->isAdmin());

            return array(
                'token' => $this->user->token
            );
        } catch (Exception $ex) {
            RestoLogUtil::httpError(403);
        }
    }

    /**
     * Process api/users/disconnect
     */
    private function POST_apiUsersDisconnect() {
        $this->user->disconnect();
        return RestoLogUtil::success('User disconnected');
    }

    /**
     * Process api/users/resetPassword
     * 
     * @param array $data
     * @return type
     */
    private function POST_apiUsersResetPassword($data) {
        
        if (!isset($data['url']) || !isset($data['email']) || !isset($data['password']) || !RestoUtil::isValidPassword($data['password'])) {
            RestoLogUtil::httpError(400);
        }
        
        $email = strtolower(base64_decode($data['email']));
        
        /*
         * Explod data['url'] into resourceUrl and queryString
         */
        $pair = explode('?', $data['url']);
        if (!isset($pair[1])) {
            RestoLogUtil::httpError(403);
        }
        
        /*
         * Only initiator of reset password can change its email
         */
        $splittedUrl = explode('/', $pair[0]);
        if (strtolower(base64_decode($splittedUrl[count($splittedUrl) - 1])) !== $email) {
            RestoLogUtil::httpError(403);
        }
        
        $query = RestoUtil::queryStringToKvps($pair[1]);
        if (!isset($query['_tk']) || !$this->context->dbDriver->check(RestoDatabaseDriver::SHARED_LINK, array('resourceUrl' => $pair[0], 'token' => $query['_tk']))) {
            RestoLogUtil::httpError(403);
        }
        
        if ($this->context->dbDriver->get(RestoDatabaseDriver::USER_PASSWORD, array('email' => $email)) === str_repeat('*', 40)) {
            RestoLogUtil::httpError(3004);
        }
        
        if ($this->context->dbDriver->update(RestoDatabaseDriver::USER_PROFILE, array('profile' => array('email' => $email, 'password' => $data['password'])))) {
            return RestoLogUtil::success('Password updated');
        }
        else {
            RestoLogUtil::httpError(400);
        }
        
    }
    
    /**
     * 
     * Process HTTP POST request on collections
     * 
     *    collections                                   |  Create a new {collection}            
     *    collections/{collection}                      |  Insert new product within {collection}
     * 
     * @param array $segments
     * @param array $data
     */
    private function POST_collections($segments, $data) {
        
        /*
         * No feature allowed
         */
        if (isset($segments[2])) {
            RestoLogUtil::httpError(404);
        }
        
        if (isset($segments[1])) {
            $collection = new RestoCollection($segments[1], $this->context, $this->user, array('autoload' => true));
        }
        
        /*
         * Check credentials
         */
        if ($segments[0] === 'search' 
                && !$this->user->canSearch(isset($collection) ? $collection->name : null)
        ) {
            RestoLogUtil::httpError(403);
        }

        /*
         * Create new collection
         */
        if (!isset($collection)) {
            return $this->POST_createCollection($data);
        }
        /*
         * Insert new feature in collection
         */
        else {
            return $this->POST_insertFeature($collection, $data);
        }
    }
    
    /**
     * Create collection from input data
     * @param array $data
     * @return type
     */
    private function POST_createCollection($data) {
        
        if (!isset($data['name'])) {
            RestoLogUtil::httpError(400);
        }
        if ($this->context->dbDriver->check(RestoDatabaseDriver::COLLECTION, array('collectionName' => $data['name']))) {
            RestoLogUtil::httpError(2003);
        }
        $collection = new RestoCollection($data['name'], $this->context, $this->user);
        $collection->loadFromJSON($data, true);
        $this->storeQuery('create', $data['name'], null);
        
        return RestoLogUtil::success('Collection ' . $data['name'] . ' created');
    }
    
    /**
     * Insert feature into collection 
     * 
     * @param RestoCollection $collection
     * @param array $data
     * @return type
     */
    private function POST_insertFeature($collection, $data) {
        $feature = $collection->addFeature($data);
        $this->storeQuery('insert', $collection->name, $feature->identifier);
        return RestoLogUtil::success('Feature ' . $feature->identifier . ' inserted within ' . $collection->name, array(
            'featureIdentifier' => $feature->identifier
        ));
    }
    
    /**
     * 
     * Process HTTP POST request on groups
     * 
     *    groups                                        |  Add a group
     * 
     * @param array $segments
     * @param array $data
     */
    private function POST_groups($data) {
        /*
         * Groups can only be create by admin
         */
        if (!$this->user->isAdmin()) {
            RestoLogUtil::httpError(403);
        }

        if(!isset($data['groupName'])) {
            RestoLogUtil::httpError(400);
        }
        
        if($this->context->dbDriver->store(RestoDatabaseDriver::GROUPS, array(
                'groupName' => $data['groupName'],
                'groupDescription' => isset($data['groupDescription']) ? $data['groupDescription'] : null,
                'groupCanWps' => $data['groupCanWps'],
                'groupProactiveId' => $data['groupProactiveId']
        ))) {
            return RestoLogUtil::success('Group ' . $data['groupName'] . ' created');
        } else {
            return RestoLogUtil::error('Cannot create group');
        }
    }
    
    /**
     * 
     * Process HTTP POST request on proactive accounts
     * 
     *    proactive                                        |  Add a proactive account
     * 
     * @param array $segments
     * @param array $data
     */
    private function POST_proactive($data)
    {
        if (!$this->user->isAdmin()) {
            RestoLogUtil::httpError(403);
        }

        if(!isset($data['accountLogin']) || !isset($data['accountPassword'])) {
            RestoLogUtil::httpError(400);
        }
        
        if($this->context->dbDriver->store(RestoDatabaseDriver::PROACTIVE, array(
            'accountLogin' => $data['accountLogin'],
            'accountPassword' => $data['accountPassword']
        ))) {
            return RestoLogUtil::success('Proactive account ' . $data['accountLogin'] . ' created');
        } else {
            return RestoLogUtil::error('Cannot create Proactive account');
        }
    }
    
    /**
     * 
     * Process HTTP POST request on users
     * 
     *    users                                         |  Add a user
     *    users/{userid}/cart                           |  Add new item in {userid} cart
     *    users/{userid}/orders                         |  Send an order for {userid}
     * 
     * @param array $segments
     * @param array $data
     */
    private function POST_users($segments, $data) {
        
        /*
         * update disallowed
         */
        if (isset($segments[3])) {
            RestoLogUtil::httpError(404);
        }
        
        /*
         * users
         */
        if (!isset($segments[1])) {
            return $this->POST_createUser($data);
        }
     
        /*
         * users/{userid}/cart
         */
        else if (isset($segments[2]) && $segments[2] === 'cart') {
            return $this->POST_userCart($segments[1], $data);
        }
      
        /*
         * users/{userid}/processingcart
         */
        else if (isset($segments[2]) && $segments[2] === 'processingcart') {
            return $this->POST_userProcessingCart($segments[1], $data);
        }
        
        /*
         * users/{userid}/orders
         */
        else if (isset($segments[2]) && $segments[2] === 'orders') {
            return $this->POST_userOrders($segments[1], $data);
        }
        
        /*
         * Unknown route
         */
        else {
            RestoLogUtil::httpError(404);
        }
        
    }
    
    /**
     * Create user
     * 
     * @param array $data
     */
    private function POST_createUser($data) {
        
        if (!isset($data['email'])) {
            RestoLogUtil::httpError(400, 'Email is not set');
        }
        
        if (!RestoUtil::isValidPassword($data['password'])) {
            RestoLogUtil::httpError(400, "Password must have at least eight characters and three of four character groups: 'upper-case', 'lowercase', 'digits', 'non-alphabetic'");
        }

        if ($this->context->dbDriver->check(RestoDatabaseDriver::USER, array('email' => $data['email']))) {
            RestoLogUtil::httpError(3000);
        }

        $redirect = isset($data['activateUrl']) ? '&redirect=' . rawurlencode($data['activateUrl']) : '';
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
                'instantdownload' => $this->context->instantDownloadLimit,
                'weeklydownload' => $this->context->weeklyDownloadLimit,
                'activated' => 0
            ))
        );
        if (isset($userInfo)) {
            $activationLink = $this->context->baseUrl . '/api/users/' . $userInfo['userid'] . '/activate?act=' . $userInfo['activationcode'] . $redirect;
            $fallbackLanguage = isset($this->context->mail['accountActivation'][$this->context->dictionary->language]) ? $this->context->dictionary->language : 'en';
            if (!$this->sendMail(array(
                        'to' => $data['email'],
                        'senderName' => $this->context->mail['senderName'],
                        'senderEmail' => $this->context->mail['senderEmail'],
                        'subject' => $this->context->dictionary->translate($this->context->mail['accountActivation'][$fallbackLanguage]['subject'], $this->context->title),
                        'message' => $this->context->dictionary->translate($this->context->mail['accountActivation'][$fallbackLanguage]['message'], $this->context->title, $activationLink)
                    ))) {
                RestoLogUtil::httpError(3001);
            }
        } else {
            RestoLogUtil::httpError(500, 'Database connection error');
        }

        return RestoLogUtil::success('User ' . $data['email'] . ' created');
    }
    
    /**
     * Process HTTP POST request on user cart
     * 
     *    users/{userid}/cart                           |  Add new items in {userid} cart
     * 
     * @param string $emailOrId
     * @param array $data
     * @throws Exception
     * @returns array - the execution status
     */
    private function POST_userCart($emailOrId, $data)
    {
        $availableFeatures = array();
        $response = array(
            "added" => 0,
            "alreadyExists" => 0,
            "unavailable" => 0,
            "norights" => 0
        );
        
        /*
         * Cart can only be modified by its owner or by admin
         */
        $user = $this->getAuthorizedUser($emailOrId);
        
        /*
         * Check if the maximum products in cart is exceeded
         */
        if (isset($this->context->cartMaxProducts) && $this->context->cartMaxProducts > 0) {
            $countCartItems = count($user->getCart()->getItems(false));
            $countDataItems = count($data);
            if ($countCartItems + $countDataItems > $this->context->cartMaxProducts) {
                return RestoLogUtil::httpError(1002, 'Cannot add item(s) in cart because the maximum of products is exceeded|'.$this->context->cartMaxProducts);
            }
        }
        
        /*
         * Get all features descriptions
         */
        $features = array();
        for ($i = count($data); $i--;) {
            if (!isset($data[$i]['id'])) {
                continue;
            }
            $features[] = $this->context->dbDriver->get(RestoDatabaseDriver::FEATURE_DESCRIPTION, array(
                    'context' => $this->context,
                    'user' => $this->user,
                    'featureIdentifier' => $data[$i]['id'],
                    'collection' => isset($data[$i]['collection']) ? new RestoCollection($data[$i]['collection'], $this->context, $this->user, array('autoload' => true)) : null
            ));
        }
        
        /*
         * Check if features are available to download
         */
        foreach($features as $feature) {
            // we validate all the possible elements to allow the product download
            $downloadState = $this->checkFeatureAvailability($feature);
            
            if ($downloadState !== true) {
                if ($downloadState['error'] === self::ERROR_BADRIGTHS) {
                    $response["norights"]++;
                } else {
                    $response["unavailable"]++;
                }
            } else {
                $availableFeatures[] = $feature;
            }
        }

        /*
         * Add all available features to cart
         */
        $addedFeatures = $user->addToCart($availableFeatures);
        foreach ($addedFeatures as $feature) {
            $response["added"]++;
        }
        
        /*
         * Fait un diff entre $availableFeatures et $addedFeatures pour obtenir
         * la liste des produits qui étaient déjà présent dans le panier
         */
        foreach ($availableFeatures as $availableFeature) {
            $itemId = RestoUtil::encrypt($user->profile['email'] . $availableFeature['id']);
            if (array_key_exists($itemId, $addedFeatures) !== true) {
                $response['alreadyExists']++;
            }
        }

        /*
         * Return the execution status
         */
        if ($addedFeatures !== false) {
            $cartItems = array_values($user->getCart()->getItems());
            return RestoLogUtil::success($response, array(
                'items' => $cartItems
            ));
        }
        else {
            return RestoLogUtil::error($response);
        }
    }

    /**
     * Process HTTP POST request on user processing cart
     *
     *    users/{userid}/processingcart                   |  Add new items in {userid} processing cart
     *
     * @param string $userid
     * @param array $data
     * 
     * @throws Exception
     * @returns array - the execution status
     */
    private function POST_userProcessingCart($userid, $data)
    {
        $availableFeatures = array();
        $response = array(
            "added" => 0,
            "alreadyExists" => 0,
            "unavailable" => 0,
            "norights" => 0
        );
    
        /*
         * Cart can only be modified by its owner or by admin
         */
        $user = $this->getAuthorizedUser($userid);
    
        /*
         * Check if the maximum products in processing cart is exceeded
         */
        if (isset($this->context->processingCartMaxProducts) && $this->context->processingCartMaxProducts > 0) {
            $countCartItems = count($user->getProcessingCart()->getItems());
            $countDataItems = count($data);
            if ($countCartItems + $countDataItems > $this->context->processingCartMaxProducts) {
                return RestoLogUtil::httpError(6002, 'Cannot add item(s) in processing cart because the maximum of products is exceeded|'.$this->context->processingCartMaxProducts);
            }
        }
        
        /*
         * Get all features descriptions
         */
        $features = array();
        for ($i = count($data); $i--;) {
            if (!isset($data[$i]['id'])) {
                continue;
            }
            $features[] = $this->context->dbDriver->get(RestoDatabaseDriver::FEATURE_DESCRIPTION, array(
                'context' => $this->context,
                'user' => $this->user,
                'featureIdentifier' => $data[$i]['id'],
                'collection' => isset($data[$i]['collection']) ? new RestoCollection($data[$i]['collection'], $this->context, $this->user, array('autoload' => true)) : null
            ));
        }
        
        /*
         * Check if features are available to download
         */
        foreach($features as $feature) {
            $downloadState = $this->checkFeatureAvailability($feature);
            
            if ($downloadState !== true) {
                if ($downloadState['error'] === self::ERROR_BADRIGTHS) {
                    $response["norights"]++;
                } else {
                    $response["unavailable"]++;
                }
            } else {
                $availableFeatures[] = $feature;
            }
        }
        
        /*
         * Add all features to processing cart (ignores those that already exist) 
         */
        $addedFeatures = $user->addToProcessingCart($availableFeatures);
        foreach ($addedFeatures as $feature) {
            $response["added"]++;
        }
        
        /*
         * Fait un diff entre $availableFeatures et $addedFeatures pour obtenir
         * la liste des produits qui étaient déjà présent dans le panier
         */
        foreach ($availableFeatures as $availableFeature) {
            if (array_key_exists($availableFeature['id'], $addedFeatures) !== true) {
                $response['alreadyExists']++;
            }
        }
        
        /*
         * Returns the execution status
         */
        if ($addedFeatures !== false) {
            $cartItems = array_values($user->getProcessingCart()->getItems());
            return RestoLogUtil::success($response, array(
                'items' => $cartItems
            ));
        }
        else {
            return RestoLogUtil::error($response);
        }
    }
    
    /**
     * Process HTTP POST request on user orders
     * 
     *    users/{userid}/orders                         |  Send an order for {userid}
     * 
     * @param string $emailOrId
     * @param array $data
     * @throws Exception
     */
    private function POST_userOrders ($emailOrId, $data)
    {
        $user = $this->getAuthorizedUser($emailOrId);
        
        $downloadLimits = $user->getDownloadLimits();

        // retrieve all order items
        $items = array();
        $fromCart = isset($this->context->query['_fromCart']) && filter_var($this->context->query['_fromCart'], FILTER_VALIDATE_BOOLEAN);
        $items = $fromCart ? $this->context->dbDriver->get(RestoDatabaseDriver::CART_ITEMS, array('email' => $user->profile['email'])) : $data;
        
        // refresh user profile
        $user->profile = $this->context->dbDriver->get(RestoDatabaseDriver::USER_PROFILE, array('email' => $user->profile['email']));
        
        // get the total of products ordered by the user for the last week
        $weeklyDownloaded = $this->context->dbDriver->get(RestoDatabaseDriver::USER_DOWNLOADED_WEEKLY, array('identifier' => $user->profile['email']));
        
        // check weekly download limit
        if ($downloadLimits['weeklyLimitDownload'] > 0 && $weeklyDownloaded + count($items) > $downloadLimits['weeklyLimitDownload']) {
            return RestoLogUtil::httpError(420, "You can't download more than " . $downloadLimits['weeklyLimitDownload'] . "products per week, please wait some days, or contact our administrator");
        }
        
        // check instant download limit
        if ($downloadLimits['instantLimitDownload'] > 0 && count($items) > $downloadLimits['instantLimitDownload']) {
            return RestoLogUtil::httpError(421, "You can't download more than " . $downloadLimits['instantLimitDownload'] . " products at once, please remove some products, or contact our administrator");
        }
        
        // Try to place order
    	$order = $user->placeOrder($data);
    	
        if ($order) {
            return RestoLogUtil::success('Place order', array(
                'order' => $order
            ));
        }
        else {
            return RestoLogUtil::error('Cannot place order');
        }
    }
}
