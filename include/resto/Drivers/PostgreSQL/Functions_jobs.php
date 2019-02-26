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
        $filters[] = 'lower(key)=\'datainputs\'';
        $oFilter = implode(' AND ', $filters);

        // Query
        $query = 'SELECT j.*, SUBSTRING(value::text from \'product=([A-Z0-9_]+)]?\') as product FROM usermanagement.jobs j, json_each_text(data::json) WHERE ' . $oFilter . ' ORDER BY querytime DESC';

        return $this->dbDriver->fetch($this->dbDriver->query($query));
    }
    
    /**
     * 
     * @param unknown $userid
     * @param array $filters
     * @return array|unknown
     */
    public function getJobsId($filters= array()) {
        $items = array();
        
        $filters[] = 'visible=TRUE';
        $oFilter = implode(' AND ', $filters);
        
        // Query
        $query = "SELECT substring(statuslocation from 'pywps-(.+)[.]xml') as jobid FROM usermanagement.jobs WHERE " . $oFilter . ' ORDER BY querytime DESC';
        $results = $this->dbDriver->fetch($this->dbDriver->query($query));
        
        $results = $this->dbDriver->query($query, 500, 'Cannot get running jobs id');
        while ($result = pg_fetch_assoc($results)) {
            $items[] = $result['jobid'];
        }
        
        return $items;
    }
    
    /**
     * 
     * @return array users list to notify
     */
    public function getUsersToNotify($filters = array()) {

        
        $filters[] = 'visible=TRUE';
        $oFilter = implode(' AND ', $filters);

        $query = "WITH tmp AS (select distinct userid FROM usermanagement.jobs WHERE " . $oFilter . ") ";
        $query .= "SELECT u.email as email FROM tmp INNER JOIN usermanagement.users u ON u.userid = tmp.userid and u.activated=1";

        $results = $this->dbDriver->fetch($this->dbDriver->query($query));
        
        $results = $this->dbDriver->query($query, 500, 'Cannot get users list to notify');
        while ($result = pg_fetch_assoc($results)) {
            $items[] = $result['email'];
        }
        
        return $items;
    }
    
    /**
     * Returns the total of the completed jobs (succeeded + failed)
     * 
     * @param string $email
     * @return number
     */
    public function getStats($userid, $filters= array())
    {
        if (!isset($userid)) {
            return 0;
        }
        $filters[] = "(status = 'ProcessSucceeded' OR status = 'ProcessFailed')";
        $filters[] = 'userid=' . $this->dbDriver->quote($userid);
        $filters[] = 'visible=TRUE';
        $filters[] = 'acknowledge = FALSE';
        $oFilter = implode(' AND ', $filters);
        
        $query = 'SELECT count(status) FROM usermanagement.jobs WHERE ' . $oFilter;
        
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
    public function add($userid, $data) {
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
            $querytime          = $this->dbDriver->quote($data['querytime'], date('Y-m-d H:i:s'));
            $identifier         = $this->dbDriver->quote($data['identifier'], 'NULL');
            $title              = $this->dbDriver->quote($data['title'], 'NULL');
            $notifmail        	= $this->dbDriver->quote($data['notifmail'], false);
            $status             = $this->dbDriver->quote($data['status'], 'NULL');
            $statusMessage      = $this->dbDriver->quote($data['statusMessage'], 'NULL');
            $statusLocation     = $this->dbDriver->quote($data['statusLocation'], 'NULL');
            $statusTime         = $this->dbDriver->quote($data['statusTime'], 'NULL');
            $percentCompleted   = $this->dbDriver->quote($data['percentcompleted'], 0);
            $outputs            = (!empty($data['statusLocation']) && isset($data['outputs'])) ? $data['outputs'] :  array();
            $method             = $this->dbDriver->quote($data['method'], 'NULL');
            $data               = $this->dbDriver->quote(json_encode($data['data']), 'NULL');

            $values = array (
                    $userid,
                    $querytime,
                    $method,
                    $title,
	                $notifmail,
                    $data,
                    $identifier, $status, $statusMessage, $statusLocation, $statusTime, $percentCompleted, count($outputs)
            );

            // Save job.
            $query = 'INSERT INTO usermanagement.jobs (userid, querytime, method, title, notifmail, data, identifier, status, statusmessage, statusLocation, statustime, percentCompleted, nbresults) '
                    . 'VALUES (' . join(',', $values) . ') RETURNING gid';
            $job = $this->dbDriver->fetch_assoc($this->dbDriver->query($query));
            
            if (empty($job['gid']))
            {             
                pg_query($this->dbh, 'ROLLBACK');
                return false;
            }

            // Save results
            foreach ($outputs as $output){
                if (isset($output['value']) && isset($output['type']) && isset($output['identifier'])) 
                {
                    $value = pg_escape_string($output['value']);
                    $query = "INSERT INTO usermanagement.wps_results (jobid, userid, identifier, value) VALUES ({$job['gid']}, $userid, '{$output['identifier']}', '{$value}')";
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
            return (pg_num_rows($result) >= 1);

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
            return (pg_num_rows($result) >= 1);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Update job and result
     * 
     * @param integer $userid
     * @param array $data
     * @return boolean
     */
    public function update($data) {
        if (!isset($data['jobid'])) {
            return false;
        }
        try {
            /*
             * Start transaction
             */
            pg_query($this->dbh, 'BEGIN');

            
            $status             = $this->dbDriver->quote2($data, 'status', 'NULL');
            $statusMessage      = $this->dbDriver->quote2($data, 'statusmessage', 'NULL');
            $statusTime         = $this->dbDriver->quote2($data, 'statusTime', 'NULL');
            $percentCompleted   = $this->dbDriver->quote2($data, 'percentcompleted', 0);
            $outputs            = isset($data['outputs']) ? $data['outputs'] :  array();
            $wpsid              = $this->dbDriver->quote($data['jobid']);
            $nbResults          = count($outputs);
            $last_dispatch      = $this->dbDriver->quote2($data, 'last_dispatch', 'now()');
            $logs               = $this->dbDriver->quote2($data, 'logs', 'NULL');
        
            // update properties
            $query = 'UPDATE usermanagement.jobs SET ' 
                            . 'last_dispatch=' . $last_dispatch 
                            . ', status=' . $status
                            . ', percentcompleted=' . $percentCompleted 
                            . ', statusmessage=' . $statusMessage
                            . ', statustime=' . $statusTime
                            . ', nbresults=' . $nbResults 
                            . ', logs=' . $logs
                            . ' WHERE substring(statuslocation from \'pywps-(.+)[.]xml\')=' . $wpsid
                            . ' RETURNING gid, userid';
            
            $result = $this->dbDriver->query($query);
            if ($job = pg_fetch_assoc($result)) {
                
                $gid = $job['gid'];
                $userid = $job['userid'];
                $query = 'DELETE FROM usermanagement.wps_results WHERE jobid=' . $gid . ' AND userid=' . $userid;
                $this->dbDriver->query($query);
                
                // Save result
                foreach ($outputs as $output)
                {
                    $identifier = basename($output);
                    $type = 'application/octet-stream';
                    $query = 'INSERT INTO usermanagement.wps_results (jobid, userid, identifier, type, value)'
                        . " VALUES ($gid, $userid, '${identifier}', '${type}', '${output}')";
                        $this->dbDriver->query($query);
                }
                pg_query($this->dbh, 'COMMIT');
            }
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
