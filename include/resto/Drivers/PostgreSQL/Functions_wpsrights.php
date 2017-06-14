<?php
/**
 * RESTo PostgreSQL WPS rights functions
 */
class Functions_wpsrights {
    
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
     * Return WPS rights for the specified group
     * 
     * @param int groupid
     * @return array
     * @throws exception
     */
    public function getWpsGroupRights($groupid)
    {
        $items = array();
        
        $query = "SELECT identifier FROM usermanagement.wpsrights WHERE groupid = " . pg_escape_string($groupid) . " ORDER BY wpsrightsid";
        
        $results = $this->dbDriver->query($query);
        while ($result = pg_fetch_assoc($results)) {
            $items[] = $result['identifier'];
        }
        
        return $items;
    }
    
    /**
     * Remove WPS rights for the specified group id
     *  
     * @param {int} groupid
     * @throws exception
     */
    public function removeWpsRights($groupid)
    {
        try {
            $query = "DELETE FROM usermanagement.wpsrights WHERE groupid = " . pg_escape_string($groupid);
            $result = $this->dbDriver->query($query);
            if (!$result) {
                throw new Exception;
            }
        } catch (Exception $e) {
            RestoLogUtil::httpError(8000, 'Cannot delete WPS rights for group ' . $groupid);
            return false;
        }
        
        return true;
    }
    
    /**
     * Store WPS rights for the specified group id
     * 
     * @param {int} groupid
     * @param {array} wpsRights
     * @throws exception
     */
    public function storeWpsRights($groupId, $wpsRights)
    {
        try {
            foreach($wpsRights as $identifier) {
                $values = array(
                    "'" . pg_escape_string($groupId) . "'",
                    "'" . pg_escape_string($identifier) . "'"
                );
                $query = 'INSERT INTO usermanagement.wpsrights (groupid, identifier) VALUES (' . join(',', $values) . ')';
                $result = pg_query($this->dbh, $query);
                if (!$result){
                    throw new Exception();
                }
            }
        } catch (Exception $e) {
            RestoLogUtil::httpError(8000, 'Cannot store WPS rights for group ' . $groupId);
            return false;
        }
        
        return true;
    }
}
