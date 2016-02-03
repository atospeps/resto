<?php

/**
 * @author Atos
 * RESTo WPS proxy module.
 *
 *    | 
 *    | Resource                                                        | Description
 *    |_________________________________________________________________|______________________________________
 *    | HTTP/GET        wps/jobs                                        | List of all user's jobs
 *    | HTTP/GET        wps/jobs/{jobid}                                | Returns job
 *    | HTTP/DELETE     wps/jobs/{jobid}                                | Delete job
 *    |
 *    | HTTP/GET        wps?                                            | HTTP/GET wps services (OGC)
 *    | HTTP/POST       wps                                             | HTTP/POST wps services (OGC)    
 *    
 */
class WPS extends RestoModule {
    
    /*
     * Resto context
     */
    public $context;
    
    /*
     * Current user (only set for administration on a single user)
     */
    public $user = null;
    
    /*
     * segments
     */
    public $segments;
    
    /*
     * Database handler
     */
    private $dbh;
    
    /**
     * Constructor
     *
     * @param RestoContext $context
     * @param RestoUser $user
     */
    public function __construct($context, $user) {
        parent::__construct($context, $user);
        // Set user
        $this->user = $user;
        
        // Set context
        $this->context = $context;
        
        // Database handler
        $this->dbh = $this->getDatabaseHandler();
    }
    
    /**
     * Run module - this function should be called by Resto.php
     *
     * @param array $elements : route elements
     * @param array $data : POST or PUT parameters
     *       
     * @return string : result from run process in the $context->outputFormat
     */
    public function run($segments, $data = array()) {

        /*
         * Only GET method on 'search' route with json outputformat is accepted
         */
        if ($this->context->method !== 'GET' && $this->context->method !== 'POST') {
            RestoLogUtil::httpError(404);
        }

        /*
         * Only autenticated user.
         */
        if ($this->user->profile['userid'] == -1) {
            RestoLogUtil::httpError(401);
        }

        // Checks if user can execute WPS services
        if ($this->user->canExecuteWPS() === 1) {

            // We get URL segments and the http method
            $this->segments = $segments;
            $method = $this->context->method;

            /*
             * Switch on HTTP methods
             */
            switch ($method) {
                case 'GET' :
                    return $this->processGET();
                case 'POST' :
                    return $this->processPOST($data);
                default :
                    RestoLogUtil::httpError(404);
            }
        } else {
            // Right denied
            RestoLogUtil::httpError(403);
        }
    }

    /**
     * Process on HTTP method GET on /jobs
     */
    private function processGET() {
        /*
         * HTTP/GET WPS 1.0 OGC services
         */
        if (!isset($this->segments[0])) {
            $this->callWPSServer();
        } else if (isset($this->segments[0]) && !isset($this->segments[1])) {

            switch ($this->segments[0]) {
                case 'jobs' :
                    return $this->getUserJobs();
                    break;
                default :
                    RestoLogUtil::httpError(404);
                    break;
            }
        }
        /*
         * Unknown route
         */
        else {
            RestoLogUtil::httpError(404);
        }
    }
    
    /**
     * Process on HTTP method POST on /wps, /wps/execute and wps/clear
     */
    private function processPOST($data) {
        /*
         * Get the operation to proceed
         */
        if (isset($this->segments[0]) && $this->segments[0] == 'execute' && !isset($data['jid'])) {
            // If there is no identifier, an job is created
            return $this->createJob($data);
        } else if (!isset($this->segments[0]) && isset($data['jid'])) {
            // If there is an jid, we are editing an existing job
            return $this->editJob($data);
        } else if ($this->segments[0] == 'clear') {
            // With the segment clear, we delete an job
            return $this->deleteJob($data);
        } else {
            RestoLogUtil::httpError(404);
        }
    }
    
