<?php

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
