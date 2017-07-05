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
        
        if (!isset($config) || !is_array($config))
        {
            throw new Exception('WPS server configuration is missing', 500);
        }

        // ? WPS server address url is setted
        if (empty($config['serverAddress'])) 
        {
            throw new Exception('WPS server configuration - ServerAddress is missing', 500);
        }

        // ? WPS outputs url is setted
        if (empty($config['outputsUrl'])) 
        {
            throw new Exception('WPS server configuration - outputsUrl is missing', 500);
        }
        
        // ? pywps conf is setted
        if (empty($config['conf']['serverAddress']) || empty($config['conf']['outputsUrl'])) 
        {
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
    public function getServerAddress() {
        return $this->serverAddress;
    }
        
    public function getCurlOptions() {
        return $this->curlOpts;
    }
    
    public function getOutputsUrl() {
        return $this->outputsUrl . (substr($this->outputsUrl, -1) == '/' ? '' : '/');
    }
    
    public function getResponseServerAddress() {
        return $this->wpsResponseServerAddress;
    }

    public function getResponseOutputsUrl() {
        return $this->wpsResponseOutputsUrl;
    }
    
    public function Perform($method, $data, $processes_enabled = array()) {
        switch ($method) 
        {
            case 'GET':
                return $this->Get($data, $processes_enabled);
            case 'POST';
                return $this->Post($data, $processes_enabled);
            default:
                RestoLogUtil::httpError(404);
        }
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
        switch (strtolower($request)) 
        {
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
        $response = null;
        /*
         * Perfom request
         */
        switch (strtolower($request))
        {
            /*
             * WPS GetCapabilities
             * wps?request=GetCapabilities&xxx
             */
            case self::GET_CAPABILITIES :
                $response = GetCapabilities::Post($this->serverAddress, $data, $processes_enabled, $this->curlOpts);
                break;
                /*
                 * WPS DescribeProcess
                 * wps?request=DescribeProcess&xxx
                 */
            case self::DESCRIBE_PROCESS :
                $response = DescribeProcess::Post($this->serverAddress, $data, $processes_enabled, $this->curlOpts);
                break;
                /*
                 * WPS Execute
                 * wps?request=Execute&xxx
                 */
            case self::EXECUTE :
                $response = Execute::Post($this->serverAddress, $data, $processes_enabled, $this->curlOpts);
                break;
                // ? Is WSDL, missing or invalid parameter 'request'
            default :
                $response = Curl::Post($this->serverAddress, $data, $this->curlOpts);
                break;
        }
        return new WPS_Response($response);
    }

    /**
     * 
     * @param unknown $resource
     */
    public function download($resource) {
        
    }
    
    /**
     * 
     * @param unknown $statusLocation
     * @return WPS_ExecuteResponse|boolean
     */
    public function getExecuteResponse($statusLocation) {
        try 
        {
            $url = $this->getOutputsUrl() . $statusLocation;
            $data = Curl::Get($url, array(), $this->curlOpts);
            $response = new WPS_Response($data);

            if ($response->isExecuteResponse()) 
            {
                $response = new WPS_ExecuteResponse($response->toXML());
                return $response;
            }
        } 
        catch (Exception $e) { }

        return false;
    }
    
    /**
     * 
     * @param unknown $data
     * @return string|NULL
     */
    private function checkRequestType($data) {

        $dom = new DOMDocument;        
        $dom->loadXML($data);

        /*
         * HTTP/POST GetCapabilities
         */ 
        $request = $dom->getElementsByTagNameNS('http://www.opengis.net/wps/1.0.0', 'GetCapabilities');
        if ($request && $request->length > 0)
        {
            return self::GET_CAPABILITIES;
        }
        
        /*
         * HTTP/POST DescribeProcess
         */
        $request = $dom->getElementsByTagNameNS('http://www.opengis.net/wps/1.0.0', 'DescribeProcess');
        if ($request && $request->length > 0)
        {
            return self::DESCRIBE_PROCESS;
        }
        
        /*
         * HTTP/POST Execute
         */
        $request = $dom->getElementsByTagNameNS('http://www.opengis.net/wps/1.0.0', 'Execute');
        if ($request && $request->length > 0)
        {
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