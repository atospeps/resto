<?php

/**
 * 
 * @author driss.elmaalem@atos.net
 *
 */
class ExceptionReport {
    
    private $exceptionCode;
    private $locator;
    
    /**
     */
    public function __construct($exceptionCode, $locator=null) {
        $this->exceptionCode = $exceptionCode;
        $this->locator = $locator;
    }
    
    /**
     * 
     * @return string
     */
    public function toXML(){
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
            <ExceptionReport xmlns="http://www.opengis.net/ows/1.1" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.opengis.net/ows/1.1 ../owsExceptionReport.xsd" version="1.0.0" xml:lang="en">;
                <Exception exceptionCode="' . $this->exceptionCode . '" ' . (!empty($this->locator) ? 'locator="' . $this->locator . '"' : '') . '/>
            </ExceptionReport>';
        
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml);
        $xml = $dom->saveXML();
        return $xml;
    }
}