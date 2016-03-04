<?php
/**
 * RESTo WPS product processing for S2
 */
class s2Mosaic {
    
    /*
     * Configuration status
     */
    private $validConfiguration;

    /*
     * Url to the server
     */
    private $wps_url; 
    
    /*
     * Database handler
     */
    private $dbh;
    
    /**
     * Constructor
     */
    public function __construct($modules, $dbh) {
        // We validate the config is correctly set
        if (isset($modules['s2Mosaic']) && 
                isset($modules['s2Mosaic']['wps_url'])) {
            // All the elements needed are set in th configuration
            $this->validConfiguration = TRUE;
            // We set the values 
            $this->wps_url = $modules['s2Mosaic']['wps_url'];
            // Database handler
            $this->dbh = $dbh;
        }else{
            $this->validConfiguration = FALSE;
        }

    } 
   
    /**
     * Check if the configuration was set correctly
     */
    public function isValid() {
        return $this->validConfiguration;       
    }
    
    /**
     * Execute the processing on the server
     */
    public function execute($productIdentifier) {
        
        $url = str_replace("%TITLE%", $productIdentifier, $this->wps_url);
        
        // Call the WPS Server
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->wps_url);
        curl_setopt($ch, CURLOPT_FAILONERROR, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $response = curl_exec($ch);
        curl_close($ch);

        if ($response !== FALSE) {
            
            $wpsExecuteResponse = new ExecuteResponse($response);
            
            $status = $wpsExecuteResponse->getStatus();
            $statusLocation = $this->setCorrectDomain($wpsExecuteResponse->getStatusLocation());
            
            $process = pg_query($this->dbh, 'INSERT INTO resto.s2mosaic VALUES (\'' . $productIdentifier . '\', \'' . date("Y-m-d h:i:s", time()) . '\', \'' . $status . '\', \'' . $statusLocation . '\')');
        }
    }
    
    /**
     * We set the correct domain for the Status Location
     * answer as always returns localhost
     */
    private function setCorrectDomain($url){
        $wpsHost = parse_url($this->wps_url, PHP_URL_HOST);
        $recievedHost = parse_url($url, PHP_URL_HOST);
        return  str_replace($recievedHost, $wpsHost, $url);
    }       
}
