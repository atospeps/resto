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
 * RESTo PostgreSQL processing cart functions
 */
class Functions_processingcart
{
    private $dbDriver = null;
    private $dbh = null;
    
    /**
     * Constructor
     * 
     * @param array $config
     * @param RestoCache $cache
     * 
     * @throws Exception
     */
    public function __construct($dbDriver)
    {
        $this->dbDriver = $dbDriver;
        $this->dbh = $dbDriver->dbh;
    }
    
    /**
     * Return processing cart for user
     * 
     * @param array $context
     * @param array $user
     * 
     * @return array
     * @throws exception
     */
    public function getProcessingCartItems($context, $user)
    {
        $items = array();
        
        if (!isset($context) || !isset($user)) {
            return $items;
        }
        
        $query = "SELECT p.itemid, f.collection "
               . "FROM usermanagement.processingcart AS p "
               . "INNER JOIN resto.features AS f ON p.itemid = f.identifier "                 //-> the row is ignored if the feature does not exists
               . "WHERE p.userid = '" . pg_escape_string($user->profile['userid']) . "'";
        
        $results = $this->dbDriver->query($query, 500, 'Cannot get processing cart items');
        
        // get all features descriptions
        $features = array();
        while ($result = pg_fetch_assoc($results)) {
            $features[$result['itemid']] = $context->dbDriver->get(RestoDatabaseDriver::FEATURE_DESCRIPTION, array(
                'context'           => $context,
                'user'              => $user,
                'featureIdentifier' => $result['itemid'],
                'collection'        => isset($result['collection']) ? new RestoCollection($result['collection'], $context, $user, array('autoload' => true)) : null
            ));
        }
        
        return $features;
    }
    
    /**
     * Return true if resource is within processing cart
     * 
     * @param string $itemId
     * 
     * @return boolean
     * @throws exception
     */
    public function isInProcessingCart($itemId)
    {
        if (!isset($itemId)) {
            return false;
        }
        
        $query = 'SELECT 1 FROM usermanagement.processingcart '
               . 'WHERE itemid = \'' . pg_escape_string($itemId) . '\'';
        
        $results = $this->dbDriver->fetch($this->dbDriver->query(($query)));
        
        return !empty($results);
    }
    
    /**
     * Add one item to processing cart
     * 
     * @param string $userid
     * @param array $item
     *   
     * @return boolean
     * @throws exception
     */
    public function addToProcessingCart($userid, $item)
    {
        if (!isset($userid) || !isset($item) || !isset($item['id'])) {
            return false;
        }
        
        $values = array(
            '\'' . pg_escape_string($item['id']) . '\'',
            '\'' . pg_escape_string($item['properties']['title']) . '\'',
            '\'' . pg_escape_string($userid) . '\'',
            'now()'
        );
        
        $query = 'INSERT INTO usermanagement.processingcart (itemid, title, userid, querytime) '
               . 'VALUES (' . join(',', $values) . ')';
        
        $this->dbDriver->query($query);
        
        return array($item['id'] => $item);
    }
    
    /**
     * Update processing cart
     * 
     * @param string $identifier
     * @param string $itemId
     * @param array $item
     *   
     *   Must contain at least a 'url' entry
     *   
     * @return boolean
     * @throws exception
     */
    public function updateProcessingCart($userid, $itemId, $item)
    {
        if (!isset($userid) || !isset($itemId) || !isset($item) || !is_array($item) || !isset($item['url'])) {
            return false;
        }
        if (!$this->isInProcessingCart($itemId)) {
            RestoLogUtil::httpError(1001, 'Cannot update item : ' . $itemId . ' does not exist');
        }
        
        $query = 'UPDATE usermanagement.processingcart '
               . 'SET item = \''. pg_escape_string(json_encode($item)) . '\', querytime=now() '
               . 'WHERE userid = \'' . pg_escape_string($userid) . '\' '
               . 'AND itemid = \'' . pg_escape_string($itemId) . '\'';
        
        $this->dbDriver->query($query);
        
        return true;
    }
    
    /**
     * Remove all items from processing cart for user $identifier
     * 
     * @param string $userid
     * 
     * @return boolean
     * @throws exception
     */
    public function clearProcessingCart($userid)
    {
        if (!isset($userid)) {
            return false;
        }
        
        $query = 'DELETE FROM usermanagement.processingcart '
               . 'WHERE userid = \'' . pg_escape_string($userid) . '\'';
        
        $this->dbDriver->query($query, 500, 'Cannot clear processing cart');
        
        return true;
    }
    
    /**
     * Remove resource from processing cart
     * 
     * @param string $userid
     * @param string $itemId
     * 
     * @return boolean
     * @throws exception
     */
    public function removeFromProcessingCart($userid, $itemId)
    {
        if (!isset($userid) || !isset($itemId)) {
            return false;
        }
        
        $query = 'DELETE FROM usermanagement.processingcart '
               . 'WHERE itemid = \'' . pg_escape_string($itemId) . '\' '
               . 'AND userid = \'' . pg_escape_string($userid) . '\'';
        
        $this->dbDriver->query($query, 500, 'Cannot remove ' . $itemId . ' from processing cart');
        
        return true;
    }
}
