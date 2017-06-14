<?php

require 'Utils/Curl.php';
require 'GetCapabilities.php';
require 'DescribeProcess.php';
require 'Execute.php';
require 'ExceptionReport.php';

/**
 *
 * WPS:WPS_Response
 * @author Driss El maalem
 *
 */
class WPS_RequestManager {
    
    /*
     * WPS Requests constant
     */
    const GET_CAPABILITIES = 'getcapabilities';
    const DESCRIBE_PROCESS = 'describeprocess';
    const EXECUTE = 'execute';
    const WSDL = 'wsdl';

    private $serverAddress = null;
    private $outputsUrl = null;
    private $curlOpts = array();
    /*
     * 
     */
    private $wpsResponseServerAddress = null;
    private $wpsResponseOutputsUrl = null;
    
    /*
     *
     */
    private static $_instance = null;
    
    /**
     */
    public function __construct($config, $curlOpts) {
        
        if (!isset($config) || !is_array($config)){
            throw new Exception('WPS server configuration is missing', 500);
        }

        // ? WPS server address url is setted
        if (empty($config['serverAddress'])) {
            throw new Exception('WPS server configuration - ServerAddress is missing', 500);
        }

        // ? WPS outputs url is setted
        if (empty($config['outputsUrl'])) {
            throw new Exception('WPS server configuration - outputsUrl is missing', 500);
        }
        
        // ? pywps conf is setted
        if (empty($config['conf']['serverAddress']) || empty($config['conf']['outputsUrl'])) {
            throw new Exception('WPS server configuration - pywps.conf : missing parameter', 500);
        }

        $this->serverAddress = $config['serverAddress'];
        $this->outputsUrl = $config['outputsUrl'];
        $this->wpsResponseServerAddress = $config['conf']['serverAddress'];
        $this->wpsResponseOutputsUrl = $config['conf']['outputsUrl'];
        $this->curlOpts = $curlOpts;
    }
    
    /**
     * Getters
     */
    public function getServerAddress(){
        return $this->serverAddress;
    }
        
    public function getCurlOptions(){
        return $this->curlOpts;
    }
    
    public function getOutputsUrl() {
        return $this->outputsUrl . (substr($this->outputsUrl, -1) == '/' ? '' : '/');
    }
    
    public function getResponseServerAddress(){
        return $this->wpsResponseServerAddress;
    }

    public function getResponseOutputsUrl(){
        return $this->wpsResponseOutputsUrl;
    }
    
    /**
     *
     * @param unknown $url
     * @param unknown $query
     * @param unknown $processes_enabled
     */
    public function Get($data, $processes_enabled = array()) {

        $request = isset($data['request']) ? $data['request'] : null;
        $response = null;
        /*
         * Perfom request
         */
        switch (strtolower($request)) {
            /*
             * WPS GetCapabilities
             * wps?request=GetCapabilities&xxx
             */ 
            case self::GET_CAPABILITIES :
                $response = GetCapabilities::Get($this->serverAddress, $data, $processes_enabled, $this->curlOpts);
                break;
            /*
             * WPS DescribeProcess
             * wps?request=DescribeProcess&xxx
             */
            case self::DESCRIBE_PROCESS :
                $response = DescribeProcess::Get($this->serverAddress, $data, $processes_enabled, $this->curlOpts);
                break;
            /*
             * WPS Execute
             * wps?request=Execute&xxx
             */
            case self::EXECUTE :
                $response = Execute::Get($this->serverAddress, $data, $processes_enabled, $this->curlOpts);
                break;
            // ? Is WSDL, missing or invalid parameter 'request'
            default :
                if ((count($data) > 0) && self::WSDL == strtolower(key($data))) {
                    $response = WSDL::Get($this->serverAddress, $data, $processes_enabled, $this->curlOpts);
                    break;
                }
                $response = Curl::Get($this->serverAddress, $data, $this->curlOpts);
                break;
        }
        return new WPS_Response($response);
    }
    
