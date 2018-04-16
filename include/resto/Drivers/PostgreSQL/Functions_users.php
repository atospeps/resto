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
 * RESTo PostgreSQL users functions
 */
class Functions_users {
    
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
     * Return encrypted user password
     * 
     * @param string $identifier : email
     * 
     * @throws Exception
     */
    public function getUserPassword($identifier) {
        $query = 'SELECT password FROM usermanagement.users WHERE email=\'' . pg_escape_string($identifier) . '\'';
        $results = $this->dbDriver->fetch($this->dbDriver->query($query));
        return count($results) === 1 ? $results[0]['password'] : null;
    }
        
    /**
     * Get user profile
     * 
     * @param string $identifier : can be email (or string) or integer (i.e. uid)
     * @param string $password : if set then profile is returned only if password is valid
     * @return array : this function should return array('userid' => -1, 'groupname' => 'unregistered')
     *                 if user is not found in database
     * @throws exception
     */
    public function getUserProfile($identifier, $password = null)
    {
        if (!isset($identifier) || !$identifier || $identifier === 'unregistered') {
            RestoLogUtil::httpError(404);
        }

        $query = "SELECT userid, email, md5(email) AS userhash, u.groupname, username, givenname, lastname, organization, nationality, domain, use, country, adress, numtel, numfax, instantdownload, weeklydownload, to_char(registrationdate, 'YYYY-MM-DD\"T\"HH24:MI:SS\"Z\"'), activated, CAST(g.canwps AS INT) AS wps"
               . " FROM usermanagement.users AS u"
               . " LEFT JOIN usermanagement.groups AS g ON u.groupname = g.groupname" 
               . " WHERE " . $this->useridOrEmailFilter($identifier)
               . (isset ( $password ) ? " AND password='" . pg_escape_string(RestoUtil::encrypt($password)) . "'" : "");

        $results = $this->dbDriver->fetch($this->dbDriver->query($query));
        
        if (count($results) === 0) {
            RestoLogUtil::httpError(404);
        }
        
        $results[0]['instantdownload'] = isset($results[0]['instantdownload']) ? (int)$results[0]['instantdownload'] : NULL;
        $results[0]['weeklydownload']  = isset($results[0]['weeklydownload'])  ? (int)$results[0]['weeklydownload']  : NULL;
        
        $results[0]['activated'] = (int) $results[0]['activated'];
        $results[0]['wps'] = (int) $results[0]['wps'];
        
        return $results[0];
    }

    /**
     * Check if user identified by $identifier exists within database
     * 
     * @param string $email - user email
     * 
     * @throws Exception
     * @return boolean
     */
    public function userExists($email) {
        $query = 'SELECT 1 FROM usermanagement.users WHERE email=\'' . pg_escape_string($email) . '\'';
        $results = $this->dbDriver->fetch($this->dbDriver->query(($query)));
        return !empty($results);
    }
    
    /**
     * Save user profile to database i.e. create new entry if user does not exist
     * 
     * @param array $profile
     * @return array (userid, activationcode)
     * @throws exception
     */
    public function storeUserProfile($profile) {
       
        if (!is_array($profile) || !isset($profile['email'])) {
            RestoLogUtil::httpError(500, 'Cannot save user profile - invalid user identifier');
        }
        if ($this->userExists($profile['email'])) {
            RestoLogUtil::httpError(500, 'Cannot save user profile - user already exist');
        }
        if (isset($profile['domain'])) {
            // Checks if domain value is valid.
            $possibleValues = array('research', 'commercial', 'education', 'other');
            if (!is_string($profile['domain']) || in_array($profile['domain'], $possibleValues) == false) {
                RestoLogUtil::httpError(500, 'Cannot save user profile - invalid user domain');
            }
        }
        if (isset($profile['use'])) {
            // Checks if use value is valid.
            $possibleValues = array('atmosphere', 'ocean', 'land', 'emergency', 'security', 'other');
            if (!is_string($profile['use']) || in_array($profile['use'], $possibleValues) == false) {
                RestoLogUtil::httpError(500, 'Cannot save user profile - invalid user use');
            }
        }
        $email = trim(strtolower($profile['email']));
        $values = "'" . pg_escape_string($email) . "',";
        $values .= "'" . (isset($profile['password']) ? RestoUtil::encrypt($profile['password']) : str_repeat('*', 40)) . "',";
        $values .= "'" . (isset($profile['groupname']) ? pg_escape_string($profile['groupname']) : 'default') . "',";
        foreach ( array_values ( array (
				'username', 'givenname', 'lastname',
				'organization', 'nationality', 'domain',
				'use', 'country', 'adress', 
        		'numtel', 'numfax',	'instantdownload',
				'weeklydownload' 
		) ) as $field ) {
			$values .= (isset ( $profile [$field] ) ? "'" . pg_escape_string($profile[$field]) . "'" : 'NULL') . ",";
		}
        $values .= "'" . pg_escape_string(RestoUtil::encrypt($email . microtime())) . "',";
        $values .= $profile['activated'] . ',now()';
        
        // TODO change to pg_fetch_assoc ?
		$results = $this->dbDriver->query ( 'INSERT INTO usermanagement.users (email,password,groupname,username,givenname,lastname,organization,nationality,domain,use,country,adress,numtel,numfax,instantdownload,weeklydownload,activationcode,activated,registrationdate) VALUES (' . $values . ') RETURNING userid, activationcode' );
        return pg_fetch_array($results);
        
    }
    
