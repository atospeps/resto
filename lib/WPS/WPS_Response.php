<?php
/**
 *
 * WPS:WPS_Response
 * @author Driss El maalem
 *
 */
class WPS_Response {
    
    const SERVICE = 'WPS'; 
    /**
     * WPS version
     * @var String
     */
    const VERSION = '1.0.0';

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
}