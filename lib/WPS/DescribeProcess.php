<?php 

/**
 * 
 * @author driss.elmaalem@atos.net
 *
 */
class DescribeProcess {

    public static function Get($url, $data, $processes_enabled, $options) {
        /*
         * Filter
         */
        if (isset($data['identifier'])) {
            // Is allowed to perform all processes ?
            $full_wps_rights = in_array('all', $processes_enabled); 

            // Getting processes list to perform (from query)
            $processes = explode(',', $data['identifier']);

            // ? Is not allowed to perform all processes 
            if ($full_wps_rights == false) {

                // ? Display ALL allowed processes
                $display_all_processes = false;

                foreach ($processes as $key => $identifier) {
                    // Not check identifiers after 'all' identifier
                    if ($identifier == 'all') {
                        $display_all_processes = true;
                        break;
                    }
                    // Is process not allowed ? Return ExceptionReport
                    if (!in_array($identifier, $processes_enabled)) {
                        $response = new ExceptionReport('InvalidParameterValue', $identifier);
                        return $response->toXML();
                    }
                }

                // Avoid 'InvalidParameterValue' error (invalid process from '$processes_enabled') 
                if ($display_all_processes == true) {
                    // Getting wps response
                    $response = Curl::Get($url, $data, $options);

                    $dom = new DOMDocument;
                    // pretty print options
                    $dom->preserveWhiteSpace = false;
                    $dom->formatOutput = true;
                    
                    $dom->loadXML($response);
                    
                    $processes = $dom->getElementsByTagName('ProcessDescription');
                    $processesToRemove = array();

                    if ($processes && $processes->length > 0) {
                        // Removing not allowed processes
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
                        // Updating WPS response
                        $response = $dom->saveXML();
                    }
                    return $response;
                }                
            }
        } 
        // ? Is missing 'identifier' parameter
        else {
            $response = new ExceptionReport('MissingParameterValue', 'identifier');
            return $response->toXML();
        }

        /*
         * Forward
         */
        return Curl::Get($url, $data, $options);
    }

    public static function Post($url, $data, $processes_enabled, $options){
        return Curl::Post($url, $data, $options);
    }
    
}