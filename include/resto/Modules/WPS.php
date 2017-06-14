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
 *    | HTTP/GET        wps/users/{userid}/processings                  | List of all processings enabled for the user
 *    |
 *    | HTTP/GET        wps?                                            | HTTP/GET wps services (OGC)
 *    | HTTP/POST       wps                                             | HTTP/POST wps services (OGC) - Not implemented
 *    |   
 *    | HTTP/GET        wps/processings                                 | List of all processings (admin only)
 *    | HTTP/GET        wps/processings/{identifier}/describe           | Get the description of processing {identifier}
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
    
    /*
     * Public wps server address
     * http(s)://
     */
    private $externalServerAddress;
    private $externalOutputsUrl;
    
    private $replacements = array();

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

        $module = $this->context->modules[get_class($this)];

        if (empty($module['serverAddress']) || empty($module['outputsUrl'])){
            throw new Exception('WPS server configuration - problem', 500);
        }
        $this->externalServerAddress = $module['serverAddress'];
        $this->externalOutputsUrl = $module['outputsUrl'];
        $wpsConf = isset($module['pywps']) ? $module['pywps'] : array() ;
        $curlOpts = isset($module['curlOpts']) ? $module['curlOpts'] : array() ;
        $this->wpsRequestManager = new WPS_RequestManager($wpsConf, $curlOpts);

        // wps response replacements
        $this->replacements[$this->wpsRequestManager->getResponseServerAddress()] = $this->externalServerAddress;
        $this->replacements[$this->wpsRequestManager->getResponseOutputsUrl()] = $this->externalOutputsUrl;

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
    public function run($segments, $data = array())
    {
        // Allowed HTTP method
        if ($this->context->method !== HttpRequestMethod::GET 
                && $this->context->method !== HttpRequestMethod::POST
                && $this->context->method !== HttpRequestMethod::PUT) {
            RestoLogUtil::httpError(404);
        }

        // Only autenticated user.
        if ($this->user->profile['userid'] === -1) {
            RestoLogUtil::httpError(401);
        }

        // Checks if user can execute WPS services
        if ($this->user->canExecuteWPS() === false) {
            RestoLogUtil::httpError(403);
        }
            
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
             * HTTP/PUT
             */
            case 'PUT' :
                return $this->processPUT($data);
            /*
             * Error
             */
            default :
                RestoLogUtil::httpError(404);
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
                 * HTTP/GET wps/users/{userid}/jobs/download
                 * HTTP/GET wps/users/{userid}/jobs/{jobid}/download
                 */
                case 'users':
                    return $this->GET_users($this->segments);
                /*
                 * 
                 */
                case 'outputs':
                    return $this->GET_wps_outputs($this->segments);
                /*
                 * HTTP/GET wps/processings (admin only)
                 * HTTP/GET wps/processings/{identifier}/describe
                 */
                case 'processings':
                    return $this->GET_wps_processings($this->segments);
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
    private function GET_wps($segments) 
    {
        $this->context->outputFormat =  'xml';

        // Gets wps rights
        $processes_enabled = $this->getEnabledProcessings($this->user->profile['groupname']);
        $response  = $this->wpsRequestManager->Get($this->context->query, $processes_enabled);
        $response->replaceTerms($this->replacements);
        
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
        $resource = $segments[1] . (isset($this->context->outputFormat) ? '.' . $this->context->outputFormat : '');
        $job = $this->context->dbDriver->get(
                RestoDatabaseDriver::PROCESSING_JOBS_ITEMS,
                array(
                        'userid' => $this->user->profile['userid'],
                        'filters' => array(
                                'statuslocation=' . $this->context->dbDriver->quote($resource)
                                )
                        )
                );

        // ? statusLocation exists 
        if (count($job) > 0) {
            $response = $this->wpsRequestManager->getExecuteResponse($job[0]['statuslocation']);
            if ($response == false){
                return RestoLogUtil::httpError(404);
            }
            $response->replaceTerms($this->replacements);
            return $response;
        }

        // ? Is processings file result
        $result = $this->getProcessingResults(
                $this->user->profile['userid'],
                null,
                array(
                        'value=' . $this->context->dbDriver->quote($resource)
                ),
                $this->wpsRequestManager->getOutputsUrl());
        if (count($result) > 0) {
            return $this->streamExternalUrl($result[0]['value'], $result[0]['type']);
        }
        
        // HTTP 404
        return RestoLogUtil::httpError(404);
    }

    /**
     * 
     * @param unknown $url
     * @param string $type
     */
    private function streamExternalUrl($url, $type=null) {
        return Curl::Download($url, $type, $this->wpsRequestManager->getCurlOptions());
    }
    
    /**
     *
     * Process HTTP GET request on users
     * 
     *              HTTP/GET wps/users/{userid}/jobs
     *      TODO    HTTP/GET wps/users/{userid}/jobs/{jobid}
     *      TODO    HTTP/GET wps/users/{userid}/jobs/download
     *      TODO    HTTP/GET wps/users/{userid}/jobs/{jobid}/download
     *              HTTP/GET wps/users/{userid}/jobs/stats
     *              HTTP/GET wps/users/{userid}/jobs/results
	 * 				HTTP/GET wps/users/{userid}/processings
     *
     * @param array $segments
     */
    private function GET_users($segments)
    {
        if (!isset($segments[1])) {
            RestoLogUtil::httpError(404);
        }

        $userid = $segments[1];
        if ($this->user->profile['userid'] !== $userid) {
            RestoLogUtil::httpError(403);
        }
        
        if (isset($segments[2])) {
            // jobs
            if ($segments[2] === 'jobs') {
                if (!isset($segments[3])){
                    // users/{userid}/jobs
                    $jobs = $this->GET_userWPSJobs($segments[1]);
                    return RestoLogUtil::success("WPS jobs stats for user {$this->user->profile['userid']}", array (
                            'data' => $jobs
                    ));
                }
                if (!isset($segments[4])) {
                    switch ($segments[3]) {
                        case 'stats':
                            // users/{userid}/jobs/stats
                            $count = $this->getCompletedJobsStats($userid);
                            return RestoLogUtil::success("WPS jobs stats for user {$userid}", array (
                                'data' => $count
                            ));
                        case 'results':
                            // users/{userid}/jobs/results
                            $results = $this->getProcessingResults(
                                $this->user->profile['userid'],
                                null,
                                array(),
                                $this->externalOutputsUrl);
                            
                            return RestoLogUtil::success(
                                    "WPS jobs results for user {$this->user->profile['userid']}",
                                    array ('data' => $results));
                        default:
                            break;
                    }
                }
                else {
                    switch ($segments[4]) {
                        case 'download':
                            // ? Is Bad Request                            
                            if (!is_numeric($segments[3])) {
                                RestoLogUtil::httpError(400);
                            }
                            // ? Is processings file result
                            $result = $this->getProcessingResults(
                                    $this->user->profile['userid'],
                                    $segments[3],
                                    array(),
                                    $this->wpsRequestManager->getOutputsUrl());
                            if (count($result) > 0) {                               
                                return $this->streamExternalUrl($result[0]['value'], $result[0]['type']);
                            }
                        default:
                            break;
                    }
                }
            }
            // processings
            elseif ($segments[2] === 'processings') {
                // users/{userid}/processings
                return $this->GET_wps_processings();
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
     * Returns the completed jobs (succeeded + failed)
     * 
     * @param {string} userid
     * @return {int} count
     */
    private function getCompletedJobsStats($userid)
    {
        if ($this->user->profile['userid'] !== $userid) {
            RestoLogUtil::httpError(403);
        }
        
        return $this->context->dbDriver->get(
            RestoDatabaseDriver::PROCESSING_JOBS_STATS, 
            array('email' => $this->user->profile['email'])
        );
    }
    
    /**
     * 
     * @param unknown $userid
     * @param unknown $job
     * @return number
     */
    private function getProcessingResults($userid, $jobid = null, $filters= array(), $rootPath='') {

        $items = array();

        // ? User id not setted
        if (!isset($userid)) {
            return $items;
        }
        $filters[] = 'usermanagement.wps_results.userid=' . $this->context->dbDriver->quote($userid);

        // ? Job id is setted
        if (isset($jobid)) {
            $filters[] = 'usermanagement.wps_results.jobid=' . $this->context->dbDriver->quote($jobid);
        }

        $oFilter = implode(' AND ', $filters);

        $rootPathOutputsUrl = isset($rootPath) ? $rootPath : '';
        
        // Query
        $query = 'SELECT usermanagement.jobs.identifier as processing, usermanagement.jobs.statusTime as datetime, usermanagement.wps_results.identifier, type,' 
                . $this->context->dbDriver->quote($rootPathOutputsUrl) .' || value as value FROM usermanagement.wps_results' 
                . ' INNER JOIN usermanagement.jobs ON usermanagement.jobs.gid = usermanagement.wps_results.jobid WHERE ' . $oFilter . ' ORDER BY usermanagement.jobs.statusTime DESC';
        

        return $this->context->dbDriver->fetch($this->context->dbDriver->query($query));
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
     * ************************************************************************
     * PROCESSING - RIGHTS
     * ************************************************************************
     */
    
    /**
     * Returns WPS rights for the group
     * 
     * @param string $groupname
     * @return array
     */
    private function getEnabledProcessings($groupname)
    {
        if (empty($groupname)) {
            return array();
        }
        
        // get group id
        $group = $this->context->dbDriver->get(
            RestoDatabaseDriver::GROUP, 
            array('gidOrGroupName' => $groupname)
        );
        
        // get WPS rights
        $wpsRights = $this->context->dbDriver->get(
            RestoDatabaseDriver::WPS_GROUP_RIGHTS, 
            array('groupid' => $group['id'])
        );
        
        return $wpsRights;
    }
    
    /**
     * TODO
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
                        $job['statusTime'] = $executeResponse->getStatusTime();
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
    
    /**
     * Get WPS processings
     * 
     */
    private function GET_wps_processings($segments = null)
    {
        if (isset($segments) && isset($segments[1]) && isset($segments[2]) && $segments[2] === 'describe') {
            // processings/{identifier}/describe
            $description = $this->getProcessingDescription($segments[1]);
            return RestoLogUtil::success("WPS processing description for identifier {$segments[1]}", array (
                'data' => $description
            ));
        } else {
            // processings
            return $this->getProcessingsList();
        }
        
        return RestoLogUtil::httpError(404);
    }
    
    /**
     * Get processing description
     */
    function getProcessingDescription($identifier)
    {
        if ($this->user->profile['groupname'] === 'admin') {
            $wpsRights = array('all');
        } else {
            $wpsRights = $this->getEnabledProcessings($this->user->profile['groupname']);
        }
        
        $response = $this->wpsRequestManager->Get(
                array(
                        'request' => 'describeProcess',
                        'service' => 'WPS',
                        'version' => '1.0.0',
                        'identifier' => $identifier
                ),
                $wpsRights
        );
        
        $dom = new DOMDocument;
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        
        $dom->loadXML($response->toXML());
        
        return $dom->getElementsByTagName('ProcessDescription')->item(0)->getElementsByTagNameNS('http://www.opengis.net/ows/1.1', 'Abstract')->item(0)->nodeValue;
    }
    
    /**
     * Get WPS processings
     */
    function getProcessingsList()
    {
        // get WPS rights
        if ($this->user->profile['groupname'] === 'admin') {
            $wpsRights = array('all');
        } else {
            $wpsRights = $this->getEnabledProcessings($this->user->profile['groupname']);
        }
        
        $response = $this->wpsRequestManager->Get(
            array(
                'request' => 'getCapabilities',
                'service' => 'WPS',
                'version' => '1.0.0'
            ), 
            $wpsRights
        );

        $dom = new DOMDocument;        
        $dom->loadXML($response->toXML());
        
        $results = array();
        $processes = $dom->getElementsByTagNameNS('http://www.opengis.net/wps/1.0.0', 'Process');

        if ($processes && $processes->length > 0) {
            // on parcours les process de la reponse et on supprime les process non autorisés
            foreach ($processes as $process) {
                $identifier = null;
                $title = null;

                $node = $process->getElementsByTagNameNS('http://www.opengis.net/ows/1.1', 'Identifier');                    
                if ($node && $node->length > 0) {
                   $identifier = $node->item(0)->nodeValue;
                }
                
                $node = $process->getElementsByTagNameNS('http://www.opengis.net/ows/1.1', 'Title');
                if ($node && $node->length > 0) {
                    $title = $node->item(0)->nodeValue;
                }
                $results[] = array(
                    'identifier' => $identifier,
                    'title' => $title
                );
            }
        }
        return RestoLogUtil::success("WPS Processings list", array (
                'items' => $results
        ));
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
