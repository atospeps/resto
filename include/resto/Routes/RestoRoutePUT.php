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
 * RESTo REST router for PUT requests
 */
class RestoRoutePUT extends RestoRoute {
    
    /**
     * Constructor
     */
    public function __construct($context, $user) {
        parent::__construct($context, $user);
    }
   
    /**
     * 
     * Process HTTP PUT request
     *  
     *    collections/{collection}                      |  Update {collection}
     *    collections/{collection}/{feature}            |  Update {feature}
     *    
     *    users/{userid}                                |  Update {userid} information
     *    users/{userid}/cart/{itemid}                  |  Modify item in {userid} cart
     *    
     * @param array $segments
     */
    public function route($segments) {
        
        /*
         * Input data is mandatory for PUT request
         */
        $data = RestoUtil::readInputData($this->context->uploadDirectory);
        if (!is_array($data) || count($data) === 0) {
            RestoLogUtil::httpError(400);
        }

        switch($segments[0]) {
            case 'collections':
                return $this->PUT_collections($segments, $data);
            case 'users':
                return $this->PUT_users($segments, $data);
            default:
                return $this->processModuleRoute($segments, $data);
        }
        
    }
    
    /**
     * 
     * Process HTTP PUT request on collections
     * 
     *    collections/{collection}                      |  Update {collection}
     *    collections/{collection}/{feature}            |  Update {feature}
     * 
     * @param array $segments
     * @param array $data
     */
    private function PUT_collections($segments, $data) {
        
        /*
         * {collection} is mandatory and no modifier is allowed
         */
        if (!isset($segments[1]) || isset($segments[3])) {
            RestoLogUtil::httpError(404);
        }
        
        $collection = new RestoCollection($segments[1], $this->context, $this->user, array('autoload' => true));
        $featureIdentifier = isset($segments[2]) ? $segments[2] : null;
        if (isset($featureIdentifier)) {
            $feature = new RestoFeature($this->context, $this->user, array(
                'featureIdentifier' => $featureIdentifier,
                'collection' => $collection
            ));
            if (!$feature->isValid()) {
                RestoLogUtil::httpError(404);
            }
        }
        
        /*
         * Check credentials
         */
        if (!$this->user->canPut($collection->name, $featureIdentifier)) {
            RestoLogUtil::httpError(403);
        }

        /*
         * collections/{collection}
         */
        if (!isset($feature)) {
            $collection->loadFromJSON($data, true);
            $this->storeQuery('update', $collection->name, null);
            return RestoLogUtil::success('Collection ' . $collection->name . ' updated');
        }
        /*
         * collections/{collection}/{feature}
         */
        else {
            //$this->storeQuery('update', $collection->name, $featureIdentifier);
            RestoLogUtil::httpError(501);
        }
        
    }
    
    
    /**
     * 
     * Process HTTP PUT request on users
     * 
     *    users/{userid}                                |  Update {userid}
     *    users/{userid}/cart/{itemid}                  |  Modify item in {userid} cart
     * 
     * @param array $segments
     * @param array $data
     */
    private function PUT_users($segments, $data) {
        /*
         * users/{userid}
         */
    	if (!isset($segments[2])) {
            return $this->PUT_userProfile($segments[1], $data);
    	}
    	
        /*
         * Mandatory {itemid}
         */
        if ($segments[2] === 'cart' && isset($segments[3])) {
            return $this->PUT_userCart($segments[1], $segments[3], $data);
        }
        
        /*
         * If no route is found.
         */
        RestoLogUtil::httpError(404);
        
        
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
    	/*
    	 * User can only be modified by admin
    	 */
    	$user = $this->user;
    	$userid = $this->userid($emailOrId);
	    if ($user->profile['groupname'] !== 'admin') {
	        RestoLogUtil::httpError(403);
	    } else if(isset($data['instantdownloadvolume']) && isset($data['weeklydownloadvolume'])) {
	    	if ($this->context->dbDriver->update(RestoDatabaseDriver::USER_PROFILE, array('profile' => array('email' => $emailOrId,
	    			 'instantdownloadvolume' => $data['instantdownloadvolume'],
	    			 'weeklydownloadvolume' => $data['weeklydownloadvolume'])))) {
	    	    return RestoLogUtil::success('User updated');
	    	}
    	}
        RestoLogUtil::httpError(400);
    }
    
    
    /**
     * 
     * Process HTTP PUT request on users cart
     * 
     *    users/{userid}/cart/{itemid}                  |  Modify item in {userid} cart
     * 
     * @param string $emailOrId
     * @param string $itemId
     * @param array $data
     */
    private function PUT_userCart($emailOrId, $itemId, $data) {
        
        /*
         * Cart can only be modified by its owner or by admin
         */
        $user = $this->user;
        $userid = $this->userid($emailOrId);
        if ($user->profile['userid'] !== $userid) {
            if ($user->profile['groupname'] !== 'admin') {
                RestoLogUtil::httpError(403);
            }
            else {
                $user = new RestoUser($this->context->dbDriver->get(RestoDatabaseDriver::USER_PROFILE, array('userid' => $userid)), $this->context);
            }
        }
         
        if ($user->updateCart($itemId, $data, true)) {
            return RestoLogUtil::success('Item ' . $itemId . ' updated', array(
                'itemId' => $itemId,
                'item' => $data
            ));
        }
        else {
            return RestoLogUtil::error('Cannot update item ' . $itemId);
        }
        
    }
    
}
