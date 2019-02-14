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
 *    | HTTP/GET        wps/users/{userid}/jobs/download                | 
 *    | HTTP/GET        wps/users/{userid}/jobs/{jobid}/download        | 
 *    | HTTP/GET        wps/users/{userid}/results/{resultid}/download  | 
 *    | HTTP/GET        wps/users/{userid}/jobs/{jobid}/log             | Get the job result log_0
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
     * Minimum period (seconds) between processings updates. 
     * This option prevent user from abusing of manual refresh.
     * Default value: 10
     */ 
    private $minPeriodBetweenProcessingsRefresh = 10;

    /* 
     * ? "Remove" also deletes processings from database
     * Default value: false
     */
    private $doesRemoveAlsoDeletesProcessingsFromDatabase = false;
    
    /* 
     * Time life of processings (days)
     * Default value : 0 (0 => Infinite)
     */
    private $timeLifeOfProcessings = 0;

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
    
    private $curlOpts = array();

    /*
     * WPS module route.
     */
    private $route;

    /**
     * WPS Module Constructor
     *
     * @param RestoContext $context context
     * @param RestoUser $user user
     */
    public function __construct($context, $user) {
        parent::__construct($context, $user);
        // Set user
        $this->user = $user;
        
        // Set context
        $this->context = $context;
        
        // Database handler
        $this->dbh = $this->getDatabaseHandler();

        $this->initialize();
    }
    
    /**
     * Initializes module context.
     */
    private function initialize()
    {    
        $module = $this->context->modules[get_class($this)];
        
        if (empty($module['serverAddress']) || empty($module['outputsUrl']))
        {
            RestoLogUtil::httpError(500, 'WPS server configuration - problem');
        }
        $this->externalServerAddress = $module['serverAddress'];
        $this->externalOutputsUrl = $module['outputsUrl'];
        
        $wpsConf = isset($module['pywps']) ? $module['pywps'] : array() ;
        $this->curlOpts = isset($module['curlOpts']) ? $module['curlOpts'] : array() ;
        $this->wpsRequestManager = new WPS_RequestManager($wpsConf, $this->curlOpts);
        
        // wps response replacements
        $this->replacements[$this->wpsRequestManager->getResponseServerAddress()] = $this->externalServerAddress;
        $this->replacements[$this->wpsRequestManager->getResponseOutputsUrl()] = $this->externalOutputsUrl;
        
        // ? Minimum period between processing update (units: seconds)
        if (isset($module['users']['minPeriodBetweenProcessingsRefresh']) 
                && is_int($module['users']['minPeriodBetweenProcessingsRefresh']))
        {
            $this->minPeriodBetweenProcessingsRefresh = $module['users']['minPeriodBetweenProcessingsRefresh'];
        }

        // ? "Remove" also deletes processings from database
        if (isset($module['users']['timeLifeOfProcessings']) && is_int($module['users']['timeLifeOfProcessings']))
        {
            $this->timeLifeOfProcessings = $module['users']['timeLifeOfProcessings'];
        }

        // ? "Remove" also deletes processings from database
        if (isset($module['users']['doesRemoveAlsoDeletesProcessingsFromDatabase']) 
                && is_bool($module['users']['doesRemoveAlsoDeletesProcessingsFromDatabase']))
        {
            $this->doesRemoveAlsoDeletesProcessingsFromDatabase = $module['users']['doesRemoveAlsoDeletesProcessingsFromDatabase'];
        }

        // WPS module route
        $this->route = isset($module['route']) ? $module['route'] : '' ;
    }

    /**
     * Run module - this function should be called by Resto.php
     *
     * @param array $segments : route elements
     * @param array $data : POST or PUT parameters
     *       
     * @return string : result from run process in the $context->outputFormat
     */
    public function run($segments, $data = array())
    {
        // Allowed HTTP method
        if ($this->context->method !== HttpRequestMethod::GET 
                && $this->context->method !== HttpRequestMethod::POST
                && $this->context->method !== HttpRequestMethod::PUT
                && $this->context->method !== HttpRequestMethod::DELETE) 
        {
            RestoLogUtil::httpError(404);
        }

        // Only autenticated user.
        if ($this->user->profile['userid'] === -1) 
        {
            if (!empty($this->context->query['_tk']) 
                    && $this->context->method == HttpRequestMethod::GET 
                    && isset($segments[0]) && $segments[0] == 'outputs' 
                    && !isset($segments[2])) 
            {
                $this->authenticateToken($segments, $this->context->query['_tk']);
            }
            else 
            {
                RestoLogUtil::httpError(401);
            }
        }

        // Checks if user can execute WPS services
        if ($this->user->canExecuteWPS() === false) 
        {
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
                return $this->processPOST($segments, $data);
            /*
             * HTTP/PUT
             */
            case HttpRequestMethod::PUT:
                return $this->processPUT($data);
            /*
             * HTTP/DELETE
             */
            case HttpRequestMethod::DELETE:
                return $this->processDELETE($segments, $data);
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
                 * HTTP/GET wps/users/{userid}/results/{resultid}/download
                 * HTTP/GET wps/users/{userid}/jobs/{jobid}/log
                 */
                case 'users':
                    return $this->GET_users($this->segments);
                /*
                 * HTTP/GET wps/outputs
                 */
                case 'outputs':
                    return $this->GET_wps_outputs($this->segments);
                /*
                 * HTTP/GET wps/status
                 */
                case 'status':
                    return $this->GET_wps_status($this->segments);
                /*
                 * HTTP/GET wps/processings (admin only)
                 * HTTP/GET wps/processings/{identifier}/describe
                 */
                case 'processings':
                    return $this->GET_wps_processings($this->segments);
                /*
                 * HTTP/GET wps/jobs
                 */
                case 'jobs':
                    return $this->GET_jobs($this->segments);
                /*
                 * Unknown route
                 */
                default:
                    return RestoLogUtil::httpError(404);
            }
        }
    }

    /**
     * HTTP/GET wps
     * @param array $segments route elements
     * @return unknown
     */
    private function GET_wps($segments) {
        return $this->perform_wps($segments, HttpRequestMethod::GET, $this->context->query);        
    }
    
    /**
     * HTTP/POST wps
     * @param array $segments route elements
     * @param array $data request parameters
     * @return unknown
     */
    private function POST_wps($segments, $data) {
        return $this->perform_wps($segments, HttpRequestMethod::POST, $data);        
    }
    
    /**
     * Perform wps service.
     * @param array $segments route elements
     * @param string $method HTTP method
     * @param array $data request parameters
     * @return unknown
     */
    private function perform_wps($segments, $method, $data)
    {
        $this->context->outputFormat =  'xml';
        
        // Gets wps rights
        $processes_enabled = $this->getEnabledProcessings($this->user->profile['groupname']);
        $response  = $this->wpsRequestManager->Perform($method, $data, $processes_enabled);
        $response->replaceTerms($this->replacements);
        
        // saves job status into database
        if ($response->isExecuteResponse())
        {
            $executeResponse = new WPS_ExecuteResponse($response->toXML());
            if ($executeResponse->getIdentifier() === WPS_RequestManager::WPS_STATUS_SERVICE)
            {
                return $response;
            }
            
            $query = ($method === HttpRequestMethod::GET) 
                        ?  $this->context->query 
                        : (strlen ($data) > 2500 ? substr ($data, 0, 2500) . ' ... ' : $data);
            $data = array_merge(
                    $executeResponse->toArray(),
                    array(
                            'querytime' => date("Y-m-d H:i:s"),
                            'method'    => $method,
                            'title'     => isset($this->context->query['title']) ? $this->context->query['title'] : null,
                            'data'      => $query
                    ));
            
            // si requete synchrone verifier dans resultat si il y a un lien vers le rapport de statut
            $data['percentcompleted'] = (empty($data['statusLocation'])) ?  $data['percentcompleted'] : 0;
            $data['status'] = (empty($data['statusLocation'])) ? $data['status'] : 'ProcessAccepted';
            // Store job into database

            $this->storeJob($this->user->profile['userid'], $data);
        }
        return $response;
    }
    
    /**
     * Returns current VIZO status
     * 
     */
    private function GET_wps_status()
    {
        $query  = 'SELECT * FROM usermanagement.wps_status WHERE TRUE';
        $result = $this->context->dbDriver->query($query);
        $row    = $this->context->dbDriver->fetch_assoc($result);
        
        return RestoLogUtil::success("vizo status: " . $row['status'], array(
            'status' => $row['status'],
            'last_dispatch' => $row['last_dispatch']
        ));
    }
    
    /**
     * 
     * @param array $segments route elements
     * @return unknown
     */
    private function GET_wps_outputs($segments) {
    
        if (!isset($segments[1]) || isset($segments[3])){
            return RestoLogUtil::httpError(404);
        }

        // ? Is statusLocation
        $resource = $segments[1] . (isset($this->context->outputFormat) ? '.' . $this->context->outputFormat : '');
        $job = $this->getJobs(
                $this->user->profile['userid'], 
                null, 
                array('statuslocation=' . $this->context->dbDriver->quote($resource)));

        // ? statusLocation exists 
        if (count($job) > 0) 
        {
            $response = $this->wpsRequestManager->getExecuteResponse($job[0]['statuslocation']);
            if ($response instanceof WPS_ExecuteResponse){
                $response->replaceTerms($this->replacements);
                return $response;
            }
        }

        // ? Is processings file result
        $result = $this->getProcessingResults(
                $this->user->profile['userid'],
                null,
                null,
                array('value=' . $this->context->dbDriver->quote($resource)),
                true);
        
        if (count($result) > 0) 
        {
            return $this->wpsRequestManager->download($result[0]['value'], null, $result[0]['identifier']);
        }
        
        echo $this->wpsRequestManager->getOutput($resource);
        return null;
    }

    /**
     *
     * Process HTTP GET request on users
     * 
     *              HTTP/GET wps/users/{userid}/jobs
     *              HTTP/GET wps/users/{userid}/jobs/stats
     *              HTTP/GET wps/users/{userid}/jobs/{jobid}
     *              HTTP/GET wps/users/{userid}/jobs/{jobid}/logs
     *              HTTP/GET wps/users/{userid}/jobs/{jobid}/download
     *              
     *              HTTP/GET wps/users/{userid}/results
     *              HTTP/GET wps/users/{userid}/results/{resultid}/download
     *
     * 				HTTP/GET wps/users/{userid}/processings
     *
     *              HTTP/GET wps/processings
     *              HTTP/GET wps/processings/{identifier}/describe 
     *
     * @param array $segments
     */
    private function GET_users($segments)
    {
        if (!isset($segments[1])) 
        {
            return $this->GET_users_to_notify();
        }
        
        $userid = $segments[1];
        
        // ? Is valid user id pattern
        if (!ctype_digit($userid))
        {
            RestoLogUtil::httpError(400);
        }
        
        // ? User can route
        if ($this->checkUserAccess($userid) === false) 
        {
             RestoLogUtil::httpError(403);
        }
        
        if (isset($segments[2])) {
            // wps/users/{userid}/jobs
            if ($segments[2] === 'jobs') 
            {
                return $this->GET_users_jobs($userid, $segments);
            }
            // wps/users/{userid}/results
            if ($segments[2] === 'results') 
            {
                return $this->GET_users_results($userid, $segments);
            }
            // wps/users/{userid}/processings
            if ($segments[2] === 'processings') {
                return $this->GET_wps_processings($segments);
            }
        }
        
        return RestoLogUtil::httpError(404);
    }

    /**
     * 
     *              HTTP/GET wps/users/{userid}/jobs
     *              HTTP/GET wps/users/{userid}/jobs/stats
     *              HTTP/GET wps/users/{userid}/jobs/{jobid}
     *              HTTP/GET wps/users/{userid}/jobs/{jobid}/logs
     *              HTTP/GET wps/users/{userid}/jobs/{jobid}/download
     * 
     * @param unknown $segments
     * @return unknown|NULL
     */
    private function GET_users_jobs($userid, $segments){
        
        // wps/users/{userid}/jobs
        if (!isset($segments[3]))
        {
            $jobs = $this->GET_userWPSJobs($userid);
            return RestoLogUtil::success('WPS jobs for user ' . $userid, array ('data' => $jobs));
        }

        // wps/users/{userid}/jobs/stats
        if (!isset($segments[4]))
        {
            if ($segments[3] === 'stats') {
                $count = $this->getCompletedJobsStats($userid);
                return RestoLogUtil::success('WPS jobs stats for user ' . $userid, array ('data' => $count));
            }
        }
        else 
        {
            $jobid = $segments[3];
            $action = $segments[4];
            if (!ctype_digit($jobid)) {
                RestoLogUtil::httpError(400);
            }

            switch ($action) {
                // wps/users/{userid}/jobs/{jobid}/download
                case 'download':
                    // ? Is processings file result
                    $result = $this->getProcessingResults($userid, $jobid, null, null, true);
                    
                    if (count($result) > 0)
                    {
                        return $this->wpsRequestManager->download($result[0]['value'], null, $result[0]['identifier']);
                    }
                    break;
                // wps/users/{userid}/jobs/{jobid}/logs
                case 'logs':
                    $job = $this->getJobs($userid, $jobid, array(), false);
                    if (isset($job[0]['logs']))
                    {
                        $content = Curl::Get($job[0]['logs'], array(), $this->curlOpts);
                        if ($content)
                        {
                            return RestoLogUtil::success('WPS job  for user ' . $userid, array ('data' => $content));
                        }
                    }
                default:
                    break;
            }
        }
        RestoLogUtil::httpError(404);
    }

    /**
     *              HTTP/GET wps/users/{userid}/results
     *              HTTP/GET wps/users/{userid}/results/{resultid}/download
     *              
     * @param unknown $segments
     * @return unknown|NULL
     */
    private function GET_users_results($userid, $segments){
        
        // users/{userid}/results
        if (!isset($segments[3]))
        {
            $results = $this->getProcessingResults($userid);
            return RestoLogUtil::success('WPS jobs results for user ' . $userid, array ('data' => $results));
        }

        // wps/users/{userid}/results/{resultid}/download
        if (isset($segments[4]) && $segments[4] === 'download') {
            
            $resultid = $segments[3];
            if (!ctype_digit($resultid)) {
                RestoLogUtil::httpError(400);
            }
            // ? Is processings file result
            $result = $this->getProcessingResults($userid, null, $resultid, null, true);
            
            if (count($result) > 0)
            {
                return $this->wpsRequestManager->download($result[0]['value'], null, $result[0]['identifier']);
            }
        }
        RestoLogUtil::httpError(404);
    }
    
    
    
    /**
     * Process on HTTP method POST on /wps, /wps/execute and wps/clear
     * 
     *      TODO    HTTP/POST wps
     *      HTTP/POST wps/users/{userid}/results/download
     *      $segments    [ {0} , {1}    , {2}   , {3}    ]
     */
    private function processPOST($segments, $data)
    {
        
        if (!isset($this->segments[0])) {
            // HTTP/POST wps
            return $this->POST_wps($this->segments, join('', $data));
        }
        else
        {
            if ($this->segments[0] === 'users') 
            {
                /*
                 * HTTP/GET wps/users/{userid}/results/download
                 */
                return $this->POST_users($this->segments, $data);
            }
        }
        /*
         * Unknown route
         */
        RestoLogUtil::httpError(404);
    }
    
    /**
     * HTTP/POST wps/users/{userid}/results
     * 
     * 
     * TODO function DELETE_users($segments):
     *      ** HTTP/DELETE ** wps/users/{userid}/jobs/{jobid}
     *      ** HTTP/DELETE ** wps/users/{userid}/jobs/{jobid}/results
     *      ** HTTP/DELETE ** wps/users/{userid}/jobs/{jobid}/results/{resultid}
     */
    private function POST_users($segments, $data)
    {
        if (!isset($segments[1])) {
            RestoLogUtil::httpError(404);
        }
        $userid = $segments[1];
        if ($this->checkUserAccess($userid) === false) 
        {
             RestoLogUtil::httpError(403);
        }
        
        // wps/users/{userid}/results
        if (isset($segments[2]) && $segments[2] === 'results' && !isset($segments[3]) ) {
            return $this->placeOrder($this->user->profile['email'], $data);
        }
        RestoLogUtil::httpError(404);
    }
    
    /**
     * Process on HTTP method PUT
     */
    private function processPUT($data)
    {
            /*
             * HTTP/PUT wps/users/{userid}/jobs/acknowledges.
             */
            if ($this->segments[0] === 'users') {
                return $this->PUT_users($this->segments);
            }
            
            /*
             * HTTP/PUT wps/jobs
             */
            if ($this->segments[0] === 'jobs' && !isset($this->segments[1])) {
                return $this->PUT_jobs($data);
            }

            /*
             * HTTP/PUT wps/status/{status}.
             */
            if ($this->segments[0] === 'check'
                    && isset($this->segments[1])
                    && in_array($this->segments[1], array('SUCCESS', 'FAILURE'))) {
                return $this->PUT_wps_status($this->segments[1]);
            }        
        RestoLogUtil::httpError(404);
    }
    
    /**
     * 
     * @param unknown $data
     * @return NULL
     */
    private function PUT_jobs($data) {

        $now = time();
        $last_dispatch = date("Y-m-d\TH:i:s", $now);
        
        // Updating jobs...
        foreach($data as $job) {
            
            $update['jobid'] = $this->toWpsStatus($job['job_id']);
            $update['status'] = $this->toWpsStatus($job['job_status']);
            $update['percentcompleted'] = $job['percentCompleted'];
            $update['outputs'] = isset($job['results']) ? $job['results']: array();
            $update['nbresults'] = count($update['outputs']);
            $update['last_dispatch'] = $last_dispatch;
            $update['logs'] = isset($job['logs'][0]) ? $job['logs'][0] : null;
            $update['statusTime'] = isset($job['finishedTime']) ? $job['finishedTime'] : null;
            
            $this->context->dbDriver->update(RestoDatabaseDriver::PROCESSING_JOBS_ITEM, array('data' => $update));            
        }        
    }
    
    /**
     * 
     * @param array $segments
     * @return mixed
     */
    private function GET_jobs($segments) {
        
        /*
         * HTTP/GET wps/jobs/running
         */
        if (isset($segments[1]) 
            && $segments[1] === 'running' 
            && !isset($segments[2])) {
            return $this->GET_jobs_running();
        }
        RestoLogUtil::httpError(404);                
    }
    
    /**
     * Returns running jobs id.
     * @param array $filters filters array
     * @return array running jobs id
     */
    private function GET_jobs_running($filters=array()) {
        
        if (!$this->user->isAdmin())
        {
            RestoLogUtil::httpError(403);
        }

        // Processings life time
        if ($this->timeLifeOfProcessings > 0)
        {
            $filters[] = 'querytime > now() - (' . $this->timeLifeOfProcessings . ' || \' day\')::interval';
        }
        
        // Only asynchronous and running jobs
        $filters[] = 'statuslocation IS NOT NULL';
//         $filters[] = "status <> 'ProcessSucceeded'";
        $filters[] = "status <> 'ProcessFailed'";
        
        return $this->context->dbDriver->get( RestoDatabaseDriver::PROCESSING_RUNNING_JOBS_ID, array( 'filters' => $filters) );        
    }
    
    
    /**
     * Returns users list to notify
     * @return array users list
     */
    private function GET_users_to_notify() {
        if (!$this->user->isAdmin())
        {
            RestoLogUtil::httpError(403);
        }
        return $this->context->dbDriver->get( RestoDatabaseDriver::PROCESSING_USERS_TO_NOTIFY); 
    }
    
    /**
     * Store WPS status
     * 
     * @param string $status - SUCCESS | FAILURE
     */
    private function PUT_wps_status($status)
    {
        $query = "UPDATE usermanagement.wps_status"
               . "SET status = '" . $status . "', last_dispatch = NOW()"
               . "WHERE TRUE";
        
        $this->context->dbDriver->query($query);
            
        return RestoLogUtil::success('vizo status updated', array(
            'status' => $status
        ));
    }
    
    /**
     * 
     * @param unknown $data
     */
    private function processDELETE($segments, $data)
    {
        /*
         * HTTP/GET wps/users/{userid}/results/download
         */
        if ($segments[0] === 'users') {
            if (!isset($segments[1])) {
                RestoLogUtil::httpError(404);
            }
            
            $userid = $segments[1];
            if (!ctype_digit($userid)){
                RestoLogUtil::httpError(400);
            }
            if ($this->checkUserAccess($userid) === false)
            {
                RestoLogUtil::httpError(403);
            }
            
            if (isset($segments[2])
                    && $segments[2] === 'jobs'
                    && isset($segments[3]) 
                    && !isset($segments[4])) {
                // jobs
                $jobid = $segments[3];
                if (!ctype_digit($jobid))
                {
                    RestoLogUtil::httpError(400);
                }
                $this->removeJob($userid, $jobid);
                $jobs = $this->GET_userWPSJobs($userid);
                return RestoLogUtil::success("WPS jobs for user {$userid}", array ('data' => $jobs));
            }
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
        RestoLogUtil::httpError(404);
    }
    
    /**
     * Set user's jobs acknowledges to TRUE
     */
    private function setJobsAcknowledges($userid)
    {
        if ($this->checkUserAccess($userid) === false) 
        {
             RestoLogUtil::httpError(403);
        }
        
        $query = "UPDATE usermanagement.jobs "
               . "SET acknowledge = TRUE "
               . "WHERE (status = 'ProcessSucceeded' OR status = 'ProcessFailed') "
               . "AND userid = '" . pg_escape_string($userid) . "' ";
        
        pg_query($this->dbh, $query);
    }
    
    /**
     * Returns the completed jobs (succeeded + failed)
     * 
     * @param {string} userid
     * @return {int} count
     */
    private function getCompletedJobsStats($userid)
    {        
        $filters = array();

        // Processings life time
        if ($this->timeLifeOfProcessings > 0)
        {
            $filters[] = 'querytime > now() - (' . $this->timeLifeOfProcessings . ' || \' day\')::interval';
        }

        return $this->context->dbDriver->get(
                RestoDatabaseDriver::PROCESSING_JOBS_STATS,
                array(
                        'userid' => $userid,
                        'filters' => $filters
                ));
    }

    /**
     * 
     * @param unknown $userid
     * @param unknown $jobid
     * @param unknown $resultid
     * @param array $filters
     * @param string $rootPath
     * @return array|unknown
     */
    private function getProcessingResults($userid, $jobid = null, $resultid =null, $filters= array(), $realValue = false) {

        $items = array();

        // ? User id not setted
        if (!isset($userid)) 
        {
            return $items;
        }
        $filters[] = 'usermanagement.wps_results.userid=' . $this->context->dbDriver->quote($userid);

        // ? Job id is setted
        if (isset($jobid)) 
        {
            $filters[] = 'usermanagement.wps_results.jobid=' . $this->context->dbDriver->quote($jobid);
        }
        
        // ? Result id is setted
        if (isset($resultid))
        {
            $filters[] = 'usermanagement.wps_results.uid=' . $this->context->dbDriver->quote($resultid);
        }
        
        // Processings life time
        if ($this->timeLifeOfProcessings > 0) 
        {
            $filters[] = 'usermanagement.jobs.querytime > now() - (' . $this->timeLifeOfProcessings . ' || \' day\')::interval';
        }

        $oFilter = implode(' AND ', $filters);

        $value = $realValue ? 'value' : $this->context->dbDriver->quote($this->externalServerAddress . '/users/' . $userid . '/results/') . ' || usermanagement.wps_results.uid || ' . $this->context->dbDriver->quote('/download');
        
        // Query
        $select = 'SELECT usermanagement.wps_results.uid as uid, usermanagement.wps_results.jobid as jobid, usermanagement.jobs.title, usermanagement.jobs.querytime as processingtime, usermanagement.jobs.identifier as processing, usermanagement.jobs.statusTime as datetime, usermanagement.wps_results.identifier, type,' 
                . $value . ' as value ';
        $from  = 'FROM usermanagement.wps_results INNER JOIN usermanagement.jobs ON usermanagement.jobs.gid = usermanagement.wps_results.jobid';
        $where = 'WHERE ' . $oFilter . ' AND visible=true ORDER BY usermanagement.jobs.statusTime DESC';

        $query =  $select . ' ' . $from . ' ' . $where;

        return $this->context->dbDriver->fetch($this->context->dbDriver->query($query));
    }

    /**
     *
     * @return multitype:multitype:
     */
    private function GET_userWPSJobs($userid) {
        
        return $this->getJobs(
                $userid, 
                null, 
                array());
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
        
        if ($groupname === 'admin') {
            return array('all');
        }
        // get group id
        $group = $this->context->dbDriver->get(
            RestoDatabaseDriver::GROUP, 
            array('gidOrGroupName' => $groupname)
        );
        
        // get WPS rights
        return $this->context->dbDriver->get(
            RestoDatabaseDriver::WPS_GROUP_RIGHTS, 
            array('groupid' => $group['id'])
        );
        
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
     * 
     * @param integer $userid
     * @param integer $jobid
     * @param array $filters
     * @param bool $updateJobsStatus
     * @return multitype:|unknown
     */
    private function getJobs($userid, $jobid=null, $filters=array(), $updateJobsStatus = true)
    {
        $items = array();

        // ? User id not setted
        if (!isset($userid)) 
        {
            return $items;
        }

        // ? Job id is setted
        if (isset($jobid)) 
        {
            $filters[] = 'gid=' . $this->context->dbDriver->quote($jobid);
        }
        
        // Processings life time
        if ($this->timeLifeOfProcessings > 0) 
        {
            $filters[] = 'querytime > now() - (' . $this->timeLifeOfProcessings . ' || \' day\')::interval';
        }
        
        return $this->context->dbDriver->get(
            RestoDatabaseDriver::PROCESSING_JOBS_ITEMS, 
            array( 'userid' => $userid, 'filters' => $filters)
            );
        
        // Updates status's jobs.
//         if ($updateJobsStatus) {
//             $results = $this->updateStatusOfJobs($results);
//         }
        
//         return $results;        
    }
    
    /**
     * We remove a job
     *
     * @throws Exception
     */
    private function removeJob($userid, $jobid) {

        $removeType = $this->doesRemoveAlsoDeletesProcessingsFromDatabase 
                ? RestoDatabaseDriver::PROCESSING_JOBS_DATA 
                : RestoDatabaseDriver::PROCESSING_JOBS_ITEM;

        $options = array(
                'userid' => $userid,
                'jobid' => $jobid
                );
        return $this->context->dbDriver->remove(
                $removeType,
                $options);
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
        
        $now = time();
        $jobs_to_update = array();
        $curl_arr = array();
        $master = curl_multi_init();
        $count = 0;
        
        foreach ($jobs as &$job) {
            $job['gid'] = (int)$job['gid'];
            $job['logs'] = !empty($job['logs']);
            
            if (!empty($job['product'])) 
            {
                $product = $job['product'];
                $job['input'] = array(
                        'collection' => RestoUtil::collection($product),
                        'id' => RestoUtil::UUIDv5(RestoUtil::collection($product) . ':' . $product)
                );
                unset($job['product']);
            }
            
            if ($job['status'] !== 'ProcessSucceeded' && $job['status'] !== 'ProcessFailed')
            {
                if ($now < (strtotime($job['last_dispatch']) + $this->minPeriodBetweenProcessingsRefresh))
                {
                    continue;
                }

                preg_match('/(pywps|report)-(.*).(xml|json)/', $job['statuslocation'], $matches);
                if (isset($matches[2]))
                {
                    $curl_arr[$count] = Curl::Init(
                            $this->wpsRequestManager->getStatusUrl($matches[2]),
                             $this->wpsRequestManager->getCurlOptions());
                    curl_multi_add_handle($master, $curl_arr[$count]);
                    
                    $jobs_to_update[$count] = &$job;
                    $count++;
                }
                continue;

            }
        }

        // ? jobs to update
        if ($count > 0){
            do
            {
                set_time_limit(0);
                curl_multi_select($master);
                curl_multi_exec($master, $running);
            }
            while ($running > 0);
            
            // updates jobs
            for($i = 0; $i < $count; $i++)
            {
                $res = $curl_arr[$i];
                $data = curl_multi_getcontent  ( $res  );
                curl_multi_remove_handle($master, $res);
                $update = &$jobs_to_update[$i];
                
                if (($statusReport = $this->wpsRequestManager->parseStatusReport($data)) !== false
                        && ($update['status'] != $statusReport['job_status'] || $update['percentcompleted'] != $statusReport['percentCompleted']) )
                {
                    $update['status'] = $this->toWpsStatus($statusReport['job_status']);                    
                    $update['percentcompleted'] = $statusReport['percentCompleted'];
                    $update['outputs'] = $statusReport['results'];
                    $update['nbresults'] = count($update['outputs']);
                    $update['last_dispatch'] = date("Y-m-d\TH:i:s", $now);
                    $update['logs'] = isset($statusReport['logs'][0]) ? $statusReport['logs'][0] : null;
                    $update['statusTime'] = isset($statusReport['finishedTime']) ? $statusReport['finishedTime'] : null;

                    if ($this->updateJob($update['userid'], $update) === false){
                        continue;
                    }
                }
                $update['logs'] = !empty($update['logs']);
            }
        }
        curl_multi_close($master);
        
//         error_log("Memory usage ; " . print_r(memory_get_usage(), true));
        return $jobs;
    }
    
    /**
     * 
     * @param unknown $proactiveStatus
     * @return string|unknown
     */
    private function toWpsStatus($proactiveStatus) {
        $wpsStatus = $proactiveStatus;
        switch ($proactiveStatus) 
        {
            case ProactiveStatus::PENDING:
                $wpsStatus = 'ProcessAccepted';
                break;
            case ProactiveStatus::RUNNING:
            case ProactiveStatus::STALLED:
                $wpsStatus = 'ProcessStarted';
                break;
            case ProactiveStatus::FINISHED:
                $wpsStatus = 'ProcessSucceeded';
                break;
            case ProactiveStatus::PAUSED:
                $wpsStatus = 'ProcessPaused';
                break;
            case ProactiveStatus::CANCELED:            
            case ProactiveStatus::KILLED:
            case ProactiveStatus::FAILED:
            case ProactiveStatus::IN_ERROR:
                $wpsStatus = 'ProcessFailed';
                break;
            default:
                break;
        }
        return $wpsStatus;
    }
    
    /**
     * Get WPS processings
     * 
     */
    private function GET_wps_processings($segments)
    {
        if (isset($segments) && isset($segments[1]) && isset($segments[2]) && $segments[2] === 'describe') {
            // processings/{identifier}/describe
            $description = $this->getProcessingDescription($segments[1]);
            return RestoLogUtil::success('WPS processing description for identifier ' . $segments[1], array ('data' => $description));
        } else {
            // processings
            return $this->getProcessingsList();
        }
        
        return RestoLogUtil::httpError(404);
    }
    
    /**
     * 
     * @param unknown $data
     * @return NULL
     */
    private function placeOrder($email, $data) 
    {

        // ? Is Bad Request
        if (empty($data) || !is_array($data)) 
        {
            RestoLogUtil::httpError(400);
        }
        
        parse_str($data[0], $data);
        if (empty($data['items']))
        {
            RestoLogUtil::httpError(400);
        }
        
        // Parse query data
        $data = json_decode($data['items']);
        
        $data = array_unique($data);
        if (count($data) == 0) 
        {
            RestoLogUtil::httpError(400);
        }
        $meta4 = new RestoMetalink($this->context);
        
        for ($i = count($data); $i--;) 
        {
            $resultid = $data[$i];
            // ? Is numeric
            if (!ctype_digit("$resultid")) 
            {
                RestoLogUtil::httpError(400);
            }
            // ? User is allowed to download this result
            $result = $this->getProcessingResults(
                    $this->user->profile['userid'],
                    null,
                    $resultid);
        
            if (count($result) > 0) 
            {
                $item['id'] = $result[0]['identifier'];
                $item['properties']['services']['download']['url'] = $result[0]['value'];

                // Add link to the file
                $meta4->addLink($item, $email);
            }    
        }
        
        $this->context->outputFormat = 'meta4';
        header('Content-Type: ' . RestoUtil::$contentTypes['meta4']);
        header('Content-Disposition: attachment; filename="download.meta4"');
        echo $meta4->toString();
        return null;
        
    }
    
    /**
     * Get processing description
     */
    function getProcessingDescription($identifier)
    {
        if ($this->user->isAdmin()) 
        {
            $wpsRights = array('all');
        } 
        else 
        {
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
        
        $describeProcessResponse = new WPS_DescribeProcessResponse($response->toXML());
        $processes = $describeProcessResponse->getProcesses();
        if (count($processes) > 0)
        {
            $process = $processes[0];
            
            
            return $process->toArray();
        }
        RestoLogUtil::httpError(400);        
    }
    
    /**
     * Get WPS processings
     */
    function getProcessingsList()
    {
        // get WPS rights
        if ($this->user->isAdmin()) {
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
        return RestoLogUtil::success("WPS Processings list", array ('items' => $results));
    }

    /**
     * 
     * @param unknown $token
     */
    function authenticateToken($segments, $token){

        $email = $this->context->dbDriver->check(
                RestoDatabaseDriver::SHARED_LINK,
                array (
                        'resourceUrl' => $this->externalOutputsUrl . $segments[1] . '.' . $this->context->outputFormat,
                        'token' => $token
                ));
        
        if (!$email) {
            RestoLogUtil::httpError(403);
        }
        
        if (empty($this->user->profile['email']) || $this->user->profile['email'] !== $email)
        {
            $this->user = new RestoUser(
                    $this->context->dbDriver->get(RestoDatabaseDriver::USER_PROFILE, array ('email' => $email)),
                    $this->context);
        }
    }
    
    /**
     * 
     * @param unknown $userid
     * @return boolean
     */
    function checkUserAccess($userid) {

        return $this->user->profile['userid'] === $userid || $this->user->isAdmin();
    }
   
    /**
     * 
     * @param unknown $replacements
     * @return unknown
     */
    public function replaceTerms($in){
        $res = $in;
        if (isset($replacements) && is_array($replacements)){
            foreach ($replacements as $search => $replace){
                str_replace($search, $replace, $res);
            }            
        }
        return $res;
        
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

abstract class ProactiveStatus {
    const PENDING   = 'PENDING';
    const RUNNING   = 'RUNNING';
    const STALLED   = 'STALLED';
    const FINISHED  = 'FINISHED';
    const PAUSED    = 'PAUSED';
    const CANCELED  = 'CANCELED';            
    const KILLED    = 'KILLED';
    const FAILED    = 'FAILED';
    const IN_ERROR  = 'IN_ERROR';
}