    /**
     *
     * @return multitype:multitype:
     */
    private function getUserJobs() {
        $identifier = $this->user->profile['email'];
        $escaped_identifier = pg_escape_string($identifier);
    
        $query = "SELECT * from usermanagement.jobs WHERE email='{$escaped_identifier}'";
        $jobs = pg_query($this->dbh, $query);
    
        $results = array ();
        while ($row = pg_fetch_assoc($jobs)) {
            $results[] = $row;
        }
        return $results;
    }
    
    /**
     * We create a job
     *
     * @throws Exception
     */
    private function createJob($data) {
        try {
            // Inserting the job into database
            $jobs = pg_query($this->dbh, 'INSERT INTO usermanagement.jobs WHERE email = \'' . pg_escape_string($this->user->profile['email']) . '\'');
            // We call the WPS Server
            $status = $this->callWPSServer($url);
            // If there was any problem with the WPS server
            if ($status === FALSE) {
            } else {
                try {
                    $jobs = pg_query($this->dbh, 'UPDATE usermanagement.jobs SET
                        email=\'' . pg_escape_string($data['email']) . '\', title=\'' . pg_escape_string($data['title']) . '\', querytime=\'' . pg_escape_string($data['querytime']) . '\', expiration=\'' . pg_escape_string($data['expiration']) . '\', criterias=\'' . pg_escape_string($data['criterias']) . '\' WHERE jid=\'' . pg_escape_string($data['jid']) . '\'');
                    return array (
                            'status' => 'success',
                            'message' => 'success' 
                    );
                } catch (Exception $e) {
                    RestoLogUtil::httpError($e->getCode(), $e->getMessage());
                }
            }
            
            return array (
                    'status' => 'success',
                    'message' => 'success' 
            );
        } catch (Exception $e) {
            RestoLogUtil::httpError($e->getCode(), $e->getMessage());
        }
    }
    
    /**
     * We edit a job
     *
     * @throws Exception
     */
    private function editJob($data) {
        // Edit a job using the job id
        if (isset($data['jid'])) {
            try {
                $jobs = pg_query($this->dbh, 'UPDATE usermanagement.jobs SET 
                        email=\'' . pg_escape_string($data['email']) . '\', title=\'' . pg_escape_string($data['title']) . '\', querytime=\'' . pg_escape_string($data['querytime']) . '\', expiration=\'' . pg_escape_string($data['expiration']) . '\', criterias=\'' . pg_escape_string($data['criterias']) . '\' WHERE jid=\'' . pg_escape_string($data['jid']) . '\'');
                return array (
                        'status' => 'success',
                        'message' => 'success' 
                );
            } catch (Exception $e) {
                RestoLogUtil::httpError($e->getCode(), $e->getMessage());
            }
        } else {
            RestoLogUtil::httpError(404);
        }
    }
    
    /**
     * We create a job
     *
     * @throws Exception
     */
    private function deleteJob($data) {
        // Delete a job using the job id
        if (isset($data['jid'])) {
            try {
                $jobs = pg_query($this->dbh, 'DELETE FROM usermanagement.jobs WHERE jid = \'' . pg_escape_string($data['jid']) . '\'');
                return array (
                        'status' => 'success',
                        'message' => 'success' 
                );
            } catch (Exception $e) {
                RestoLogUtil::httpError($e->getCode(), $e->getMessage());
            }
        } else {
            RestoLogUtil::httpError(404);
        }
    }
    
    /**
     * We call the WPS server
     */
    private function callWPSServer($url) {
        // Call the WPS Server
        /*$ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FAILONERROR, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $retValue = curl_exec($ch);
        curl_close($ch);
        
        if ($retValue !== FALSE) {
            // In orer to keep working...
            // The xml file returned cannot be treated by the xml php functions
            // We erase the tags which causes problems
            $tmp1 = str_replace("wps:", "", $retValue);
            $tmp2 = str_replace("ows:", "", $tmp1);
            
            // Transform to aray
            $xml = new SimpleXMLElement($tmp2);
            // Get the statusLocation
            return (string) $xml['statusLocation'];
        } else {
            return FALSE;
        }*/
        return RestoLogUtil::success('call WPS server');
    }
}
