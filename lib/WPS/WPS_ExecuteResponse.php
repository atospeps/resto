<?php
/**
 *
 * WPS:ExecuteResponse
 * @author Driss El maalem
 *
 */
class WPS_ExecuteResponse extends WPS_Response {

    /*
     * Process identifier.
     */
    private $identifier;

    /*
     * Status location.
     */
    private $statusLocation;

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
    
    /**
     * 
     * @return multitype:string NULL
     */
    public function toArray(){
        return array(
                'identifier' => $this->identifier,
                'status' => $this->status,
                'statusLocation' => $this->statusLocation,
                'statusMessage' => $this->statusMessage,
                'percentcompleted' => $this->percentCompleted,
                'outputs' => $this->processOutputs
        );
    }
}