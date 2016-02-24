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
        if ($this->context->method !== 'GET' && $this->context->method !== 'POST') {
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
        }
        // else if (isset($this->segments[0]) && isset($this->segments[1]) && !isset($this->segments[2])) {
        
        // switch ($this->segments[0]) {
        // case 'jobs' :
        // $jobid = $this->segments[1];
        // if (is_numeric($jobid)) {
        // /*
        // * TODO : Remove specified job : used HTTP/POST instead of HTTP/DELETE
        // */
        // // return $this->deleteJob($data);
        // return RestoLogUtil::httpError(501);
        // } else {
        // RestoLogUtil::httpError(400);
        // }
        // break;
        // default :
        // RestoLogUtil::httpError(404);
        // break;
        // }
        // } /*
        // * Unknown route
        // */
        // else {
        // RestoLogUtil::httpError(404);
        // }
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
            $response = $this->callWPSServer($statusLocation, null, false);
            
            
            // Parses response in order to refresh status.
            try {
                $wpsExecuteResponse = new ExecuteResponse($response->toXML());
                $status = $wpsExecuteResponse->getStatus();
            } catch (ExecuteResponseException $e) {
            }
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
            
            $values = array (
                    $email,
                    $identifier,
                    $querytime,
                    $status,
                    $statusLocation 
            );
            /*
             * Stores alert.
             */
            $query = 'INSERT INTO usermanagement.jobs (email, identifier, querytime, status, statusLocation) ' 
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
    private function deleteJob($data) {
        // Delete a job using the job id
        if (isset($data['jid'])) {
            try {
                $jobs = pg_query($this->dbh, 'DELETE FROM usermanagement.jobs WHERE gid = \'' . pg_escape_string($data['jid']) . '\'');
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
                        'statusLocation' => $wpsExecuteResponse->getStatusLocation()
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
}

/**
 * 
 * WPS Response
 */
class WPSResponse {

    // xml as string
    protected $xml;

    /**
     * 
     * @param unknown $pXml
     */
    public function __construct($pXml){
        $this->xml = $pXml;
    }

    /**
     * 
     */
    public function toXML(){
        return $this->xml;
    }
}
/**
 * 
 * WPS:ExecuteResponse
 *
 */
class ExecuteResponse extends WPSResponse {

    /*
     * Process identifier.
     */
    private $identifier;
    
    /*
     * WPS Service instance. 
     */
    private $serviceInstance;
    
    /*
     * Status.
     */
    private $status;

    /*
     * To improve this WPS parser, create WPSStatus, WPSProcess, .. class...
     * class WPSStatus {
     *     private $status;
     *     private $statusMessage;
     *     private $statusTime;
     *     private $percentCompleted;
     * }
     */
    
    /*
     * Status location.
     */
    private $statusLocation;
    
    /*
     * Output definitions.
     */
    private $outputDefinitions;
    
    /*
     * Process outputs.
     */
    private $processOutputs;
    
    /*
     * WPS status events.
     */
    public static $statusEvents = array (
            'ProcessAccepted',
            'ProcessSucceeded',
            'ProcessFailed',
            'ProcessStarted',
            'ProcessPaused' 
    );

    /**
     * 
     * @param unknown $pXml
     */
    function __construct($pXml){
        parent::__construct($pXml);
        
        $sxe = new SimpleXMLElement($this->xml);
        $sxe->registerXPathNamespace('ows', 'http://www.opengis.net/ows/1.1');
        $sxe->registerXPathNamespace('xlink', 'http://www.w3.org/1999/xlink');
        $sxe->registerXPathNamespace('wps', 'http://www.opengis.net/wps/');
        $sxe->registerXPathNamespace('xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        
        $result = $sxe->xpath('//wps:ExecuteResponse');
        if (!$result && count($result) == 0) {
            throw new ExecuteResponseException('wps:ExecuteResponse::__contruct : Invalid xml');
        }
        $this->parseExecuteResponse($result[0]);        
    }
    /**
     * 
     * @param SimpleXMLElement $wps_ExecuteResponse
     */
    private function parseExecuteResponse(SimpleXMLElement $wps_ExecuteResponse){
        $attributes = $wps_ExecuteResponse->attributes();
        $this->statusLocation = isset($attributes['statusLocation']) ? $attributes['statusLocation']->__toString() : null;
        $this->serviceInstance = isset($attributes['serviceInstance']) ? $attributes['serviceInstance']->__toString() : null;
        
        $status = $wps_ExecuteResponse->xpath('//wps:Status');
        if ($status && count($status) > 0){
            $this->parseStatus($status[0]);
        }
        
        $process = $wps_ExecuteResponse->xpath('//wps:Process');
        if ($process && count($process) > 0){
            $this->parseProcess($process[0]);
        }
    }

    /**
     * TODO
     * @param SimpleXMLElement $wps_processFailed
     */
    private function parseProcessFailed(SimpleXMLElement $wps_processFailed){}

    /**
     * 
     * @param SimpleXMLElement $wps_process
     */
    private function parseProcess(SimpleXMLElement $wps_process){
        
        $identifier = $wps_process->xpath('//ows:Identifier');
        if ($identifier && count($identifier)>0){
            $this->identifier = $identifier[0]->__toString();
        }
    }

    /**
     * 
     * @param SimpleXMLElement $wps_Status
     */
    private function parseStatus(SimpleXMLElement $wps_Status) {

        foreach (self::$statusEvents as $statusEvent) {
            $status = $wps_Status->xpath("//wps:$statusEvent");
            if ($status && count($status) > 0){
                $this->status = $statusEvent;
                break;
            }
        }
        if ($this->status === 'ProcessFailed'){
            $this->parseProcessFailed($status[0]);
        }
    }
    
    /**
     * Returns WPS process identifier.
     */
    public function getIdentifier(){
        return $this->identifier;
    }
    
    /**
     * If ExecuteResponse, returns status if present, otherwise null.
     */
    public function getStatus(){    
        return $this->status;
    }
    
    /**
     * If ExecuteResponse, returns status if present, otherwise null.
     */
    public function getStatusLocation(){
        return $this->statusLocation;
    }
}

class ExecuteResponseException extends Exception { }
