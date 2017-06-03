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
        if ($this->user->canExecuteWPS() === 1) {
            
            // We get URL segments and the http method
            $this->segments = $segments;
            $method = $this->context->method;

            // Switch on HTTP methods
            switch ($method) {
                /*
                 * HTTP/GET
                 */
                case HttpRequestMethod::GET :
                    return $this->processGET();
                /*
                 * HTTP/POST 
                 */
                case HttpRequestMethod::POST :
                    return $this->processPOST($data);
                /*
                 * 
                 */
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

        // Checks if WPS server url is configured
        if (empty($this->wpsServerUrl)) {
            throw new Exception('WPS Configuration problem', 500);
        }
        
        $wps = WPSRequest::getInstance($this->wpsServerUrl, $this->curl_options);

        // Gets wps rights
        $processes_enabled = $this->getEnabledProcesses($this->user->profile['groupname']);
        $response  = $wps->Get($this->context->query, $processes_enabled);
        $this->updateWpsResponseUrls($response);
        
        // saves job status into database
        if ($response->isExecuteResponse()) {
            $execute_response = new WPS_ExecuteResponse($response->toXML());

            $data = array_merge(
                    $execute_response->toArray(),
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
    
    private function GET_wps_outputs($segments) {
    
        if (!isset($segments[1]) || isset($segments[3])){
            return RestoLogUtil::httpError(404);
        }
        
        // ? Is statusLocation
        $job = $this->context->dbDriver->get(
                RestoDatabaseDriver::PROCESSING_JOBS_ITEMS,
                array(
                        'userid' => $this->user->profile['userid'],
                        'filters' => array(
                                'statuslocation=' . $this->context->dbDriver->quote($segments[1] . (isset($this->context->outputFormat) ? '.' . $this->context->outputFormat : ''))
                                )
                        )
                );

        // ? statusLocation exists 
        if (count($job) > 0) {
            $this->context->outputFormat =  'xml';
            $response = new WPS_Response(Curl::Get('http://localhost:4444/wps/outputs/' . $job[0]['statuslocation']));
            $this->updateWpsResponseUrls($response);
            return $response;
        }
        
        error_log(print_r($job, true), 0);

        

        //if output result
        // TODO : wpsfiles table
        /*
         * - identifier
         * - type
         * - value
         * - userid
         * 
         */ 
        

        
        // HTTP 404
        
        
        
        
    
//         // Checks if WPS server url is configured
//         if (empty($this->wpsServerUrl)) {
//             throw new Exception('WPS Configuration problem', 500);
//         }
//            $this->streamExternalUrl('http://localhost:4444/wps/outputs/' . $segments[1] . '.' . $this->context->outputFormat);
           return RestoLogUtil::httpError(404);
//         $response = Curl::Get('http://localhost:4444/wps/outputs/' . $segments[1], $this->curl_options);
//            return new WPS_Response($response);
    
//         // Gets wps rights
//         $processes_enabled = $this->getEnabledProcesses($this->user->profile['groupname']);
//         $response  = $wps->Get($this->context->query, $processes_enabled);
//         $this->updateWpsResponseUrls($response);
    
//         // saves job status into database
//         if ($response->isExecuteResponse()) {
//             $execute_response = new WPS_ExecuteResponse($response->toXML());
    
//             $data = array_merge(
//                     $execute_response->toArray(),
//                     array(
//                             'querytime' => date("Y-m-d H:i:s"),
//                             'method'    => HttpRequestMethod::GET,
//                             'data'      => $this->context->query
//                     ));
    
//             // Store job into database
//             $this->storeJob($this->user->profile['userid'], $data);
//         }
//         return $response;
    }

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
    private function GET_users($segments) {
        $segments = $this->segments;
        /*
         * users/{userid}/jobs
         */
        if (isset($segments[1]) 
                && isset($segments[2]) 
                && $segments[2] === 'jobs') {

            $jobs = $this->GET_userWPSJobs($segments[1]);
            return RestoLogUtil::success(
                    'WPS jobs instance for user ' . $this->user->profile['userid'], 
                    array ( 'data' => $jobs ));
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
//             $results = $this->updateStatusOfJobs($results);
//             $results =  $this->filterJobOutputs($results);

            return $results;
        } 
        // ? Is Bad Request
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
    private function filterJobOutputs($jobs) {
        
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

        $statusLocation     = ArrayUtil.get($job, 'statuslocation', null);
        $status             = ArrayUtil.get($job, 'status', null);
        $percentCompleted   = ArrayUtil.get($job, 'percentcompleted', 0);
        $outputs            = ArrayUtil.get($job, 'outputs', array());
        $statusMessage      = ArrayUtil.get($job, 'statusmessage',null);

        if ($status === 'ProcessSucceeded' || $status === 'ProcessFailed'){
            $percentCompleted = 100;
        } 
        // gets status from pywps server
        else {
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
        }
        return array(
                'status' => $status, 
                'percentCompleted' => $percentCompleted,
                'outputs' => $outputs,
                'statusMessage' => $statusMessage);
    }
    
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
        return array(/*'all',*/ 'echotiff', 'noinputsprocess', 'assyncprocess', 'QL_S2');
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
        return $this->context->dbDriver->store(RestoDatabaseDriver::PROCESSING_JOBS_ITEM,
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
        return $this->context->dbDriver->remove(RestoDatabaseDriver::PROCESSING_JOBS_ITEM,
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
        return $this->context->dbDriver->put(RestoDatabaseDriver::PROCESSING_JOBS_ITEM,
                array(
                        'userid' => $userid,
                        'data' => $data
                ));
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
     * 
     * @param unknown $xml_string
     */
    private function updateWPSURLs($text) {
        
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
