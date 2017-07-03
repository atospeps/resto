<?php

class Execute {

    /**
     * 
     * @param unknown $url
     * @param unknown $data
     * @param unknown $processes_enabled
     * @param unknown $options
     */
    public static function Get($url, $data, $processes_enabled, $options){
        /*
         * Filter
         */
        if (isset($data['identifier'])) 
        {
            $identifiers = array();
            $identifier = $data['identifier'];

            // Is allowed to perform all processes ?
            $full_wps_rights = in_array('all', $processes_enabled);

            // ? Is not allowed to perform all processes
            if ($full_wps_rights == false)
            {
                
                // Is process not allowed ? Return ExceptionReport
                if (!in_array($identifier, $processes_enabled)) 
                {
                    $response = new ExceptionReport('InvalidParameterValue', $identifier);
                    return $response->toXML();
                }
            }
        }
        // ? Is missing 'identifier' parameter
        else 
        {
            $response = new ExceptionReport('MissingParameterValue', 'identifier');
            return $response->toXML();
        }
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
    public static function Post($url, $data, $processes_enabled, $options){
        
        $dom = new DOMDocument;
        $dom->loadXML($data);
        
        // Getting process to perform
        $identifier = null;
        
        $owsIdentifiers = $dom->getElementsByTagNameNS('http://www.opengis.net/ows/1.1', 'Identifier');
        if ($owsIdentifiers && $owsIdentifiers->length > 0)
        {
            $identifier = $owsIdentifiers->item(0)->nodeValue;
        }

        /*
         * Filter
         */
        if (!empty($identifier)) 
        {
            $identifiers = array();

            // Is allowed to perform all processes ?
            $full_wps_rights = in_array('all', $processes_enabled);

            // ? Is not allowed to perform all processes
            if ($full_wps_rights == false)
            {
                
                // Is process not allowed ? Return ExceptionReport
                if (!in_array($identifier, $processes_enabled)) 
                {
                    $response = new ExceptionReport('InvalidParameterValue', $identifier);
                    return $response->toXML();
                }
            }
        }
        // ? Is missing 'identifier' parameter
        else 
        {
            $response = new ExceptionReport('MissingParameterValue', 'identifier');
            return $response->toXML();
        }
        /*
         * Forward
         */
        return Curl::Post($url, $data, $options);
    
    }
}