    /**
     * Update user profile to database
     * 
     * @param array $profile
     * @return integer (userid)
     * @throws exception
     */
    public function updateUserProfile($profile)
    {
        if (!is_array($profile) || (!isset($profile['email']) && !isset($profile['id']))) {
            RestoLogUtil::httpError(500, 'Cannot update user profile - invalid user identifier');
        }

        $values = array();
        if (isset($profile['password'])) {
            $values[] = 'password=\'' . RestoUtil::encrypt($profile['password']) . '\'';
        }
        if (isset($profile['groupname'])) {
            $values[] = 'groupname=\'' . pg_escape_string($profile['groupname']) . '\'';
        }
        if (isset($profile['activated'])) {
            $values[] = 'activated=' . $profile['activated'];
        }
        if (isset($profile['domain'])) {
            // Checks if domain value is valid.
            $possibleValues = array('research', 'commercial', 'education', 'other');
            if (!is_string($profile['domain']) || in_array($profile['domain'], $possibleValues) == false){
                RestoLogUtil::httpError(500, 'Cannot save user profile - invalid user domain');
            }
        }
        if (isset($profile['use'])) {
            // Checks if use value is valid.
            $possibleValues = array('atmosphere', 'ocean', 'land', 'emergency', 'security', 'other');
            if (!is_string($profile['use']) || in_array($profile['use'], $possibleValues) == false){
                RestoLogUtil::httpError(500, 'Cannot save user profile - invalid user use');
            }
        }
        foreach ( array_values ( array (
				'username', 'givenname', 'lastname',
				'organization', 'nationality', 'domain',
				'use', 'country', 'adress', 
        		'numtel', 'numfax',	'instantdownload',
				'weeklydownload' 
		) ) as $field ) {
		    if (array_key_exists($field, $profile)) {
                switch(gettype($profile[$field])) {
                    case 'integer':
                        $values[] = pg_escape_string($field) . '=' . pg_escape_string($profile[$field]);
                        break;
                    case 'NULL':
                        $values[] = pg_escape_string($field) . '= NULL';
                        break;
                    default:
                        $values[] = pg_escape_string($field) . '=\'' . pg_escape_string($profile[$field]) . '\'';
                }
		    }
		}

		// Nothing to update
		if(count($values) === 0) {
            return true;   
		}
		
        if(isset($profile['email'])) 
        {
            $query = 'UPDATE usermanagement.users SET ' . join(',', $values) . ' WHERE email=\'' . pg_escape_string(trim(strtolower($profile['email']))) .'\' RETURNING userid';
        } 
        else {
            $query = 'UPDATE usermanagement.users SET ' . join(',', $values) . ' WHERE userid=\'' . pg_escape_string(trim(strtolower($profile['id']))) .'\' RETURNING userid';
        }
        $results = $this->dbDriver->fetch($this->dbDriver->query($query));
        
        return count($results) === 1 ? $results[0]['userid'] : null;
        
    }

