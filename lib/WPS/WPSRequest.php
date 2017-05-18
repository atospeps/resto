<?php

require 'Utils/Curl.php';
require 'GetCapabilities.php';
require 'DescribeProcess.php';
require 'Execute.php';

/**
 *
 * WPS:WPS_Response
 * @author Driss El maalem
 *
 */
class WPSRequest {
    
    /*
     * WPS Services constants
     */
    const GET_CAPABILITIES = 'getcapabilities';
    const DESCRIBE_PROCESS = 'describeprocess';
    const EXECUTE = 'execute';

    public $url = null;
    public $options = array();
    
    /*
     *
     */
    private static $_instance = null;
    
    /**
     */
    private function __construct($url, $options) {
        $this->url = $url;
        $this->options = $options;
    }

    public static function getInstance($url, $options) {
        
        if (is_null(self::$_instance)) {
            self::$_instance = new WPSRequest($url, $options);
        }
        return self::$_instance;
    }
    
    /**
     *
     * @param unknown $url
     * @param unknown $query
     * @param unknown $processes_enabled
     */
    public function Get($data, $processes_enabled = array()) {
        $request = isset($data['request']) ? $data['request'] : null;

        /*
         * Perfom request
         */
        switch (strtolower($request)) {
            case self::GET_CAPABILITIES :
                return GetCapabilities::Get($this->url, $data, $processes_enabled, $this->options);
            case self::DESCRIBE_PROCESS :
                return DescribeProcess::Get($this->url, $data, $processes_enabled, $this->options);
            case self::EXECUTE :
                return Execute::Get($this->url, $data, $processes_enabled, $this->options);
            default :
                return Curl::Get($this->url, $data, $this->options);
        }
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
                return GetCapabilities::Post($this->url, $data, $processes_enabled, $this->options);
            case self::DESCRIBE_PROCESS :
                return DescribeProcess::Post($this->url, $data, $processes_enabled, $this->options);
            case self::EXECUTE :
                return Execute::Post($this->url, $data, $processes_enabled, $this->options);
            default :
                return Curl::Post($this->url, $data, $this->options);
        }
        
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