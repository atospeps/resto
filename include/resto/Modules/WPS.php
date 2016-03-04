<?php

/**
 * @author Atos
 * RESTo WPS proxy module.
 *
 *    | 
 *    | Resource                                                        | Description
 *    |_________________________________________________________________|______________________________________
 *    | HTTP/GET        wps/users/{userid}/jobs                         | List of all user's jobs
 *    | HTTP/DELETE     wps/users/{userid}/jobs/{jobid}                 | Delete job
 *    |
 *    | HTTP/GET        wps?                                            | HTTP/GET wps services (OGC)
 *    | HTTP/POST       wps                                             | HTTP/POST wps services (OGC) - Not implemented   
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

    /*
     * WPS Server url.
     */
    private $wpsServerUrl;

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

        // WPS server url
        $this->wpsServerUrl = isset($this->context->modules[get_class($this)]['wpsServerUrl']) ? $this->context->modules[get_class($this)]['wpsServerUrl'] : null ;
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
        if ($this->context->method !== 'GET' && $this->context->method !== 'POST' && $this->context->method !== 'DELETE') {
            RestoLogUtil::httpError(404);
        }
        
        /*
         * Only autenticated user.
         */
        if ($this->user->profile['userid'] === -1) {
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
                case 'DELETE' :
                    return $this->processDELETE();
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
     * HTTP/GET
     */
    private function processGET() {
        /*
         * HTTP/GET WPS 1.0 OGC services
         * - GetCapabilities
         * - DescribeProcess
         * - Execute
         */
        if (!isset($this->segments[0])) {

            // Checks if WPS server url is configured
            if (empty($this->wpsServerUrl)){
                throw new Exception('WPS Configuration problem', 500);
            }

            $query = http_build_query($this->context->query);            
            return $this->callWPSServer($this->wpsServerUrl . $query);
        } else {
            switch ($this->segments[0]) {
                /*
                 * HTTP/GET wps/users/{userid}/jobs
                 * HTTP/GET wps/users/{userid}/jobs/{jobid}
                 */
                case 'users' :
                    return $this->GET_users($segments);
                    break;
                /*
                 * Unknown route
                 */
                default :
                    RestoLogUtil::httpError(404);
                    break;
            }
        }
    }
    
    /**
     * Process on HTTP method DELETE on /jobs
     * HTTP/DELETE
     */
    private function processDELETE() {
            // Verify all the variables
        if (isset($this->segments[0]) && 
                isset($this->segments[1]) && 
                isset($this->segments[2]) && 
                isset($this->segments[3])) {
              if ($this->segments[0]=='users' && 
                      ctype_digit($this->segments[1]) && 
                      $this->segments[2]=='jobs' && 
                      ctype_digit($this->segments[3]))  {
                          $this->deleteJob();
              }else{
                  RestoLogUtil::httpError(404);
              }      
        }else{
            RestoLogUtil::httpError(404);
        } 
       
        
        if (!isset($this->segments[0])) {
    
            // Checks if WPS server url is configured
            if (empty($this->wpsServerUrl)){
                throw new Exception('WPS Configuration problem', 500);
            }
    
            $query = http_build_query($this->context->query);
            return $this->callWPSServer($this->wpsServerUrl . $query);
        } else {
            switch ($this->segments[0]) {
                /*
                 * HTTP/GET wps/users/{userid}/jobs
                 * HTTP/GET wps/users/{userid}/jobs/{jobid}
                 */
                case 'users' :
                    return $this->GET_users($segments);
                    break;
                    /*
                     * Unknown route
                     */
                default :
                    RestoLogUtil::httpError(404);
                    break;
            }
        }
    }

    /**
     *
     * Process HTTP GET request on users
     *
     * @param array $segments
     */
    private function GET_users($segments) {
        $segments = $this->segments;
        /*
         * users/{userid}/jobs
         */
        if (isset($segments[1]) && isset($segments[2]) && $segments[2] === 'jobs') {
            $jobs = $this->GET_userWPSJobs($segments[1]);
            return RestoLogUtil::success("WPS jobs instance for user {$this->user->profile['userid']}", array (
                    data => $jobs
            ));
        }
        return RestoLogUtil::httpError(404);
    }
    
    /**
     * Process on HTTP method POST on /wps, /wps/execute and wps/clear
     */
    private function processPOST($data) {
        /*
         * HTTP/GET WPS 1.0 OGC services - not implemented
         */
        if (!isset($this->segments[0])) {
            
            $query = http_build_query($this->context->query);
            return $this->callWPSServer($this->wpsServerUrl . $query, $data);
        } else if (isset($this->segments[0]) && isset($this->segments[1]) && !isset($this->segments[2])) {
            
            switch ($this->segments[0]) {
                case 'jobs' :
                    $jobid = $this->segments[1];
                    if (is_numeric($jobid)) {
                        /*
                         * TODO : Remove specified job : used HTTP/POST instead of HTTP/DELETE
                         */
                         return $this->deleteJob();
                        return RestoLogUtil::httpError(501);
                    } else {
                        RestoLogUtil::httpError(400);
                    }
                    break;
                default :
                    RestoLogUtil::httpError(404);
                    break;
            }
        } else {
            RestoLogUtil::httpError(404);
        }
        RestoLogUtil::httpError(404);
    }
    
    /**
     *
     * @return multitype:multitype:
     */
    private function GET_userWPSJobs($userid) {

        /*
         * Only user admin can see WPS jobs of all users.
         */
        if ($this->user->profile['userid'] !== $userid) {
            if ($this->user->profile['groupname'] !== 'admin') {
                RestoLogUtil::httpError(403);
            }
        }

        /*
         * Checks user id pattern.
         */
        if (is_numeric($userid)) {
            /*
             * Gets user. 
             */
            $user = new RestoUser($this->context->dbDriver->get(RestoDatabaseDriver::USER_PROFILE, array (
                    'userid' => $userid 
            )), $this->context);

            if ($user->profile['userid'] === -1) {
                RestoLogUtil::httpError(400);
            } else {
                $identifier = $user->profile['email'];
                $escaped_identifier = pg_escape_string($identifier);
                
                $query = "SELECT * from usermanagement.jobs WHERE email='{$escaped_identifier}'";
                $jobs = pg_query($this->dbh, $query);
                
                $results = array ();
                while ($row = pg_fetch_assoc($jobs)) {
                    $results[] = $row;
                }
                // Updates status's jobs.
                $results = $this->updateStatusOfJobs($results);
                return $results;
            }
        } else {
            RestoLogUtil::httpError(400);
        }
    }
    

    /**
     * Returns current status of WPS process.
     * @param unknown $job
     * @return Ambigous <NULL, unknown, string>
     */
    private function getStatusOfJob($job){

        $statusLocation = isset($job['statuslocation']) ? $job['statuslocation'] : null;
        $status = isset($job['status']) ? $job['status'] : null;
        if ($status === 'ProcessSucceeded' || $status === 'ProcessFailed'){
            return $status;
        }
        
        /*
         * Gets current WPS process status.
         */
        if (!empty($statusLocation)) {                
            // Parses response in order to refresh status.
            try {
                $response = $this->callWPSServer($statusLocation, null, false);
                $wpsExecuteResponse = new ExecuteResponse($response->toXML());
                $status = $wpsExecuteResponse->getStatus();
            } catch (ExecuteResponseException $e) {
            } catch (Exception $e){}
        }
        return $status;
    }
    
    /**
     * Updates status of jobs.
     */
    private function updateStatusOfJobs($jobs) {
        foreach ($jobs as $job){
            $job['status'] = $this->getStatusOfJob($job);
            $this->updateJob($job);
        }
        return $jobs;
    }
   
    /**
     * We create a job
     *
     * @throws Exception
     */
    private function createJob($data) {
        try {
            // Inserting the job into database
            $querytime = !empty($data['query_time']) ? '\'' . pg_escape_string($data['query_time']) . '\'' : date("Y-m-d H:i:s");
            $email = '\'' . pg_escape_string($this->user->profile['email']) . '\'';
            $identifier = isset($data['identifier']) ? '\'' . pg_escape_string($data['identifier']) . '\'' : 'NULL';
            $status = isset($data['status']) ? '\'' . pg_escape_string($data['status']) . '\'' : 'NULL';
            $statusLocation = isset($data['statusLocation']) ? '\'' . $data['statusLocation'] . '\'' : 'NULL';
            $query = isset($data['query']) ? '\'' . $data['query'] . '\'' : 'NULL';
            $method = '\'' . pg_escape_string($this->context->method) . '\'';
            if ($this->context->method === 'POST'){
                $data = isset($data['data']) ? '\'' . $data['data'] . '\'' : 'NULL';
            }else{
                $data = 'NULL';
            }

            $values = array (
                    $email,
                    $identifier,
                    $querytime,
                    $status,
                    $statusLocation, 
                    $query, 
                    $data,
                    $method
            );

            /*
             * Stores alert.
             */
            $query = 'INSERT INTO usermanagement.jobs (email, identifier, querytime, status, statusLocation, query, data, method)' 
                        . 'VALUES (' . join(',', $values) . ')';
            $jobs = pg_query($this->dbh, $query);

        } catch (Exception $e) {
            RestoLogUtil::httpError($e->getCode(), $e->getMessage());
        }
    }
    
    /**
     * We edit a job
     *
     * @throws Exception
     */
    private function updateJob($data) {
        try {

            $status = isset($data['status']) ? pg_escape_string($data['status']) : 'NULL';
            $gid = pg_escape_string($data['gid']);
            
            /*
             * Stores alert.
             */
            $query = "UPDATE usermanagement.jobs SET status='{$status}' WHERE gid='{$gid}'";
            $jobs = pg_query($this->dbh, $query);

        } catch (Exception $e) {
            RestoLogUtil::httpError($e->getCode(), $e->getMessage());
        }
    }
    
    /**
     * We create a job
     *
     * @throws Exception
     */
    private function deleteJob() {
        try {
            $jobId = $this->segments[3];
            $jobs = pg_query($this->dbh, 'DELETE FROM usermanagement.jobs WHERE gid = \'' . pg_escape_string($jobId) . '\' 
                    AND email = \'' . $this->user->profile["email"] . '\'');
            return array (
                'status' => 'success',
                'message' => 'success' 
            );
        } catch (Exception $e) {
            RestoLogUtil::httpError($e->getCode(), $e->getMessage());
        }
    }
    
    /**
     * We call the WPS server
     */
    private function callWPSServer($url, $data = null, $saveExecuteResponse = true) {
        $this->context->outputFormat =  'xml';
        // Call the WPS Server
        $ch = curl_init($url);
        
        $options = array(
                CURLOPT_RETURNTRANSFER 	=> true,
                CURLOPT_VERBOSE			=> RestoLogUtil::$debug ? 1 : 0,
                CURLOPT_TIMEOUT         => 60,
                CURLOPT_RETURNTRANSFER  => 1,
                CURLOPT_FOLLOWLOCATION  => 1,
                CURLOPT_FAILONERROR     => 1
        );

        if ($this->context->method === 'POST'){
            /* Form data string */
            $options[CURLOPT_POST] = 1;
            $options[CURLOPT_POSTFIELDS] = $data[0];
        }
        
        /*
         * Sets request options.
         */
        curl_setopt_array($ch, $options);
        

        /*
         * Get the response
         */
        $response = curl_exec($ch);
        /*
         * Checks errors.
         */
        if(curl_errno($ch))
        {
            $error = curl_error($ch);
            /*
             * logs error.
            */
            error_log(__METHOD__ . ' ' . $error, 0);
            /*
             * Close cURL session
            */
            curl_close($ch);
            /*
             * Throw cURL exception
            */
            throw new Exception($error, 500);
        }

        curl_close($ch);

        //return $response;
        $xml = new WPSResponse($response);

        /*
         * Saves user's job into database (Only valid WPS processes).
         * Parses responses in order to check WPS processing service=WPS&request=execute.
         */
        if ($saveExecuteResponse == true){
            try {
                $wpsExecuteResponse = new ExecuteResponse($response);
                $data = array(
                        'query_time' => date("Y-m-d H:i:s"),
                        'identifier' => $wpsExecuteResponse->getIdentifier(),
                        'status' => $wpsExecuteResponse->getStatus(),
                        'statusLocation' => $this->setCorrectDomain($wpsExecuteResponse->getStatusLocation()), 
                        'query' => $url,
                        'data' => serialize($data)
                );
 
                $this->createJob($data);
            } catch (ExecuteResponseException $e) {
            }
        }       

        /*
         * Returns WPS response.
         */
        return $xml;        
    }

    /**
     * We set the correct domain for the Status Location 
     * answer as always returns localhost
     */
    private function setCorrectDomain($url){
        $wpsHost = parse_url($this->wpsServerUrl, PHP_URL_HOST);
        $recievedHost = parse_url($url, PHP_URL_HOST);
        return  str_replace($recievedHost, $wpsHost, $url);
    }
}


class ExecuteResponseException extends Exception { }