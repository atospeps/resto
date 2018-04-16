<?php
/**
 * RESTo PostgreSQL Proactive account functions
 */
class Functions_proactive {
    
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
     * Return all Proactive accounts
     * 
     * @return array
     * @throws exception
     */
    public function getAccounts()
    {        
        $items = array();
        
        $query = 'SELECT proactiveid, login, password FROM usermanagement.proactive ORDER BY proactiveid';
        $results = $this->dbDriver->query($query);
        while ($result = pg_fetch_assoc($results)) {
            $items[] = array(
                'id' => $result['proactiveid'],
                'login' => $result['login'],
                'password' => $result['password']
            );
        }
        
        return $items;
    }
    
    /**
     * Return single account
     * 
     * @return array
     * @throws exception
     */
    public function getAccount($proactiveid)
    {
        $query = 'SELECT * FROM usermanagement.proactive WHERE proactiveid = \'' . $proactiveid . '\'';
        $results = $this->dbDriver->query($query);
        
        $account = null;
        while ($result = pg_fetch_assoc($results)) {
            $account = array(
                'id' => $result['proactiveid'],
                'login' => $result['login'],
                'password' => $result['password']
            );
        }

        if ($account === null) {
            RestoLogUtil::httpError(404);
        }
        
        return $account;
    }
    
    /**
     * Return true if the Proactive account $login exists 
     * 
     * @param string $login
     * @return boolean
     * @throws exception
     */
    public function isAccountExists($login, $id = null)
    {
        if (!isset($login) && !isset($id)) {
            return false;
        }
        
        $where = array();
        $query = 'SELECT 1 FROM usermanagement.proactive ';
        if (isset($login)) {
            $where[] = "login = '" . pg_escape_string($login) . "'";     
        }
        if (isset($id)) {
            $where[] = "proactiveid = '" . pg_escape_string($id) . "'";     
        }
        $query .= 'WHERE ' . implode(' AND ', $where);
        
        $results = $this->dbDriver->fetch($this->dbDriver->query(($query)));
        
        return !empty($results);
    }
    
    /**
     * Create a Proactive account
     * 
     * @param string $login
     * @param string $password
     *   
     *   Must contain at least the $login
     *   
     * @return boolean
     * @throws exception
     */
    public function createAccount($login, $password)
    {
        if (!isset($login)) {
            return false;
        }

        if($this->isAccountExists($login)) {
            RestoLogUtil::httpError(7000, 'Cannot create Proactive account: ' . $login . ', it already exists');
        }
        
        $values = array(
            '\'' . pg_escape_string($login) . '\'',
            '\'' . pg_escape_string(RestoUtil::encrypt($password)) . '\''
        );
        $this->dbDriver->query('INSERT INTO usermanagement.proactive (login, password) VALUES (' . join(',', $values) . ')');
        
        return true;
    }
    
    /**
     * Update Proactive account
     * 
     * @param string $id
     * @param string $login
     * @param string $password
     *   
     * @return boolean
     * @throws exception
     */
    public function updateAccount($id, $login, $password)
    {
        if (!isset($id)) {
            return false;
        }

        if ($this->isAccountExists(null, $id) === false) {
            RestoLogUtil::httpError(7001, 'Cannot update Proactive account: ' . $login . ', does not exists');
        }
        
        $query = "UPDATE usermanagement.proactive"
               . " SET login = '" . pg_escape_string($login) . "'"
               .       (!empty($password) ? ", password = '" . pg_escape_string(RestoUtil::encrypt($password)) . "'" : "")
               . " FROM (SELECT login FROM usermanagement.proactive WHERE proactiveid = '" . pg_escape_string($id) . "' FOR UPDATE) oldAccount WHERE proactiveid = '" . pg_escape_string($id) . "' RETURNING oldAccount.login";
        
        $results = $this->dbDriver->query($query);

        if (pg_fetch_assoc($results)) {
            return true;
        }
        return false;
    }
    
    /**
     * Remove Proactive account
     * 
     * @param string $id
     * @return boolean
     * @throws exception
     */
    public function removeAccount($id)
    {
       if (!isset($id)) {
            return false;
       }
       
        $results = $this->dbDriver->query('DELETE FROM usermanagement.proactive WHERE proactiveid = \'' . pg_escape_string($id) . '\' RETURNING login');
        
        if (pg_fetch_assoc($results)) {
            return true;
        }
        return false;
    }
    
}
