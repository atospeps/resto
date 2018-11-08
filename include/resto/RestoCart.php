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
        // get storage info
        foreach ($this->items as $id => $item) {
            $feature = new RestoFeature($context, $this->user, array (
                'featureArray' => $item,
                'overwriteStorageMode' => true
            ));
            $this->items[$id] = $feature->toArray();
        }
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
     * Remove item from cart
     * 
     * @param string $itemId
     */
    public function remove($itemId) {
        
        if (isset($itemId) === false) {
            return false;
        }
        
        $itemCartId = RestoUtil::encrypt($this->user->profile['email'] . $itemId);
        if (isset($this->items[$itemCartId])) {
            unset($this->items[$itemCartId]);
        }
        
        return $this->context->dbDriver->remove(RestoDatabaseDriver::CART_ITEM, array(
                'email' => $this->user->profile['email'], 
                'itemId' => $itemCartId
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
    public function getItems() {
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

}
