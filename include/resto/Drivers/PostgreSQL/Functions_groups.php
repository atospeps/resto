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
 * RESTo PostgreSQL cart functions
 */
class Functions_groups {
    
    private $dbDriver = null;
    private $dbh = null;
    
    /**
     * Constructor
     * 
     * @param array $config
     * @param RestoCache $cache
     * @throws Exception
     */
    public function __construct($dbDriver) {
        $this->dbDriver = $dbDriver;
        $this->dbh = $dbDriver->dbh;
    }
    
    /**
     * Return all groups
     * 
     * @return array
     * @throws exception
     */
    public function getGroups() {        
        $items = array();
        
        $query = 'SELECT gid, groupname, description FROM usermanagement.groups ORDER BY gid';
        $results = $this->dbDriver->query($query);
        while ($result = pg_fetch_assoc($results)) {
            $items[] = array(
                'id' => $result['gid'],
                'groupname' => $result['groupname'],
                'description' => $result['description']
            );
        }
        
        return $items;
    }
    
    /**
     * Return single group
     * 
     * @return array
     * @throws exception
     */
    public function getGroup($groupId) {        
        $items = array();
        $query = 'SELECT * FROM usermanagement.groups WHERE gid = \'' . $groupId . '\' ORDER BY gid';
        $results = $this->dbDriver->query($query);
        
        while ($result = pg_fetch_assoc($results)) {
            $items[] = array(
                'id' => $result['gid'],
                'groupname' => $result['groupname'],
                'description' => $result['description']
            );
        }

        // If group not found
        if ($items[0] === null) {
            RestoLogUtil::httpError(404);
        }
        
        return $items[0];
    }
    
    /**
     * Return true if the group $groupname exists 
     * 
     * @param string $groupname
     * @return boolean
     * @throws exception
     */
    public function isGroupExists($groupname, $identifier = null) {
        if (!isset($groupname)) {
            return false;
        }
        $query = 'SELECT 1 FROM usermanagement.groups WHERE groupname=\'' . pg_escape_string($groupname) . '\'';
        if(isset($identifier)) {
            $query .= ' AND gid!=\'' . pg_escape_string($identifier) . '\'';     
        }
        $results = $this->dbDriver->fetch($this->dbDriver->query(($query)));
        return !empty($results);
    }
    
    /**
     * Create a group
     * 
     * @param string $groupname
     * @param string $description
     *   
     *   Must contain at least the $groupname
     *   
     * @return boolean
     * @throws exception
     */
    public function createGroup($groupname, $description) {
        if (!isset($groupname)) {
            return false;
        }

        // Check if group already exists in the database
        if($this->isGroupExists($groupname)) {
            RestoLogUtil::httpError(5000, 'Cannot create group : ' . $groupname . ', it already exists');
        }
        
        $values = array(
            '\'' . pg_escape_string($groupname) . '\'',
            '\'' . pg_escape_string($description) . '\''
        );
        $this->dbDriver->query('INSERT INTO usermanagement.groups (groupname, description) VALUES (' . join(',', $values) . ')');
        return true;
    }
    
    /**
     * Update group
     * 
     * @param string $identifier
     * @param string $groupname
     * @param string $description
     *   
     * @return boolean
     * @throws exception
     */
    public function updateGroup($identifier, $groupname, $description) {
        if (!isset($identifier)) {
            return false;
        }

        // Check if group already exists in the database
        if($this->isGroupExists($groupname, $identifier)) {
            RestoLogUtil::httpError(5000, 'Cannot update groupname to : ' . $groupname . ', it already exists');
        }
        
        $results = $this->dbDriver->query('UPDATE usermanagement.groups SET groupname = \''. pg_escape_string($groupname) . '\', description= \''. pg_escape_string($description) . '\' FROM (SELECT groupname FROM usermanagement.groups WHERE gid=\'' . pg_escape_string($identifier) . '\' FOR UPDATE) oldGroup WHERE gid=\'' . pg_escape_string($identifier) . '\' RETURNING oldGroup.groupname');

        if ($group = pg_fetch_assoc($results)) {
            // Update all rights associated to the group
            $this->dbDriver->query('UPDATE usermanagement.rights SET emailorgroup = \''. pg_escape_string($groupname) . '\' WHERE emailorgroup=\'' . pg_escape_string($group['groupname']) . '\'');
            // Update all users which have the updated group
            $this->dbDriver->query('UPDATE usermanagement.users SET groupname = \''. pg_escape_string($groupname) . '\' WHERE groupname=\'' . pg_escape_string($group['groupname']) . '\'');
            return true;
        } else {
            return false;
        }
    }
    
    /**
     * Remove group
     * 
     * @param string $identifier
     * @return boolean
     * @throws exception
     */
    public function removeGroup($identifier) {
        if (!isset($identifier)) {
            return false;
        }
        $results = $this->dbDriver->query('DELETE FROM usermanagement.groups WHERE gid=\'' . pg_escape_string($identifier) . '\' RETURNING groupname');
        
        if ($group = pg_fetch_assoc($results)) {
            // Delete all rights associated to the group
            $this->dbDriver->query('DELETE from usermanagement.rights WHERE emailorgroup=\'' . pg_escape_string($group['groupname']) . '\'');
            
            // Change the group of the users which have the deleted group by the default group
            $this->dbDriver->query('UPDATE usermanagement.users SET groupname = \'default\' WHERE groupname=\'' . pg_escape_string($group['groupname']) . '\'');
            return true;
        } else {
            return false;
        }
    }
    
}
