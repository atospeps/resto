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
class RestoCart{
    
    /*
     * Context
     */
    public $context;
    
    /*
     * Owner of the cart
     */
    public $user;
    
    /*
     * Cart items 
     */
    private $items = array();
    
    /*
     * RestoCart instance.
     */
    private static $_instance = null;
    
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
     * @param RestoUser $user
     * @param RestoContext $context
     */
    private function __construct($user, $context){
        $this->user = $user;
        $this->context = $context;
        $this->items = $this->context->dbDriver->get(RestoDatabaseDriver::CART_ITEMS, array(
            'email' => $this->user->profile['email']
        ));
    }

    /**
     * 
     * Returns RestoCart instance (singleton).
     * @param RestoUser $user
     * @param RestoContext $context
     */
    public static function getInstance($user, $context){
        if(is_null(self::$_instance)) {
            self::$_instance = new RestoCart($user, $context);
        }
        return self::$_instance;
    }
    
    /**
     * Add items to cart
     * 
     * $data should be an array of array.
     * 
     * Structure :
     *      array(
     *          array(
     *              'id' => //featureidentifier
     *             'properties' => array(
     *              
     *              )
     *          ),
     *          array(
     * 
     *          ),
     *          ...
     *      )
     * 
     * @param array $data
     * @return array $items les produits réellement ajoutés au panier
     */
    public function add($data) {
        
        if (!is_array($data)) {
            return false;
        }
                    
        $items = array();
        for ($i = count($data); $i--;) {
                    
            if (!isset($data[$i]['id'])) {
                continue;
            }
            
            /*
             * Same resource cannot be added twice
             */
            $itemId = RestoUtil::encrypt($this->user->profile['email'] . $data[$i]['id']);
            if (isset($this->items[$itemId])) {
                continue;
            }
            
            if (!$this->context->dbDriver->store(RestoDatabaseDriver::CART_ITEM, array(
                'email' => $this->user->profile['email'],
                'item'  => $data[$i]))
            ) {
                return false;
            }
            $this->items[$itemId] = $data[$i];
            $items[$itemId] = $data[$i];
        }
        
        return $items;
    }
    
    /**
     * Update item in cart
     * 
     * @param string $itemId
     * @param array $item
     * 
     */
    public function update($itemId, $item) {
        if (!isset($itemId)) {
            return false;
        }
        if (!isset($this->items[$itemId])) {
            RestoLogUtil::httpError(1001, 'Cannot update item : ' . $itemId . ' does not exist');
        }
        $this->items[$itemId] = $item;
        return $this->context->dbDriver->update(RestoDatabaseDriver::CART_ITEM, array(
                'email' => $this->user->profile['email'], 
                'itemId' => $itemId, 'item' => $item
        ));
    }

    /**
     * Remove items from cart
     * @param array $data
     * @return boolean|unknown[]
     */
    public function remove($data) {
        
        if (!is_array($data)) {
            return false;
        }
        $items = array();
        for ($i = count($data); $i--;) {

            if (!isset($data[$i]['id'])) {
                continue;
            }
            $itemId = RestoUtil::encrypt($this->user->profile['email'] . $data[$i]['id']);
            if (isset($this->items[$itemId])) {
                unset($this->items[$itemId]);
                $items[] = $itemId;
            }
        }
        return $this->context->dbDriver->remove(RestoDatabaseDriver::CART_ITEM, array(
            'email' => $this->user->profile['email'],
            'items' => $items
        ));
    }
    
    /**
     * Remove all items from cart 
     */
    public function clear() {
        $this->items = array();
        return $this->context->dbDriver->remove(RestoDatabaseDriver::CART_ITEMS, array(
                'email' => $this->user->profile['email']
        ));
    }
    
    /**
     * Returns all items from cart
     */
    public function getItems($storageInfo=true) {
            
        if ($storageInfo === true){
            
            // get storage info
            $storageInfos = array();
            $postData = [];
            foreach ($this->items as $id => $item) {
                $feature = new RestoFeature($this->context, $this->user, array (
                    'featureArray' => $item,
                ));
                $this->items[$id] = $feature->toArray(true);
                                
                if ($feature->get('isNrt') == 1){
                    $storageInfos[$feature->get('title')] = array('storage' => self::STORAGE_MODE_DISK);
                    continue;
                }
                $hpssRes = $feature->get('hpssResource');
                if (!empty($hpssRes)){
                    $postData[] = $hpssRes;
                }
                
            }
            if (!empty($postData)) {
                $postData = $this->getStorageInfo($postData);
            }
            $storageInfos = array_merge($storageInfos, $postData);
            
            foreach ($this->items as $id => &$item) {
                $name = $item['properties']['title'];
                $item['properties']['storage'] = array('mode' => isset($storageInfos[$name]['storage'])
                    ? $storageInfos[$name]['storage'] : self::STORAGE_MODE_UNKNOWN);
            }
        }

        return $this->items;
    }
    
    /**
     * Return the cart as a JSON file
     * 
     * @param boolean $pretty
     */
    public function toJSON($pretty) {
        $response = array('items' => $this->getItems());
        if (isset($this->context->query['getMaxProducts'])) {
            $response['maxProducts'] = $this->context->cartMaxProducts;
        }
        return RestoUtil::json_format($response, $pretty);
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
            
            // curl opts
            $opts = array (
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_POST => 1,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_POSTFIELDS => implode(' ', $data),
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => 0,
                CURLOPT_TIMEOUT => $timeout,
            );
            
            // user curl options
            if (!empty($this->context->hpssRestApi['curlOpts']) && is_array($this->context->hpssRestApi['curlOpts'])){
                foreach ($this->context->hpssRestApi['curlOpts'] as $key => $value){
                    $opts[$key] = $value;
                }
            }
            curl_setopt_array($curl, $opts);
            
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
