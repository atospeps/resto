<?php
/**
 * 
 * @author Atos
 *
 */
class Curl {
    
    /**
     * 
     * @param unknown $url
     * @param unknown $data
     * @param unknown $options
     */
    public static function Get($url, $data, $options=array()){    
        $opts = array (
                'CURLOPT_RETURNTRANSFER' => 1,
                'CURLOPT_VERBOSE' => 0,
                'CURLOPT_TIMEOUT' => 60,
                'CURLOPT_RETURNTRANSFER' => 1,
                'CURLOPT_FOLLOWLOCATION' => 1,
                'CURLOPT_FAILONERROR' => 1
        );
        $opts = array_merge($opts, $options);
        $_url = $url . (substr($url, -1) == '?' ? '' : '?') . http_build_query($data);

        return self::exec($_url, $opts);
    }

    /**
     * 
     * @param unknown $url
     * @param unknown $data
     * @param unknown $options
     * @return unknown
     */
    public static function Post($url, $data, $options=array()){
        // Call the WPS Server
        $ch = curl_init($url);

        $opts = array (
                'CURLOPT_RETURNTRANSFER' => 1,
                'CURLOPT_VERBOSE' => 0,
                'CURLOPT_TIMEOUT' => 60,
                'CURLOPT_RETURNTRANSFER' => 1,
                'CURLOPT_FOLLOWLOCATION' => 1,
                'CURLOPT_FAILONERROR' => 1,
                'CURLOPT_POST' => 1,
                'CURLOPT_POSTFIELDS' => $data
        );
        $opts = array_merge($opts, $options);

        return self::exec($url, $curl_options);
    }

    /**
     * 
     * @param unknown $url
     * @param unknown $curl_options
     * @throws Exception
     * @return unknown
     */
    private function exec($url, $curl_options) {
        
        $ch = curl_init($url);
        /*
         * Sets request options.
        */
        foreach ($curl_options as $option => $value){
            curl_setopt($ch, $option, $value);
        }
    
        /*
         * Get the response
        */
        $response = curl_exec($ch);
    
        /*
         * Checks errors.
        */
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            /*
             * logs error.
            */
            error_log(__METHOD__ . ' ' . $error, 0);
            /*
             * Close cURL session
            */
            curl_close($ch);
            /*
             * Throw cURL exception
            */
            throw new Exception($error, 500);
        }
        curl_close($ch);
        return $response;
    }
}