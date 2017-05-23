<?php

/**
 * RESTo PostgreSQL jobs functions
 */
class Functions_jobs {
    
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
    
    public function find($jobid){
        if (!isset($jobid)){
            return null;
        }
        
        $query = 'SELECT * FROM usermanagement.jobs WHERE email=\'' . pg_escape_string($identifier) . '\'';
        $results = $this->dbDriver->fetch($this->dbDriver->query($query));
        return count($results) === 1 ? $results[0]['password'] : null;
        
    }
    
    public function get($userid = null){
        $query = 'SELECT * FROM usermanagement.jobs WHERE email=\'' . pg_escape_string($identifier) . '\'';
        $results = $this->dbDriver->fetch($this->dbDriver->query($query));
        return count($results) === 1 ? $results[0]['password'] : null;
    }
    
    public function add($data){
        
    }
    
    public function remove($data){
        
    }
    
    public function update($data){
        
    }
    
    
    
}
