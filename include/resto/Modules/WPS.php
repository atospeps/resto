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
    private $wpsRequestManager;

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
        $wpsServerUrl = isset($this->context->modules[get_class($this)]['wpsServerUrl']) ? $this->context->modules[get_class($this)]['wpsServerUrl'] : null ;
        $outputsUrl = isset($this->context->modules[get_class($this)]['outputsUrl']) ? $this->context->modules[get_class($this)]['outputsUrl'] : null ;
        $curlOpts = isset($this->context->modules[get_class($this)]['curlOpts']) ? $this->context->modules[get_class($this)]['curlOpts'] : array() ;
        $this->wpsRequestManager = new WPS_RequestManager($wpsServerUrl, $outputsUrl, $curlOpts);

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

        // Only GET method on 'search' route with json outputformat is accepted
        if ($this->context->method !== HttpRequestMethod::GET 
                && $this->context->method !== HttpRequestMethod::POST) {
            RestoLogUtil::httpError(404);
        }

        // Only autenticated user.
        if ($this->user->profile['userid'] === -1) {
            RestoLogUtil::httpError(401);
        }

        // Checks if user can execute WPS services
        if ($this->user->canExecuteWPS()) {
            
            // We get URL segments and the http method
            $this->segments = $segments;
            $method = $this->context->method;

            // Switch on HTTP methods
            switch ($method) {
                /*
                 * HTTP/GET
                 */
                case HttpRequestMethod::GET:
                    return $this->processGET();
                /*
                 * HTTP/POST 
                 */
                case HttpRequestMethod::POST:
                    return $this->processPOST($data);
                /*
                 * 
                 */
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
        else 
        {
            switch ($this->segments[0]) {
                /*
                 * HTTP/GET wps/users/{userid}/jobs
                 * HTTP/GET wps/users/{userid}/jobs/{jobid}
                 */
                case 'users':
                    return $this->GET_users($this->segments);
                /*
                 * 
                 */
                case 'outputs':
                    return $this->GET_wps_outputs($this->segments);
                /*
                 * Unknown route
                 */
                default:
                    return RestoLogUtil::httpError(404);
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

        // Gets wps rights
        $processes_enabled = $this->getEnabledProcesses($this->user->profile['groupname']);
        $response  = $this->wpsRequestManager->Get($this->context->query, $processes_enabled);
        $this->updateWpsResponseUrls($response);
        
        // saves job status into database
        if ($response->isExecuteResponse()) {
            $executeResponse = new WPS_ExecuteResponse($response->toXML());

            $data = array_merge(
                    $executeResponse->toArray(),
                    array(
                            'querytime' => date("Y-m-d H:i:s"),
                            'method'    => HttpRequestMethod::GET,
                            'data'      => $this->context->query
                    ));
            // Store job into database
            $this->storeJob($this->user->profile['userid'], $data);
        }
        return $response;
    }
    
    /**
     * 
     * @param unknown $segments
     * @return WPS_Response
     */
    private function GET_wps_outputs($segments) {
    
        if (!isset($segments[1]) || isset($segments[3])){
            return RestoLogUtil::httpError(404);
        }

        // ? Is statusLocation
        $statusLocation = $segments[1] . (isset($this->context->outputFormat) ? '.' . $this->context->outputFormat : '');
        $job = $this->context->dbDriver->get(
                RestoDatabaseDriver::PROCESSING_JOBS_ITEMS,
                array(
                        'userid' => $this->user->profile['userid'],
                        'filters' => array(
                                'statuslocation=' . $this->context->dbDriver->quote($statusLocation)
                                )
                        )
                );

        // ? statusLocation exists 
        if (count($job) > 0) {
            $response = new WPS_Response(Curl::Get($this->wpsRequestManager->getOutputsUrl() . $job[0]['statuslocation']));
            $this->updateWpsResponseUrls($response);
            return $response;
        }

        //if output result
        /*
         * ======================================================
         * TODO : wpsfiles table
         * ======================================================
         */ 
        /*
         * - identifier
         * - type
         * - value
         * - userid
         * 
         */ 
        
        
        // HTTP 404
        return RestoLogUtil::httpError(404);
    }

    /**
     * 
     * @param unknown $url
     * @param string $type
     */
    private function streamExternalUrl($url, $type=null) {
        $handle = fopen($url, "rb");
        if ($handle === false) {
            RestoLogUtil::httpError(500, 'Resource cannot be downloaded');
        }
        header('HTTP/1.1 200 OK');
        header('Content-Disposition: attachment; filename="' . basename($url) . '"');
        header('Content-Type: ' . isset($type) ? $type : 'application/unknown');
        while (!feof($handle) && (connection_status() === CONNECTION_NORMAL)) {
            echo fread($handle, 10 * 1024 * 1024);
            flush();
        }
        return fclose($handle);
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
                    'data' => $count
                ));
            } else {
                // users/{userid}/jobs
                $jobs = $this->GET_userWPSJobs($segments[1]);
                return RestoLogUtil::success("WPS jobs stats for user {$this->user->profile['userid']}", array (
                    'data' => $jobs
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
            return $this->PUT_users($this->segments);
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

        // User identifier pattern is valid ?
        if (is_numeric($userid)) {

            $results = $this->context->dbDriver->get(
                    RestoDatabaseDriver::PROCESSING_JOBS_ITEMS, 
                    array('userid' => $userid));

            // TODO Updates status's jobs.
            $results = $this->updateStatusOfJobs($results);
            return $results;
        }
        // ? Is Bad Request
        else {
            RestoLogUtil::httpError(400);
        }
    }
    
    /**
     * 
     * @param WPS_Response $response
     */
    private function updateWpsResponseUrls(WPS_Response $response){
        // replace pywps outputs url
        $response->replace(
                'http://localhost:4444/wps/outputs/',
                'http://192.168.56.102:4444/resto/wps/outputs/');
        // replace pywps server address
        $response->replace(
                'http://localhost:4444/cgi-bin/pywps.cgi',
                'http://192.168.56.102:4444/resto/wps');
        
    }

    /**
     * ************************************************************************
     * PROCESSING - RIGHTS
     * ************************************************************************
     */
    
    /**
     * Returns wps rights
     * @param unknown $groupname
     * @return multitype:string
     */
    private function getEnabledProcesses($groupname) {
        return array('all', /*'all',*/ 'echotiff', 'noinputsprocess', 'assyncprocess', 'QL_S2');
    }
    
    /**
     * 
     * @param unknown $groupname
     */
    private function getProactiveAccount($groupname){
        return null;
    }
    
    /** 
     * ************************************************************************
     * PROCESSING - JOBS FUNCTIONS
     * ************************************************************************
     */
    
    /**
     * 
     * @param unknown $userid
     * @param unknown $data
     */
    private function storeJob($userid, $data){
        return $this->context->dbDriver->store(
                RestoDatabaseDriver::PROCESSING_JOBS_ITEM,
                array(
                        'userid' => $userid,
                        'data' => $data
                ));
    }
    
    /**
     * We remove a job
     *
     * @throws Exception
     */
    private function removeJob($userid, $data) {
        return $this->context->dbDriver->remove(
                RestoDatabaseDriver::PROCESSING_JOBS_ITEM,
                array(
                        'userid' => $userid,
                        'data' => $data
                ));
    }
    
    /**
     * We edit a job
     *
     * @throws Exception
     */
    private function updateJob($userid, $data) {
        return $this->context->dbDriver->update(
                RestoDatabaseDriver::PROCESSING_JOBS_ITEM,
                array(
                        'userid' => $userid,
                        'data' => $data
                ));
    }

    /**
     * Updates status of jobs.
     */
    private function updateStatusOfJobs($jobs) {
        foreach ($jobs as &$job) {
            if ($job['status'] !== 'ProcessSucceeded' && $job['status'] !== 'ProcessFailed') {

                if (($executeResponse = $this->wpsRequestManager->getExecuteResponse($job['statuslocation'])) != false) {

                    if ($job['status'] != $executeResponse->getStatus() 
                            || $job['statusmessage'] != $executeResponse->getStatusMessage()
                            || $job['percentcompleted'] != $executeResponse->getPercentCompleted()) {

                        $job['status'] = $executeResponse->getStatus();
                        $job['statusmessage'] = $executeResponse->getStatusMessage();
                        $job['percentcompleted'] = $executeResponse->getPercentCompleted();
                        $job['outputs'] = $executeResponse->getOutputs();
                        
                        $this->updateJob($job['userid'], $job);
                    }
                }
            }
        }
        return $jobs;
    }

}

/**
 * 
 * @author root
 *
 */
class ExecuteResponseException extends Exception { }
/**
 * 
 */
abstract class ArrayUtil {
    public static function get($array, $key, $default=null){
        return isset($array[$key]) ? $array[$key] : $default;
    }
}

/**
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
