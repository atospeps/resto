<?php

/**
 * @author Atos
 * RESTo WPS proxy module.
 *
 *    | 
 *    | Resource                                                        | Description
 *    |_________________________________________________________________|______________________________________
 *    | HTTP/GET        wps/users/{userid}/jobs                         | List of all user's jobs
 *    | HTTP/GET        wps/users/{userid}/jobs/stats                   | User's completed jobs stats
 *    | HTTP/PUT        wps/users/{userid}/jobs/acknowledges            | Update user's jobs acknowledges
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
    
    private $curl_options = array();
    
    /**
     * WPS module route.
     */
    private $route;

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
        $this->curl_options = isset($this->context->modules[get_class($this)]['http']) ? $this->context->modules[get_class($this)]['http'] : array() ;
        
        // WPS module route
        $this->route = isset($this->context->modules[get_class($this)]['route']) ? $this->context->modules[get_class($this)]['route'] : '' ;
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

        if ($this->context->method !== 'GET' && $this->context->method !== 'POST' && $this->context->method !== 'PUT') {
            RestoLogUtil::httpError(404);
        }

        // Only autenticated user.
        if ($this->user->profile['userid'] === -1) {
            RestoLogUtil::httpError(401);
        }

        // Checks if user can execute WPS services
        if ($this->user->canExecuteWPS() === 1) {
            
            // We get URL segments and the http method
            $this->segments = $segments;
            $method = $this->context->method;

            // Switch on HTTP methods
            switch ($method) {
                case HttpRequestMethod::GET :
                    return $this->processGET();
                case HttpRequestMethod::POST :
                    return $this->processPOST($data);
                case 'PUT' :
                    return $this->processPUT($data);
                default :
                    RestoLogUtil::httpError(404);
            }
        }
        // Rights denied
        else {
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
         * wps?request=xxx&version=1.0.0&
         *      - GetCapabilities
         *      - DescribeProcess
         *      - Execute
         */
        if (!isset($this->segments[0])) {
            // HTTP/GET wps?
            return $this->GET_wps($this->segments);
        } 
        else {
            switch ($this->segments[0]) {
                /*
                 * HTTP/GET wps/users/{userid}/jobs
                 * HTTP/GET wps/users/{userid}/jobs/{jobid}
                 */
                case 'users':
                    return $this->GET_users($this->segments);
                /*
                 * Unknown route
                 */
                default :
                    /*
                     * Transform the proxy request received to WPS server request.
                     */
//                     $query = http_build_query($this->context->query);
//                     $path = implode('/', $this->segments) . (isset($this->context->outputFormat) ? ('.' . $this->context->outputFormat) : '');
//                     $url = $this->getWPSServerAddress() . '/' . $path . $query;

//                     /*
//                      * Returns response.
//                      */
//                     $response = Request::execute($url);

                    $processes_enabled = array('all');
                    $response =  $wps->Get($this->context->query, $processes_enabled);
                    return new WPSResponse($response);
                    if (!ob_start("ob_gzhandler")) ob_start();
                    echo  $response;
                    flush();

                    return null;
            }
        }
    }

    /**
     * HTTP/GET wps?
     * @param unknown $segments
     * @throws Exception
     * @return WPS_Response
     */
    private function GET_wps($segments) {

        $this->context->outputFormat =  'xml';

        // Checks if WPS server url is configured
        if (empty($this->wpsServerUrl)) {
            throw new Exception('WPS Configuration problem', 500);
        }
        
        $wps = WPSRequest::getInstance($this->wpsServerUrl, $this->curl_options);
        
        /*
         * ###################################################
         * 
         * TODO : Getting WPS rights - manage processes enabled
         * 
         * ###################################################
        */
        $processes_enabled = array(/*'all',*/ 'echotiff', 'noinputsprocess', 'QL_S2');
        $response  = $wps->Get($this->context->query, $processes_enabled);

        // save job status into database
        if ($response->isExecuteResponse()) {
            $execute_response = new WPS_ExecuteResponse($response->toXML());

            $data = array_merge(
                    $execute_response->toArray(),
                    array(
                            'querytime' => date("Y-m-d H:i:s"),
                            'method'    => HttpRequestMethod::GET,
                            'data'      => $this->context->query
                    ));
            $this->context->dbDriver->store(RestoDatabaseDriver::PROCESSING_JOBS_ITEM, 
                    array(
                            'userid' => $this->user->profile['userid'],
                            'data' => $data
                    ));
        }
        return $response;
    }
    
    /**
     *
     * Process HTTP GET request on users
     *
     * @param array $segments
     */
    private function GET_users($segments)
    {
        $segments = $this->segments;
        if (isset($segments[1]) && isset($segments[2]) && $segments[2] === 'jobs') {
            if (isset($segments[3]) && $segments[3] === 'stats') {
                // users/{userid}/jobs/stats
                $count = $this->getCompletedJobsStats($segments[1]);
                return RestoLogUtil::success("WPS jobs stats for user {$this->user->profile['userid']}", array (
                    data => $count
                ));
            } else {
                // users/{userid}/jobs
                $jobs = $this->GET_userWPSJobs($segments[1]);
                return RestoLogUtil::success("WPS jobs stats for user {$this->user->profile['userid']}", array (
                    data => $jobs
                ));
            }
        }
        return RestoLogUtil::httpError(404);
    }

    /**
     * Process on HTTP method POST on /wps, /wps/execute and wps/clear
     */
    private function processPOST($data)
    {
        /*
         * HTTP/GET WPS 1.0 OGC services - not implemented
         */
        if (!isset($this->segments[0])) {
            
            $query = http_build_query($this->context->query);
            // TODO return $this->callWPSServer($this->wpsServerUrl . $query, $data);
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
     * Process on HTTP method PUT
     */
    private function processPUT($data)
    {
        if (isset($this->segments[0]) && $this->segments[0] === 'users') {
            // HTTP/PUT wps/users/{userid}/jobs/acknowledges
            return $this->PUT_users();
        }
        RestoLogUtil::httpError(404);
    }
    
    /**
     *
     * Process HTTP PUT request on users
     *
     * @param array $segments
     */
    private function PUT_users($segments)
    {
        if (isset($this->segments[1]) && 
            isset($this->segments[2]) && $this->segments[2] === 'jobs' &&
            isset($this->segments[3]) && $this->segments[3] === 'acknowledges'
        ) {
            // users/{userid}/jobs/acknowledges
            $this->setJobsAcknowledges($this->segments[1]);
            return RestoLogUtil::success("WPS jobs acknowledges for user {$this->user->profile['userid']}", array ());
        }
        return RestoLogUtil::httpError(404);
    }
    
    /**
     * Set user's jobs acknowledges to TRUE
     */
    private function setJobsAcknowledges($userid)
    {
        if ($this->user->profile['userid'] !== $userid) {
            RestoLogUtil::httpError(403);
        }
        
        $query = "UPDATE usermanagement.jobs "
               . "SET acknowledge = TRUE "
               . "WHERE (status = 'ProcessSucceeded' OR status = 'ProcessFailed') "
               . "AND email = '" . pg_escape_string($this->user->profile['email']) . "' ";
        
        $result = pg_query($this->dbh, $query);
    }
    
    /**
     * Returns the completed jobs (succeeded + failed) to be notified
     * 
     * @param {string} userid
     * @return {int} count
     */
    private function getCompletedJobsStats($userid)
    {
        if ($this->user->profile['userid'] !== $userid) {
            RestoLogUtil::httpError(403);
        }
        
        $query = "SELECT count(status) "
               . "FROM usermanagement.jobs "
               . "WHERE (status = 'ProcessSucceeded' OR status = 'ProcessFailed') "
               . "AND email = '" . pg_escape_string($this->user->profile['email']) . "' "
               . "AND acknowledge = FALSE";
        $result = pg_query($this->dbh, $query);
        $row = pg_fetch_assoc($result);
        
        return (int)$row['count'];
    }
    
    /**
     *
     * @return multitype:multitype:
     */
    private function GET_userWPSJobs($userid) {

        // Only user admin can see WPS jobs of all users.
        if ($this->user->profile['userid'] !== $userid) {
            if ($this->user->profile['groupname'] !== 'admin') {
                RestoLogUtil::httpError(403);
            }
        }

        // Checks user id pattern.
        if (is_numeric($userid)) {
            
            $results = $this->context->dbDriver->get(RestoDatabaseDriver::PROCESSING_JOBS_ITEMS, array('userid' => $userid));
            // Updates status's jobs.
//             $results = $this->updateStatusOfJobs($results);
//             $results =  $this->filterJobOutputs($results);

            return $results;
        } 
        // Bad Request
        else {
            RestoLogUtil::httpError(400);
        }
    }

    /**
     * Filters outputs.
     * Returns only outputs of type 'string' and value which matche URL pattern.
     * @param unknown $jobs
     * @return unknown
     */
    private function filterJobOutputs($jobs){
        
        foreach ($jobs as &$job){
            if (!empty($job['outputs'])){
            
                // Returns only URL path (to download)
                $filteredoutputs = array();
                foreach ($job['outputs'] as $output){
                    $type = $output['type'];
                    $value = $output['value'];
            
                    if ($type === 'string' && filter_var($value, FILTER_VALIDATE_URL) !== false){
                        $filteredoutputs[] = $output;
                    }
                }
                $job['outputs'] = $filteredoutputs;
            }
            if (!empty($job['statuslocation'])){
                $job['statuslocation'] = $this->updateWPSURLs($job['statuslocation']);
            }
        }
        
        return $jobs;
    }
    

    /**
     * Returns current status of WPS process.
     * @param unknown $job
     * @return Ambigous <NULL, unknown, string>
     */
    private function getStatusOfJob($job){

        $statusLocation = isset($job['statuslocation']) ? $job['statuslocation'] : null;
        $status = isset($job['status']) ? $job['status'] : null;
        $percentCompleted = isset($job['percentcompleted']) ? $job['percentcompleted'] : 0;
        $outputs = isset($job['outputs']) ? $job['outputs'] : null;
        $statusMessage = isset($job['statusmessage']) ? $job['statusmessage'] : null;

        if ($status === 'ProcessSucceeded' || $status === 'ProcessFailed'){
            return array($status, 100, $outputs, $statusMessage);
        }

        /*
         * Gets current WPS process status.
         */
        if (!empty($statusLocation)) {
            try {
                /* TODO */
//                 $response = $this->callWPSServer($statusLocation, null, false);

//                 // Parses response in order to refresh status.            
//                 $wpsExecuteResponse = new ExecuteResponse($response->toXML());
//                 $status = $wpsExecuteResponse->getStatus();
//                 $percentCompleted = $wpsExecuteResponse->getPercentCompleted();
//                 $outputs = $wpsExecuteResponse->getOutputs();
//                 $statusMessage = $wpsExecuteResponse->getStatusMessage();
            } catch (ExecuteResponseException $e) {
            } catch (Exception $e){
                error_log("WPS:getStatusOfJob:{$job['gid']} :" . $e->getMessage(), 0);
            }
        }
        return array($status, $percentCompleted, $outputs, $statusMessage);
    }
    
    /**
     * Updates status of jobs.
     */
    private function updateStatusOfJobs($jobs) {
        foreach ($jobs as $job){
            list($status, $percentCompleted, $outputs, $statusMessage) = $this->getStatusOfJob($job);
            $job['status'] = $status;
            $job['percentcompleted'] = $percentCompleted;
            $job['outputs'] = $outputs;
            $job['statusmessage'] = $statusMessage;
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
            $statusMessage = isset($data['statusMessage']) ? '\'' . pg_escape_string($data['statusMessage']) . '\'' : 'NULL';
            $statusLocation = isset($data['statusLocation']) ? '\'' . $data['statusLocation'] . '\'' : 'NULL';
            $percentCompleted = isset($data['percentcompleted']) ? '\'' . $data['percentcompleted'] . '\'' : 0;
            $outputs = isset($data['outputs']) ? '\'' . pg_escape_string(json_encode($data['outputs'])) . '\'' : 'NULL';
            
            $values = array (
                    $email,
                    $identifier,
                    $querytime,
                    $status,
                    $statusMessage,
                    $statusLocation,
                    $percentCompleted,
                    $outputs
            );
            /*
             * Stores alert.
             */
            $query = 'INSERT INTO usermanagement.jobs (email, identifier, querytime, status, statusmessage, statusLocation, percentCompleted, outputs) ' 
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
            $statusMessage = isset($data['statusmessage']) ? '\'' . pg_escape_string($data['statusmessage']) . '\'' : 'NULL';
            $percentCompleted = isset($data['percentcompleted']) ? pg_escape_string($data['percentcompleted']) : 0;
            $outputs = isset($data['outputs']) ? '\'' . pg_escape_string(json_encode($data['outputs'])) . '\'' : 'NULL';
            $gid = pg_escape_string($data['gid']);

            /*
             * Stores alert.
             */
            $query = "UPDATE usermanagement.jobs "
                    . "SET status='{$status}', percentcompleted={$percentCompleted}, outputs={$outputs}, statusmessage={$statusMessage} "
                    . "WHERE gid='{$gid}'";
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
        if (isset($data['gid'])) {
            try {
                $jobid = pg_escape_string($data['gid']);
                $jobs = pg_query($this->dbh, 'DELETE FROM usermanagement.jobs WHERE gid = \'' . $jobid . '\'');
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
     * Returns IP address of wps server.
     */
    private function getWPSServerAddress(){
        $wps_host = parse_url($this->wpsServerUrl, PHP_URL_HOST);
        $wps_port = parse_url($this->wpsServerUrl, PHP_URL_PORT);

        return ($wps_host . ':' . $wps_port);
    }

    /**
     * 
     * @param unknown $xml_string
     */
    private function updateWPSURLs($text){
        
        $server_endpoint = parse_url($this->context->baseUrl);
        $server_host = $server_endpoint['host'];
        $server_port = isset($server_endpoint['port']) ? $server_endpoint['port'] : 80;
        
        $server_address = $server_host . ':' . $server_port . $server_endpoint['path'] . '/' . $this->route;
        
        $wps_host = parse_url($this->wpsServerUrl, PHP_URL_HOST);
        $wps_port = parse_url($this->wpsServerUrl, PHP_URL_PORT);
        $wps_address = $wps_host . ':' . $wps_port; 

        return str_replace($wps_address, $server_address, $text);
    }
}


class ExecuteResponseException extends Exception { }

/*
 * HTTP request method type
 */
abstract class HttpRequestMethod {
    const GET       = 'GET';
    const POST      = 'POST';
    const PUT       = 'PUT';
    const DELETE    = 'DELETE';
    const OPTIONS   = 'OPTIONS';
    const HEAD      = 'HEAD';
    const TRACE     = 'TRACE';
    const CONNECT   = 'CONNECT';
}
