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

abstract class RestoModule{
    
    /*
     * Resto context
     */
    protected $context;
    
    /*
     * Resto user
     */
    protected $user;
    
    /*
     * Modules options
     */
    protected $options;
    
    /*
     * Indicates if database connection should 
     * be closed
     */
    protected $closeDbh = false;
    
    /**
     * Constructor
     * 
     * @param RestoContext $context
     * @param RestoUser $user
     */
    public function __construct($context, $user) {
        $this->context = $context;
        $this->user = $user;
        $this->options = $this->context->modules[get_class($this)];
    }
    
    /**
     * Set the database handler from config.php
     * 
     * @param array $config
     * @throws Exception
     */
    protected function getDatabaseHandler() {
    
        /*
         * Set database handler from configuration
         */
        if (isset($this->options['database'])) {
            $dbh = $this->context->dbDriver->get(RestoDatabaseDriver::HANDLER, $this->options['database']);
        }

        /*
         * Get default database handler 
         */
        if (!isset($dbh)) {
            $dbh = $this->context->dbDriver->dbh;
        }
        
        return $dbh;
    }

    /**
     * Store query to database
     * 
     * @param string $serviceName
     * @param string $collectionName
     */
    protected function storeQuery($serviceName, $collectionName, $featureIdentifier) {
        if ($this->context->storeQuery === true && isset($this->user)) {
            $this->user->storeQuery($this->context->method, $serviceName, isset($collectionName) ? $collectionName : null, isset($featureIdentifier) ? $featureIdentifier : null, $this->context->query, $this->context->getUrl());
        }
    }
    
    /**
     * Run module - this function should be called by Resto.php
     * 
     * @param array $elements : route elements
     * @param array $data : request data
     * @return string : result from run process in the $context->outputFormat
     */
    abstract public function run($elements, $data = array()); 
}

