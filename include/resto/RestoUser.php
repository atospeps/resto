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

class RestoUser {
    
    /*
     * User profile
     */
    public $profile;
    
    /*
     * Context
     */
    public $context;
    
    /*
     * Current JWT token
     */
    public $token = null;
    
    /*
     * User cart
     */
    private $cart;
    
    /*
     * User processing cart
     */
    private $processingCart;
    
    /*
     * Resto rights
     */
    private $rights;
    
    /**
     * Constructor
     * 
     * @param array $profile : User profile
     * @param RestoContext $context
     */
    public function __construct($profile, $context) {
        
        $this->context = $context;
        
        /*
         * Assign default profile for unauthentified user
         */
        if (!isset($profile) || !isset($profile['userid'])) {
            $this->profile = array(
                'userid' => -1,
                'groupname' => 'unregistered',
                'activated' => 0
            );
        }
        else {
            $this->profile = $profile;
        }
        
        /*
         * Set rights and cart for identified user
         */
        if ($this->profile['userid'] === -1) {
            $this->rights = new RestoRights('unregistered', 'unregistered', $this->context);
        }
        else {
            $this->rights = new RestoRights($this->profile['email'], $this->profile['groupname'], $this->context);
        }
        
    }
    
    /**
     * Returns rights for collection and/or identifier
     * 
     * @param string $collectionName
     * @param string $featureIdentifier
     */
    public function getRights($collectionName = null, $featureIdentifier = null) {
        return $this->profile['activated'] === 0 ? $this->rights->groupRights['unregistered'] : $this->rights->getRights($collectionName, $featureIdentifier);
    }
    
    /**
     * Returns full rights for collection and/or identifier
     * 
     * @param string $collectionName
     * @param string $featureIdentifier
     */
    public function getFullRights($collectionName = null, $featureIdentifier = null) {
        return $this->profile['activated'] === 0 ? array('*' => $this->rights->groupRights['unregistered']) : $this->rights->getFullRights($collectionName, $featureIdentifier);
    }
    
    /**
     * Store user query to database
     * 
     * @param string $method
     * @param string $service
     * @param string $collectionName
     * @param string $featureIdentifier
     * @param array $query
     * @param string $url
     */
    public function storeQuery($method, $service, $collectionName, $featureIdentifier, $query, $url){
        try {
            $remoteAdress = $this->getIp();
            $this->context->dbDriver->store(RestoDatabaseDriver::QUERY, array(
                'userid' => $this->profile['userid'],
                'query' => array(
                    'method' => $method,
                    'service' => $service,
                    'collection' => $collectionName,
                    'resourceid' => $featureIdentifier,
                    'query' => $query,
                    'url' => $url,
                    'ip' => $remoteAdress,
                ))
            );
        } catch (Exception $e) {}
    }
    
    /**
     * Can User visualize ?
     * 
     * @param string $collectionName
     * @param string $featureIdentifier
     * @param string $token
     * @return boolean
     */
    public function canVisualize($collectionName = null, $featureIdentifier = null, $token = null){
        return $this->canDownloadOrVisualize('visualize', $collectionName, $featureIdentifier, $token);
    }
    
    /**
     * Can User download ? 
     * 
     * @param string $collectionName
     * @param string $featureIdentifier
     * @param string $token
     * @return boolean
     */
    public function canDownload($collectionName = null, $featureIdentifier = null, $token = null){
        return $this->canDownloadOrVisualize('download', $collectionName, $featureIdentifier, $token);
    }
    
    /**
     * Can User execute WPS service ?
     * @return Ambigous <multitype:number >
     */
    public function canExecuteWPS(){
        $rights = $this->rights->getRights();
        return $rights['wps'];
    }

    /**
     * Can User POST ?
     * 
     * @param string $collectionName
     * @return boolean
     */
    public function canPost($collectionName = null){
        $rights = $this->rights->getRights($collectionName);
        return $rights['post'];
    }
    
    /**
     * Can User PUT ?
     * 
     * @param string $collectionName
     * @param string $featureIdentifier
     * @return boolean
     */
    public function canPut($collectionName, $featureIdentifier = null){
        $rights = $this->rights->getRights($collectionName, $featureIdentifier);
        return $rights['put'];
    }
    
    /**
     * Can User DELETE ?
     * 
     * @param string $collectionName
     * @param string $featureIdentifier
     * @return boolean
     */
    public function canDelete($collectionName, $featureIdentifier = null){
        $rights = $this->rights->getRights($collectionName, $featureIdentifier);
        return $rights['delete'];
    }
    
