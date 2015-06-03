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
     *    groups/{groupid}                              |  Update {groupid}
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
            case 'groups':
                return $this->PUT_groups($segments, $data);
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
     * Process HTTP PUT request on groups
     * 
     *    groups/{groupid}                              |  Update {groupid}
     * 
     * @param array $segments
     * @param array $data
     */
    private function PUT_groups($segments, $data) {

        if (isset($segments[1])) {
            /*
             * Groups can only be update by admin
             */
            if ($this->user->profile['groupname'] !== 'admin') {
                RestoLogUtil::httpError(403);
            }
        
            if($this->context->dbDriver->update(RestoDatabaseDriver::GROUPS, array(
                    "groupId" => $segments[1],
                    "groupName" => $data["groupName"],
                    "groupDescription" => $data["groupDescription"]
            ))) {
                return RestoLogUtil::success('Group ' . $data['groupName'] . ' updated');
            } else {
            RestoLogUtil::httpError(5000, 'Cannot update group, it does not exist');
            }
        }
        else {
            RestoLogUtil::httpError(404);
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
         * Cart can only be modified by its owner or by admin
         */
        $user = $this->getAuthorizedUser($emailOrId);
        
    	$data['id'] = $user->profile["userid"];
    	
    	if ($this->context->dbDriver->update(RestoDatabaseDriver::USER_PROFILE, array('profile' => $data))) {
    	    return RestoLogUtil::success('User updated');
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
        $user = $this->getAuthorizedUser($emailOrId);
         
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
