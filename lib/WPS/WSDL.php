<?php
class WSDL {
    
    /**
     *
     * TODO
     *
     * @param unknown $url
     * @param unknown $data
     * @param unknown $processes_enabled
     * @param unknown $options
     */
    public static function Get($url, $data, $processes_enabled, $options) {

        $data = array();
        $url = $url . (substr($url, -1) == '?' ? '' : '?') . WPSRequest::WSDL;
        
        
        /*
         * Filter
         */
        // Is allowed to perform all processes ?
//         $full_wps_rights = in_array('all', $processes_enabled);
        
//         // Getting processes list to perform (from query)
//         $processes = explode(',', $data['identifier']);
        
//         // ? Is not allowed to perform all processes
//         if ($full_wps_rights == false) {
            
//                 // Getting wps response
//                 $response = Curl::Get($url, $data, $options);
                
//                 $dom = new DOMDocument();
//                 // pretty print options
//                 $dom->preserveWhiteSpace = false;
//                 $dom->formatOutput = true;
                
//                 $dom->loadXML($response);
                
//                 $processes = $dom->getElementsByTagName('ProcessDescription');
//                 $processesToRemove = array ();
                
//                 if ($processes && $processes->length > 0) {
//                     // Removing not allowed processes
//                     foreach ($processes as $process) {
//                         $identifier = $process->getElementsByTagNameNS('http://www.opengis.net/ows/1.1', 'Identifier');
//                         if ($identifier && $identifier->length > 0) {
//                             if (!in_array($identifier->item(0)->nodeValue, $processes_enabled)) {
//                                 $processesToRemove[] = $process;
//                             }
//                         }
//                     }
//                     foreach ($processesToRemove as $process) {
//                         $process->parentNode->removeChild($process);
//                     }
//                     // Updating WPS response
//                     $response = $dom->saveXML();
//                 }
//                 return $response;
//         }
        
        /*
         * Forward
         */
        return Curl::Get($url, $data, $options);
    }
    
    /**
     *
     * @param unknown $url
     * @param unknown $data
     * @param unknown $processes_enabled
     * @param unknown $options
     */
    public static function Post($url, $data, $processes_enabled, $options) {
        return Curl::Post($url, $data, $options);
    }
}