    /**
     * Disconnect user
     */
    public function disconnect() {
        if (!$this->context->dbDriver->execute(RestoDatabaseDriver::DISCONNECT_USER, array('token' => $this->token))) {
            return false;
        }
        return true;
    }
    
    /**
     * Return user cart
     */
    public function getCart() {
        return RestoCart::getInstance($this, $this->context);
    }
    
    /**
     * Add item to cart
     * 
     * @param array $data
     */
    public function addToCart($data) {
        return $this->getCart()->add($data);
    }
    
    /**
     * Add item to cart
     * 
     * @param string $itemId
     * @param array $item
     */
    public function updateCart($itemId, $item) {
        return $this->getCart()->update($itemId, $item);
    }
    
    /**
     * Remove item from cart
     * 
     * @param string $itemId
     */
    public function removeFromCart($itemId) {
        return $this->getCart()->remove($itemId);
    }
    
    /**
     * Clear cart
     */
    public function clearCart() {
        return $this->getCart()->clear();
    }
    
    /**
     * Return user orders
     */
    public function getOrders() {
        return $this->context->dbDriver->get(RestoDatabaseDriver::ORDERS, array(
                'email' => $this->profile['email']
        ));
    }
    
    /**
     * Place order
     * 
     * @param array $data
     */
    public function placeOrder($data) {
        $fromCart = isset($this->context->query['_fromCart']) ? filter_var($this->context->query['_fromCart'], FILTER_VALIDATE_BOOLEAN) : false;
        if ($fromCart) {
            $order = $this->context->dbDriver->store(RestoDatabaseDriver::ORDER, array('email' => $this->profile['email']));
            if (isset($order)) {
                $this->getCart()->clear();
            }
        }
        else {
            $order = $this->context->dbDriver->store(RestoDatabaseDriver::ORDER, array('email' => $this->profile['email'], 'items' => $data));
        }
        return $order;
    }
    
    /**
     * Can User download or visualize 
     * 
     * @param string $action
     * @param string $collectionName
     * @param string $featureIdentifier
     * @param string $token
     * @return boolean
     */
    private function canDownloadOrVisualize($action, $collectionName = null, $featureIdentifier = null, $token = null)
    {
        /*
         * Token case - bypass user rights
         */
        if (isset($token)) {
            if (!isset($collectionName) || !isset($featureIdentifier)) {
                return false;
            }
            if ($this->context->dbDriver->check(RestoDatabaseDriver::SHARED_LINK, array('resourceUrl' => $this->context->baseUrl . '/' . $this->context->path, 'token' => $token))) {
                return true;
            }
        }
        
        /*
         * Normal case - checke user rights
         */
        $rights = $this->rights->getRights($collectionName, $featureIdentifier);
        return $rights[$action];
    }
    
    /**
     * Return user processing cart
     */
    public function getProcessingCart() {
        return RestoProcessingCart::getInstance($this, $this->context);
    }
    
    /**
     * Add item to processing cart
     * 
     * @param array $data
     */
    public function addToProcessingCart($data) {
        return $this->getProcessingCart()->add($data);
    }
    
    /**
     * Update item from processing cart
     * 
     * @param string $itemId
     * @param array $item
     */
    public function updateProcessingCart($itemId, $item) {
        return $this->getProcessingCart()->update($itemId, $item);
    }
    
    /**
     * Remove item from processing cart
     * 
     * @param string $itemId
     */
    public function removeFromProcessingCart($itemId) {
        return $this->getProcessingCart()->remove($itemId);
    }
    
    /**
     * Clear processing cart
     * 
     */
    public function clearProcessingCart() {
        return $this->getProcessingCart()->clear();
    }
    
    /**
     * We get the ip.
     * We try differents values to get the true ip beyond any firewall
     *
     * @return string
     */
    private function getIp()
    {
        // Variables to verify. We verify them by order,
        // so at the start we sure get REMOTE_ADD, which is
        // the firewall ip
        $variables = array (
            'REMOTE_ADDR',
            'HTTP_FORWARDED',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED', 
            'HTTP_X_FORWARDED_FOR'
        );

        // If the value is set, we store it
        $ipaddress = '';
        foreach ($variables as $key => $variable) {
            if (filter_input(INPUT_SERVER, $variable, FILTER_SANITIZE_STRING) !== FALSE && !is_null(filter_input(INPUT_SERVER, $variable, FILTER_SANITIZE_STRING))) {
                $ipaddress = filter_input(INPUT_SERVER, $variable, FILTER_SANITIZE_STRING);
            }
        }
        
        return $ipaddress;
    }
    
}

