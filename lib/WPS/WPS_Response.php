<?php
/**
 *
 * WPS:WPS_Response
 * @author Driss El maalem
 *
 */
class WPS_Response {
    
    const SERVICE = 'WPS';
    
    const TAG_GET_CAPABILITIES_RESPONSE     = 'Capabilities';
    const TAG_DESCRIBE_PROCESS_RESPONSE     = 'ProcessDescriptions';
    const TAG_EXECUTE_RESPONSE              = 'ExecuteResponse';
    const TAG_EXCEPTION_REPORT_RESPONSE     = 'ExceptionReport';

    /**
     * WPS version
     * @var String
     */
    const VERSION = '1.0.0';
    
    private $request = null;

    // xml as string
    protected $xml;
    
    /**
     *
     * @param unknown $pXml
     */
    public function __construct($pXml) {
        $this->xml = $pXml;
    }
    
    /**
     */
    public function toXML() {
        return $this->xml;
    }
    
    /**
     * Returns true whether ExecuteResponse, otherwise false
     * @return boolean
     */
    public function isExecuteResponse(){

        if (!empty($this->xml)) {
            
            $dom = new DOMDocument;

            // load xml
            $dom->loadXML($this->xml);
            
            $execute_response = $dom->getElementsByTagName(self::TAG_EXECUTE_RESPONSE);
            
            if ($execute_response && $execute_response->length > 0) {
                return true;
            }
            return false;
        }
        return false;
    }
}