    /**
     * Return true if token is revoked
     * 
     * @param string $token
     */
    public function isTokenRevoked($token) {
        $query = 'SELECT 1 FROM usermanagement.revokedtokens WHERE token= \'' . pg_escape_string($token) . '\'';
        $results = $this->dbDriver->fetch($this->dbDriver->query(($query)));
        return !empty($results);
    }

    /**
     * Revoke token
     * 
     * @param string $token
     */
    public function revokeToken($token) {
        if (isset($token) && !$this->isTokenRevoked($token)) {
            $this->dbDriver->query('INSERT INTO usermanagement.revokedtokens (token) VALUES(\'' . pg_escape_string($token) . '\')');
        }
        return true;
    }
    
    /**
     * Sign license for collection collectionName
     * 
     * @param string $identifier : user identifier 
     * @param string $collectionName
     * @return boolean
     * @throws Exception
     */
    public function signLicense($identifier, $collectionName) {
        
        if (!$this->dbDriver->check(RestoDatabaseDriver::COLLECTION, array(
            'collectionName' => $collectionName
        ))) {
            RestoLogUtil::httpError(500, 'Cannot sign license');
        }
        $results = $this->dbDriver->query('SELECT email FROM usermanagement.signatures WHERE email=\'' . pg_escape_string($identifier) . '\' AND collection=\'' . pg_escape_string($collectionName) . '\'');
        if (pg_fetch_assoc($results)) {
            $this->dbDriver->query('UPDATE usermanagement.signatures SET signdate=now() WHERE email=\'' . pg_escape_string($identifier) . '\' AND collection=\'' . pg_escape_string($collectionName) . '\'');
        }
        else {
            $this->dbDriver->query('INSERT INTO usermanagement.signatures (email, collection, signdate) VALUES (\'' . pg_escape_string($identifier) . '\',\'' . pg_escape_string($collectionName) . '\',now())');
        }
        return true;
    }
    
    /**
     * Activate user
     * 
     * @param string $userid : can be userid or base64(email)
     * @param string $activationcode
     * 
     * @throws Exception
     */
    public function activateUser($userid, $activationcode = null) {
        $query = 'UPDATE usermanagement.users SET activated=1 WHERE userid=\'' . pg_escape_string($userid) . '\'' . (isset($activationcode) ? ' AND activationcode=\'' . pg_escape_string($activationcode) . '\'' :'') . ' RETURNING userid';
        $results = $this->dbDriver->fetch($this->dbDriver->query($query));
        if (count($results) === 1) {
            return true;
        }
        return false;
    }
    
    /**
     * Deactivate user
     * 
     * @param string $userid
     * @throws Exception
     */
    public function deactivateUser($userid) {
        $query = 'UPDATE usermanagement.users SET activated=0 WHERE userid=\'' . pg_escape_string($userid) . '\'';
        $results = $this->dbDriver->fetch($this->dbDriver->query($query));
        if (count($results) === 1) {
            return true;
        }
        return false;
    }
    
    /**
     * Return filter on user
     * 
     * @param string $identifier
     */
    private function useridOrEmailFilter($identifier) {
        return ctype_digit($identifier) ? 'userid=' . $identifier : 'email=\'' . pg_escape_string($identifier) . '\'';
    }
    
    /**
     * Return the sum of products that a user has downloaded the last 7 days.
     * 
     * @param string $identifier
     * @return integer $totalproducts
     */
    public function getUserLastWeekDownloaded($identifier)
    {
        if (!isset($identifier)) {
            RestoLogUtil::httpError(404);
        }

        $query = "SELECT SUM(nbitems) AS total "
               . "FROM usermanagement.orders "
               . "WHERE " . $this->useridOrEmailFilter($identifier) . " "
               . "AND querytime > now() - interval '7 days'";
        
        $results = pg_fetch_assoc($this->dbDriver->query($query));
        
        return (int) $results['total'];
    }
    
    /**
     * 
     */
    public function checkPassword($identifier, $password) {
        if(!isset($identifier) || !isset($password)) {
            RestoLogUtil::httpError(404);
        }
        $query = 'SELECT 1 FROM usermanagement.users WHERE userid=\'' . pg_escape_string($identifier) . '\' AND password=\'' . RestoUtil::encrypt($password) . '\'';
        $results = $this->dbDriver->fetch($this->dbDriver->query(($query)));
        return !empty($results);
    }
}
