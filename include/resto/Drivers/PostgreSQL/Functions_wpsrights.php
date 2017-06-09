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
        
        $query = "SELECT wpsrightsid, identifier FROM usermanagement.wpsrights WHERE groupid = " . pg_escape_string($groupid) . " ORDER BY wpsrightsid";
        
        $results = $this->dbDriver->query($query);
        while ($result = pg_fetch_assoc($results)) {
            $items[] = array(
                'id' => $result['wpsrightsid'],
                'identifier' => $result['identifier']
            );
        }
        
        return $items;
    }
}
