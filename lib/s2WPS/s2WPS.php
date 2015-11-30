<?php
/**
 * RESTo WPS product processing for S2
 */
class s2WPS {
    
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
    public function __construct($identifier, $modules, $dbh) {
        
        // We validate the config is correctly set
        if (isset($modules['s2WPS']) && 
                isset($modules['s2WPS']['wps_url'])) {
            // All the elements needed are set in th configuration
            $this->validConfiguration = TRUE;
            // We set the values 
            $this->wps_url = $modules['s2WPS']['wps_url'];
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
    public function execute($feature_id) {
        
        // Call the WPS Server
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->wps_url);
        curl_setopt($ch, CURLOPT_FAILONERROR, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $retValue = curl_exec($ch);
        curl_close($ch);

        if ($retValue !== FALSE) {
            // In orer to keep working...
            // The xml file returned cannot be treated by the xml php functions
            // We erase the tags which causes problems
            $tmp1 = str_replace("wps:", "", $retValue);
            $tmp2 = str_replace("ows:", "", $tmp1);
            
            // Transform to aray
            $xml = new SimpleXMLElement($tmp2);
            // Get the statusLocation
            $statusLocation = (string) $xml['statusLocation'];
            // Get the Status
            $status = array ();
            foreach ($xml->Status->children() as $key => $value) {
                $status[] = $key . ': ' . $value;
            }
            
            $process = pg_query($this->dbh, 'INSERT INTO _s2.wpsprocess VALUES (\'' . $feature_id . '\', \'' . date("Y-m-d h:i:s", time()) . '\', \'' . join(', ', $status) . '\', \'' . $statusLocation . '\')');
        }
    }
        
}
