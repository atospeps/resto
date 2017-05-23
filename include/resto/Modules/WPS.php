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
        if ($this->context->method !== 'GET' && $this->context->method !== 'POST') {
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
                case 'GET' :
                    return $this->processGET();
                case 'POST' :
                    return $this->processPOST($data);
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
         * - GetCapabilities
         * - DescribeProcess
         * - Execute
         */
        if (!isset($this->segments[0])) {
            $this->context->outputFormat =  'xml';

            // Checks if WPS server url is configured
            if (empty($this->wpsServerUrl)){
                throw new Exception('WPS Configuration problem', 500);
            }

            $wps = WPSRequest::getInstance($this->wpsServerUrl, $this->curl_options);

            /* 
             * ###################################################
             * TODO : Getting WPS rights - manage processes enabled
             * ###################################################
             */
            $processes_enabled = array(/*'all',*/ 'echotiff', 'QL_S2');
            $response =  $wps->Get($this->context->query, $processes_enabled);
            return new WPSResponse($response); 
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
                    $row['outputs'] = json_decode($row['outputs'], true);
                    $results[] = $row;
                }
                // Updates status's jobs.
                $results = $this->updateStatusOfJobs($results);
                return $this->filterJobOutputs($results);
            }
        } else {
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
                $response = $this->callWPSServer($statusLocation, null, false);

                // Parses response in order to refresh status.            
                $wpsExecuteResponse = new ExecuteResponse($response->toXML());
                $status = $wpsExecuteResponse->getStatus();
                $percentCompleted = $wpsExecuteResponse->getPercentCompleted();
                $outputs = $wpsExecuteResponse->getOutputs();
                $statusMessage = $wpsExecuteResponse->getStatusMessage();
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
        
        $wps_host = parse_url($this->wpsServerUrl, PHP_URL_HOST);
        if (!empty($wps_host)){
           $response = str_replace('localhost', $wps_host, $response); 
        }
        
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
        $xml = new WPSResponse($this->updateWPSURLs($response));

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
                        'statusLocation' => $wpsExecuteResponse->getStatusLocation(),
                        'statusMessage' => $wpsExecuteResponse->getStatusMessage(),
                        'percentcompleted' => $wpsExecuteResponse->getPercentCompleted(),
                        'outputs' => $wpsExecuteResponse->getOutputs()
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
    private $statusMessage;
    private $statusTime;
    private $percentCompleted = 0;

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
        libxml_use_internal_errors(true);
        $sxe = new SimpleXMLElement($this->xml);
        libxml_clear_errors();
        
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
        
        $status = $wps_ExecuteResponse->xpath('.//wps:Status');
        if ($status && count($status) > 0){
            $this->parseStatus($status[0]);
        }
        
        $process = $wps_ExecuteResponse->xpath('.//wps:Process');
        if ($process && count($process) > 0){
            $this->parseProcess($process[0]);
        }

        $outputs = $wps_ExecuteResponse->xpath('.//wps:ProcessOutputs');
        if ($outputs && count($outputs) > 0){
            $this->parseOutputs($outputs[0]);
        }
    }

    /**
     * Parse WPS ProcessFailed.
     * @param SimpleXMLElement $wps_processFailed
     */
    private function parseProcessFailed(SimpleXMLElement $wps_processFailed){
        $report = $wps_processFailed->xpath('.//wps:ExceptionReport');
        if ($report && count($report)>0){
            $exception = $report[0]->xpath('.//ows:Exception');
            if ($exception && count($exception)>0){
                $exceptionText = $exception[0]->xpath('.//ows:ExceptionText');
                if ($exceptionText && count($exceptionText)>0){
                    $this->statusMessage = $exceptionText[0]->__toString();
                }
            }
        }
    }

    /**
     * Parses WPS process.
     * @param SimpleXMLElement $wps_process
     */
    private function parseProcess(SimpleXMLElement $wps_process){
        
        $identifier = $wps_process->xpath('.//ows:Identifier');
        if ($identifier && count($identifier)>0){
            $this->identifier = $identifier[0]->__toString();
        }
    }

    /**
     * Parses status.
     * @param SimpleXMLElement $wps_Status
     */
    private function parseStatus(SimpleXMLElement $wps_Status) {

        foreach (self::$statusEvents as $statusEvent) {
            $status = $wps_Status->xpath(".//wps:$statusEvent");
            if ($status && count($status) > 0){
                $this->status = $statusEvent;
                
                $attributes = $status[0]->attributes();
                $this->percentCompleted =  isset($attributes['percentCompleted']) ? intval($attributes['percentCompleted']->__toString(), 0) : 0;
                break;
            }
        }
        // Gets status message.
        if ($this->status === 'ProcessFailed'){
            $this->parseProcessFailed($status[0]);
        } else {
            $this->statusMessage = $status[0]->__toString();
        }

        if ($this->status === 'ProcessSucceeded'){
            $this->percentCompleted = 100;
        }
    }

    /**
     * Parses outputs.
     * @param SimpleXMLElement $wps_Outputs
     */
    private function parseOutputs(SimpleXMLElement $wps_Outputs){
        $this->processOutputs = array();
        
        $outputs = $wps_Outputs->xpath('.//wps:Output');
        if ($outputs && count($outputs)>0){
            foreach ($outputs as $key => $output){
                $this->processOutputs[] = $this->parseOutput($output);
            }
        }
    }

    /**
     * Parses Output.
     * @param SimpleXMLElement $wps_Output
     */
    private function parseOutput(SimpleXMLElement $wps_Output){
        $output = array();        

        $identifier = $wps_Output->xpath('.//ows:Identifier');        
        if ($identifier && count($identifier)>0){
            $output['identifier'] = $identifier[0]->__toString();
        }

        $title = $wps_Output->xpath('.//ows:Title');
        if ($title && count($title)>0){
            $output['title'] = $title[0]->__toString();
        }
        
        $data = $wps_Output->xpath('.//wps:Data');
        if ($data && count($data)>0){
            $literal = $data[0]->xpath('.//wps:LiteralData');
            if ($literal && count($literal)>0){
                $attributes = $literal[0]->attributes();
                $output['type'] = $attributes['dataType']->__toString();
                $output['value'] = $literal[0]->__toString();
            }
        }
        return $output;
    }

    /**
     * Returns WPS outputs.
     */
    public function getOutputs(){
        return $this->processOutputs;
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
     * Returns status message.
     */
    public function getStatusMessage(){
        return $this->statusMessage;
    }

    /**
     * Returns percent completed (ProcessSucceded)
     * @return number percent completed
     */
    public function getPercentCompleted(){
        return $this->percentCompleted;
    }

    /**
     * If ExecuteResponse, returns status if present, otherwise null.
     */
    public function getStatusLocation(){
        return $this->statusLocation;
    }
}

class ExecuteResponseException extends Exception { }
