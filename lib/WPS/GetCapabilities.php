<?php
class GetCapabilities {
    /**
     * 
     * @param string $url
     * @param array $data
     * @param array $processes_enabled
     * @param array $options
     */
    public static function Get($url, $data, $processes_enabled, $options) {

        $full_wps_rights = in_array('all', $processes_enabled);
        $response = Curl::Get($url, $data, $options);

        if ($full_wps_rights == false) {

            $dom = new DOMDocument;
            // pretty print options
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = true;

            $dom->loadXML($response);

            $processes = $dom->getElementsByTagNameNS('http://www.opengis.net/wps/1.0.0', 'Process');
            $processesToRemove = array();

            if ($processes && $processes->length > 0) {
                // on parcours les process de la reponse et on supprime les process non autorisés
                foreach ($processes as $process) {
                    $identifier = $process->getElementsByTagNameNS('http://www.opengis.net/ows/1.1', 'Identifier');                    
                    if ($identifier && $identifier->length > 0) {
                        if (!in_array($identifier->item(0)->nodeValue, $processes_enabled)) {
                            $processesToRemove[] = $process;
                        }
                    }
                }
                foreach($processesToRemove as $process) {
                    $process->parentNode->removeChild($process);
                }
            }
            $response = $dom->saveXML();
        }
        return $response;
    }

    /**
     * 
     * @param unknown $url
     * @param unknown $data
     * @param unknown $processes_enabled
     * @param unknown $options
     * @return unknown
     */
    public static function Get_2($url, $data, $processes_enabled, $options) {
    
        $full_wps_rights = in_array('all', $processes_enabled);
        $response = Curl::Get($url, $data, $options);
    
        if ($full_wps_rights == false) {
    
            libxml_use_internal_errors(true);
            $sxe = new SimpleXMLElement($response);
            libxml_clear_errors();
    
            $sxe->registerXPathNamespace('ows', 'http://www.opengis.net/ows/1.1');
            $sxe->registerXPathNamespace('wps', 'http://www.opengis.net/wps/1.0.0');
    
            $processes = $sxe->xpath('//wps:Process');
            $processesToRemove = array();
    
            if ($processes && count($processes) > 0){
                // on parcours les process de la reponse et on supprime les process non autorisés
                foreach ($processes as $process){
                    $identifier = $process->xpath('.//ows:Identifier');
                    if ($identifier && count($identifier) > 0){
                        if (!in_array($identifier[0]->__toString(), $processes_enabled)){
                            unset($process[0]);
                        }
                    }
                }
            }
            $response = $sxe->saveXML();
        }
    
        return $response;
    }
    
    /**
     * 
     * @param unknown $url
     * @param unknown $data
     * @param unknown $processes_enabled
     * @param unknown $options
     */
    public static function Post($url, $data, $processes_enabled, $options) {
        // TODO
        return Curl::Post($url, $data, $options);
    }
}