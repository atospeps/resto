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

        // ? Job id is setted
        if (isset($jobid)) {
            $filters[] = 'gid=' . $this->dbDriver->quote($jobid);
        }

        $filters[] = 'userid=' . $this->dbDriver->quote($userid);
        $filters[] = 'visible=TRUE';
        $oFilter = implode(' AND ', $filters);

        // Query
        $query = 'SELECT * FROM usermanagement.jobs WHERE ' . $oFilter . ' ORDER BY querytime DESC';

        return $this->dbDriver->fetch($this->dbDriver->query($query));
    }

    /**
     * Returns the total of the completed jobs (succeeded + failed)
     * 
     * @param string $email
     * @return number
     */
    public function getStats($userid)
    {
        if (!isset($userid)) {
            return 0;
        }
        
        $query = "SELECT count(status)"
               . " FROM usermanagement.jobs"
               . " WHERE (status = 'ProcessSucceeded' OR status = 'ProcessFailed')"
               . " AND userid = " . $this->dbDriver->quote($userid)
               . " AND acknowledge = FALSE"
               . " AND visible = TRUE";
        
        $result = $this->dbDriver->query($query);
        $row = $this->dbDriver->fetch_assoc($result);
        
        return (int)$row['count'];
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
            /*
             * Start transaction
             */
            pg_query($this->dbh, 'BEGIN');
            
            // Inserting the job into database
            $userid             = $this->dbDriver->quote($userid);
            $querytime          = $this->dbDriver->quote($data['querytime'], date("Y-m-d H:i:s"));
            $identifier         = $this->dbDriver->quote($data['identifier'], 'NULL');
            $title              = $this->dbDriver->quote($data['title'], 'NULL');
            $status             = $this->dbDriver->quote($data['status'], 'NULL');
            $statusMessage      = $this->dbDriver->quote($data['statusMessage'], 'NULL');
            $statusLocation     = $this->dbDriver->quote($data['statusLocation'], 'NULL');
            $statusTime         = $this->dbDriver->quote($data['statusTime'], 'NULL');
            $percentCompleted   = $this->dbDriver->quote($data['percentcompleted'], 0);
            $outputs            = isset($data['outputs']) ? $data['outputs'] :  array();
            $method             = $this->dbDriver->quote($data['method'], 'NULL');
            $data               = $this->dbDriver->quote(json_encode($data['data']), 'NULL');

            $values = array (
                    $userid,
                    $querytime,
                    $method,
                    $title,
                    $data,
                    $identifier, $status, $statusMessage, $statusLocation, $statusTime, $percentCompleted, count($outputs)
            );

            // Save job.
            $query = 'INSERT INTO usermanagement.jobs (userid, querytime, method, title, data, identifier, status, statusmessage, statusLocation, statustime, percentCompleted, nbresults) '
                    . 'VALUES (' . join(',', $values) . ') RETURNING gid';
            $jobid = $this->dbDriver->query($query);
            
            if (!$jobid)
            {
                return false;
            }
            // Save results
            foreach ($outputs as $output){
                if (isset($output['value']) && isset($output['type']) && isset($output['identifier'])) {
                    
                    $query = 'INSERT INTO usermanagement.wps_results (jobid, userid, identifier, type, value)'
                            . " VALUES ($jobid, $userid, '${output['identifier']}', '${output['type']}', '${output['value']}')";
                    $this->dbDriver->query($query);
                }
            }
            pg_query($this->dbh, 'COMMIT');
            return true;
        } 
        catch (Exception $e) { 
            pg_query($this->dbh, 'ROLLBACK');
            return false;
        }
    }

    /**
     * 
     * @param integer $userid
     * @param integer $jobid
     * @return boolean
     */
    public function remove($userid, $jobid) {

        if (!isset($userid) || !isset($jobid)) {
            return false;
        }

        try {
            $query = 'UPDATE usermanagement.jobs set visible=FALSE WHERE gid=' . $this->dbDriver->quote($jobid) . 'AND userid=' . $this->dbDriver->quote($userid);
            $result = $this->dbDriver->query($query);
            
            if (!$result){
                return false;
            }
            // If no update, return false
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
     * @param integer $jobid
     * @return boolean
     */
    public function delete($userid, $jobid) {
    
        if (!isset($userid) || !isset($jobid)) {
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
            /*
             * Start transaction
             */
            pg_query($this->dbh, 'BEGIN');

            $status             = $this->dbDriver->quote($data['status'], 'NULL');
            $statusMessage      = $this->dbDriver->quote($data['statusmessage'], 'NULL');
            $statusTime         = $this->dbDriver->quote($data['statusTime'], 'NULL');
            $percentCompleted   = $this->dbDriver->quote($data['percentcompleted'], 0);
            $outputs            = isset($data['outputs']) ? $data['outputs'] :  array();
            $gid                = $this->dbDriver->quote($data['gid']);
            $nbResults          = count($outputs);
            $last_dispatch      = $this->dbDriver->quote($data['last_dispatch'], 'now()');
        
            // update properties
            $query = 'UPDATE usermanagement.jobs'
                    . " SET last_dispatch=${last_dispatch}, status=${status}, percentcompleted=${percentCompleted}, statusmessage=${statusMessage}, statustime=${statusTime}, nbresults=${nbResults}"
                    . " WHERE gid=${gid}";
            
            $this->dbDriver->query($query);

            //
            $query = 'DELETE FROM usermanagement.wps_results WHERE jobid=' . $gid . ' AND userid=' . $userid;
            $this->dbDriver->query($query);

            // Save results
            foreach ($outputs as $output) {
                if (isset($output['value']) && isset($output['type']) && isset($output['identifier'])) {

                    $query = 'INSERT INTO usermanagement.wps_results (jobid, userid, identifier, type, value)'
                            . " VALUES ($gid, $userid, '${output['identifier']}', '${output['type']}', '${output['value']}')";
                    $this->dbDriver->query($query);
                }
            }
            pg_query($this->dbh, 'COMMIT');
        } 
        catch (Exception $e) {
            pg_query($this->dbh, 'ROLLBACK');
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
