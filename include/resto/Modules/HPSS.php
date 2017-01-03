<?php

/**
 *
 * @author Atos 
 * HPSS module
 *
 *    Stage files from tape to disk
 *    
 *    | Resource                                                             | Description
 *    |___________________________________________________________________________________________________________
 *    |
 *    | HTTP/POST               /hpss?file=$path                             | stage specified file $path
 * 
 */
class HPSS extends RestoModule {
    
    /*
     * Resto context
     */
    public $context;
    
    /*
     * Current user (only set for administration on a single user)
     */
    public $user = null;
    
    /*
     * segments
     */
    public $segments;
    
    /*
     * Database handler
     */
    private $dbh;
    
    /**
     * Constructor
     *
     * @param RestoContext $context
     * @param RestoUser $user
     */
    public function __construct($context, $user) {
        parent::__construct($context, $user);
        
        // Set user
        $this->user = $user;
        
        // Set context
        $this->context = $context;
        
        // Database handler
        $this->dbh = $this->getDatabaseHandler();
    }
    
    /**
     * Run module - this function should be called by Resto.php
     *
     * @param array $elements : route elements
     * @param array $data : POST or PUT parameters
     *       
     * @return string : result from run process in the $context->outputFormat
     */
    public function run($segments, $data = array()) {

        /*
         * Only autenticated user.
         */
        if ($this->user->profile['userid'] == -1) {
            RestoLogUtil::httpError(401);
        }

        $this->segments = $segments;
        $method = $this->context->method;

        switch ($method) {
            case 'POST' :
                return $this->processPOST($data);
            default :
                RestoLogUtil::httpError(404);
        }
    }

    /**
     * Process on HTTP method POST on /hpss
     *
     * HTTP/POST               /hpss?file=$path             stage specified file
     * 
     */
    private function processPOST($data) {

        if (!isset($this->segments[0])) {            
            if (empty($this->context->query['file'])) {
                RestoLogUtil::httpError(400);
            }
            $path = $this->context->query['file'];
            return $this->staging($path);
        }
        /*
         * Unknown route
         */
        else {
            RestoLogUtil::httpError(404);
        }
    }

    /**
     * Execute an openserach to the same resto
     *
     * @param array $url Request url
     */
    private function staging($path) {
        error_log("staging ...", 0);
        $handle = fopen($path, "rb");
        if (!is_resource($handle)) {
            RestoLogUtil::httpError(404);
        }
        fread($handle, 1);
        fclose($handle);
    }
}