    /**
     *
     * @param unknown $data
     * @param unknown $files
     */
    public function Post($data, $processes_enabled = array()) {
        
        $request = $this->checkRequestType($data);
        
        /*
         * Perfom request
         */
        switch (strtolower($request)) {
            case self::GET_CAPABILITIES :
                return GetCapabilities::Post($this->serverAddress, $data, $processes_enabled, $this->curlOpts);
            case self::DESCRIBE_PROCESS :
                return DescribeProcess::Post($this->serverAddress, $data, $processes_enabled, $this->curlOpts);
            case self::EXECUTE :
                return Execute::Post($this->serverAddress, $data, $processes_enabled, $this->curlOpts);
            default :
                return Curl::Post($this->serverAddress, $data, $this->curlOpts);
        }
    }

    public function download($resource) {
        
    }
    
    /**
     * 
     * @param unknown $statusLocation
     * @return WPS_ExecuteResponse|boolean
     */
    public function getExecuteResponse($statusLocation) {
        try {
            $url = $this->getOutputsUrl() . $statusLocation;
            $response = new WPS_Response(Curl::Get($url, array(), $this->curlOpts));
            
            if ($response->isExecuteResponse()) {
                $response = new WPS_ExecuteResponse($response->toXML());
                return $response;
            }
        } catch (Exception $e) {}

        return false;
    }

    // private function perform($url, $data, $options) {
    
    // //return $response;
    // $xml = new WPSResponse($this->updateWPSURLs($response));
    
    // /*
    // * Saves user's job into database (Only valid WPS processes).
    // * Parses responses in order to check WPS processing service=WPS&request=execute.
    // */
    // if ($saveExecuteResponse == true){
    // try {
    // $wpsExecuteResponse = new ExecuteResponse($response);
    // $data = array(
    // 'query_time' => date("Y-m-d H:i:s"),
    // 'identifier' => $wpsExecuteResponse->getIdentifier(),
    // 'status' => $wpsExecuteResponse->getStatus(),
    // 'statusLocation' => $wpsExecuteResponse->getStatusLocation(),
    // 'statusMessage' => $wpsExecuteResponse->getStatusMessage(),
    // 'percentcompleted' => $wpsExecuteResponse->getPercentCompleted(),
    // 'outputs' => $wpsExecuteResponse->getOutputs()
    // );
    // $this->createJob($data);
    // } catch (ExecuteResponseException $e) {
    // }
    // }
    
    // /*
    // * Returns WPS response.
    // */
    // return $xml;
    // }
    
    private function checkRequestType($data){
        libxml_use_internal_errors(true);
        $sxe = new SimpleXMLElement($data);
        libxml_clear_errors();

        $sxe->registerXPathNamespace('wps', 'http://www.opengis.net/wps/');
        $sxe->registerXPathNamespace('soap', 'http://www.w3.org/2003/05/soap-envelope');

        $result = $sxe->xpath('//wps:GetCapabilities');
        if (!$result && count($result) == 0) {
            return self::GET_CAPABILITIES;
        }
        $result = $sxe->xpath('/GetCapabilities');
        if (!$result && count($result) == 0) {
            return self::GET_CAPABILITIES;
        }
        $result = $sxe->xpath('//wps:DescribeProcess');
        if (!$result && count($result) == 0) {
            return self::DESCRIBE_PROCESS;
        }
        $result = $sxe->xpath('/DescribeProcess');
        if (!$result && count($result) == 0) {
            return self::DESCRIBE_PROCESS;
        }
        /*
         * TODO
         */ 
        $result = $sxe->xpath('//wps:Execute');
        if (!$result && count($result) == 0) {
            return self::EXECUTE;
        }
        // <soap:Body><Execute_PROCESSNAME ...></Execute_PROCESSNAME>
        $result = $sxe->xpath('/soap:Body');
        if (!$result && count($result) == 0) {
            return self::EXECUTE;
        }
        return null;
    }
    
    /**
     *
     * @param unknown $query
     * @param unknown $data
     * @param unknown $files
     */
    public function request($query, $data, $files) {
    }
    public function parseRequest() {
    }
    private function performRequest() {
    }
}