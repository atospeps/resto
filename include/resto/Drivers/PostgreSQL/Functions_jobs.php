<?php

/**
 * RESTo PostgreSQL jobs functions
 */
class Functions_jobs {
    
    /*
     * Reference to database driver
     */
    private $dbDriver = null;

    /*
     * Reference to database handler
     */
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
     * Return jobs for userid
     * @param string $userid
     * @param string $jobid
     * @return array : user's jobs
     */
    public function get($userid, $jobid = null, $filters= array()) {

        $items = array();

        // ? User id not setted
        if (!isset($userid)) {
            return $items;
        }

        $filters[] = 'userid=' . $this->dbDriver->quote($userid); 
        $oFilter = implode(' AND ', $filters);

        // Query
        $query = 'SELECT * FROM usermanagement.jobs' 
                . ' WHERE ' . $oFilter
                . (isset($jobid) ? 'AND gid=' . $this->dbDriver->quote($jobid) : '')
                . ' ORDER BY querytime DESC';

        return $this->dbDriver->fetch($this->dbDriver->query($query));
    }

    /**
     * 
     * @param integer $userid
     * @param array $data
     * @return boolean
     */
    public function add($userid, $data){
        if (!isset($data['identifier'])) {
            return false;
        }
        try {
            // Inserting the job into database
            $userid             = $this->dbDriver->quote($userid);
            $querytime          = $this->dbDriver->quote($data['querytime'], date("Y-m-d H:i:s"));
            // TODO $query
            $identifier         = $this->dbDriver->quote($data['identifier'], 'NULL');
            $status             = $this->dbDriver->quote($data['status'], 'NULL');
            $statusMessage      = $this->dbDriver->quote($data['statusMessage'], 'NULL');
            $statusLocation     = $this->dbDriver->quote($data['statusLocation'], 'NULL');
            $percentCompleted   = $this->dbDriver->quote($data['percentcompleted'], 0);
            $outputs            = $this->dbDriver->quote(json_encode($data['outputs']), 'NULL');
            $method             = $this->dbDriver->quote(json_encode($data['method']), 'NULL');
            $data               = $this->dbDriver->quote(json_encode($data['data']), 'NULL');
    
            $values = array (
                    $userid,
                    $querytime,
                    $method,
                    $data,
                    $identifier, $status, $statusMessage, $statusLocation, $percentCompleted, $outputs
            );

            // Save job.
            $query = 'INSERT INTO usermanagement.jobs (userid, querytime, method, data, identifier, status, statusmessage, statusLocation, percentCompleted, outputs) '
                    . 'VALUES (' . join(',', $values) . ')';
            $jobs = $this->dbDriver->query($query);
            return true;
        } 
        catch (Exception $e) {
           return false;
        }
    }

    /**
     * 
     * @param integer $userid
     * @param integer $jobid
     * @return boolean
     */
    public function remove($userid, $jobid){

        if (!isset($userid) || $isset($jobid)) {
            return false;
        }

        try {
            $query = 'DELETE FROM usermanagement.jobs WHERE gid=' . $this->dbDriver->quote($jobid) . 'AND userid=' . $this->dbDriver->quote($userid);
            $result = $this->dbDriver->query($query);
            
            // If no deletion, return false
            if (pg_num_rows($result) < 1) {
                return false;
            } else {
                return true;
            }
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 
     * @param integer $userid
     * @param array $data
     * @return boolean
     */
    public function update($userid, $data){
        if (!isset($userid) || !isset($data['gid'])) {
            return false;
        }

        try {        
            $status = $this->dbDriver->quote($data['status'], 'NULL');
            $statusMessage = $this->dbDriver->quote($data['statusmessage'], 'NULL');
            $percentCompleted = $this->dbDriver->quote($data['percentcompleted'], 0);
            $outputs = $this->dbDriver->quote((json_encode($data['outputs'])), 'NULL');
            $gid = $this->dbDriver->quote($data['gid']);
        
            $query = "UPDATE usermanagement.jobs "
                    . "SET status='{$status}', percentcompleted={$percentCompleted}, outputs={$outputs}, statusmessage={$statusMessage} "
                    . "WHERE gid={$gid}";
            $jobs = pg_query($this->dbh, $query);        
        } 
        catch (Exception $e) {
            return false;
        }
        return true;
    }

    /**
     * Remove all items from job for user $identifier
     *
     * @param string $identifier
     * @return boolean
     * @throws exception
     */
    public function clear($userid) {

        if (!isset($userid)) {
            return false;
        }

        try {
            $query = 'DELETE FROM usermanagement.jobs WHERE userid=' . $this->dbDriver->quote($userid);
            $this->dbDriver->query($query);
        } 
        catch (Exception $e) {
            return false;
        }
        return true;
    }    
}
