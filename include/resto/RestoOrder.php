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

class RestoOrder{
    
    /*
     * Context
     */
    public $context;
    
    /*
     * Owner
     */
    public $user;
    
    /*
     * Order items
     *  array(
     *      'url' //
     *      'size'
     *      'checksum'
     *      'mimeType'
     *  )
     */
    private $order = array();
    
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
    public function __construct($user, $context, $orderId){
        $this->user = $user;
        $this->context = $context;
        $this->order = $this->getCompleteOrders($orderId);
    }

    /**
     * Retrieve all order features.
     */
    public function getCompleteOrders($orderId) {
        $userOrder = $this->context->dbDriver->get(RestoDatabaseDriver::ORDERS, array('email' => $this->user->profile['email'], 'orderId' => $orderId));
    
        $features = array();
        $postData = array();
        $storageInfos = array();
        
        foreach ($userOrder['items'] as $item) {
            $feature = $this->context->dbDriver->get(RestoDatabaseDriver::FEATURE_DESCRIPTION, array('context' => $this->context, 'user' => $this->user, 'featureIdentifier' => $item['id']));
            $features[] = $feature;
            // If NRT, stoage mode is disk
            if (isset($feature['properties']['isNrt']) && $feature['properties']['isNrt'] == 1){
                $storageInfos[$feature['properties']['title']] = array('storage' => self::STORAGE_MODE_DISK);
                continue;
            }
            if (isset($feature['properties']['hpssResource'])) {
                $postData[] = $feature['properties']['hpssResource'];
            }
        }
        
        if (!empty($postData)) {
            $postData = $this->getStorageInfo($postData);
        }
        $storageInfos = array_merge($storageInfos, $postData);
        
        for ($i = 0, $l = count($features); $i < $l; $i++) {
            $name = $features[$i]['properties']['title'];
            $features[$i]['properties']['storage'] = array('mode' => isset($storageInfos[$name]['storage'])
                ? $storageInfos[$name]['storage'] : self::STORAGE_MODE_UNKNOWN);
        }
        
        $userOrder['items'] = $features;
        return $userOrder;
    }
    
    /**
     * Return the cart as a JSON file
     * 
     * @param boolean $pretty
     */
    public function toJSON($pretty) {
        return  RestoUtil::json_format(array(
            'status' => 'success',
            'message' => 'Order ' . $this->order['orderId'] . ' for user ' . $this->user->profile['email'],
            'order' => $this->order
        ), $pretty);
    }
    
    /**
     * Return the cart as a metalink XML file
     * 
     * Warning ! a link is created only for resource that can be downloaded by users
     */
    public function toMETA4() {
        
        $meta4 = new RestoMetalink($this->context);
        
        /*
         * One metalink file per item - if user has rights to download file
         */
        foreach ($this->order['items'] as $item) {
           
            /*
             * Invalid item
             */
            if (!isset($item['properties']) || !isset($item['properties']['services']) || !isset($item['properties']['services']['download'])) {
                continue;
            }
        
            /*
             * Item not downloadable
             */
            if (!isset($item['properties']['services']['download']['url']) || !RestoUtil::isUrl($item['properties']['services']['download']['url'])) {
                continue;
            }
            
            $exploded = parse_url($item['properties']['services']['download']['url']);
            $segments = explode('/', $exploded['path']);
            $last = count($segments) - 1;
            if ($last > 2) {
                list($modifier) = explode('.', $segments[$last], 1);
                if ($modifier !== 'download' || !$this->user->canDownload($segments[$last - 2], $segments[$last - 1])) {
                    continue;
                }
            }
            
            /*
             * Add link
             */
            $meta4->addLink($item, $this->user->profile['email']);
        }
           
        return $meta4->toString();
        
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
