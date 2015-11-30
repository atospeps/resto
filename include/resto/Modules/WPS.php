<?php

/**
 *
* WPS module
*
* Gerer les jobs
* 
* 
* 
*    |          Resource                                                |     Description
*    |__________________________________________________________________|______________________________________
*    |  GET     wtc/user/{userid}                                    |  Display users subscriptions
*    |  POST    wtc/users                                            |  Create user
* 
*/

class WPS extends RestoModule {
    
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
         * Only GET method on 'search' route with json outputformat is accepted
         */
        if ($this->context->method !== 'GET' && $this->context->method !== 'POST') {
            RestoLogUtil::httpError(404);
        }
        
        // We have permission to make the process
        if($this->canWPS() === '1'){
            // We get URL segments and the http method
            $this->segments = $segments;
            $method = $this->context->method;
            
            /*
             * Switch on HTTP methods
             */
            switch ($method) {
                case 'GET':
                    return $this->processGET();
                case 'POST':
                    return $this->processPOST($data);
                default:
                    RestoLogUtil::httpError(404);
            }
            
        }else{
            // Right denied
            RestoLogUtil::httpError(403);
        }
    }
    
    /**
     * Process on HTTP method GET on /alerts
     * 
     */
    private function processGET() {
        // Verify user is set
        if (isset($this->user->profile['email'])) {
            $alerts = pg_query($this->dbh, 'SELECT * from usermanagement.alerts WHERE email = \'' . pg_escape_string($this->user->profile['email']) . '\'');
            $result = array ();
            while ($row = pg_fetch_assoc($alerts)) {
                $result[] = $row;
            }
            return $result;
        } else {
            RestoLogUtil::httpError(403);
        }
        
    }
    
    /**
     * Process on HTTP method POST on /alerts and alerts/clear
     *
     */
    private function processPOST($data) {
        /*
         * Get the operation to proceed
         */
        if (!isset($this->segments[0]) && !isset($data['aid'])) {
            // If there is no identifier, an alert is created
            return $this->createAlert($data);
        } else if (!isset($this->segments[0]) && isset($data['aid'])) {
            // If there is an aid, we are editing an  existing alert
            return $this->editAlert($data);
        } else if ($this->segments[0] == 'clear') {
            // With the segment clear, we delete an alert
            return $this->deleteAlert($data);
        } else {
            RestoLogUtil::httpError(404);
        }
    }
    
    /**
     * We create an alert
     *
     * @throws Exception
     */
    private function createAlert($data) {
        try {
            $alerts = pg_query($this->dbh, 'INSERT INTO usermanagement.alerts WHERE email = \'' . pg_escape_string($this->user->profile['email']) . '\'');
            return array('status' => 'success', 'message' => 'success');
        } catch (Exception $e) {
            RestoLogUtil::httpError($e->getCode(), $e->getMessage());
        }
    }
    
    /**
     * We create an alert
     *
     * @throws Exception
     */
    private function editAlert($data) {
        // Edit an alert using the alert id
        if (isset($data['aid'])) {
            try {
                $alerts = pg_query($this->dbh, 'UPDATE usermanagement.alerts SET 
                        email=\'' . pg_escape_string($data['email']) . '\', title=\'' . pg_escape_string($data['title']) . 
                        '\', querytime=\'' . pg_escape_string($data['querytime']) . '\', expiration=\'' . pg_escape_string($data['expiration']) . 
                        '\', criterias=\'' . pg_escape_string($data['criterias']) . '\' WHERE aid=\'' . pg_escape_string($data['aid']) . '\'');
                return array ('status' => 'success', 'message' => 'success');
            } catch (Exception $e) {
                RestoLogUtil::httpError($e->getCode(), $e->getMessage());
            }
        } else {
            RestoLogUtil::httpError(404);
        }
    }
    
    /**
     * We create an alert
     *
     * @throws Exception
     */
    private function deleteAlert($data) {
        // Delete an alert using the alert id
        if (isset($data['aid'])) {
            try {
                $alerts = pg_query($this->dbh, 'DELETE FROM usermanagement.alerts WHERE aid = \'' . pg_escape_string($data['aid']) . '\'');
                return array ('status' => 'success', 'message' => 'success');
            } catch (Exception $e) {
                RestoLogUtil::httpError($e->getCode(), $e->getMessage());
            }
        } else {
            RestoLogUtil::httpError(404);
        }
    }
    
    /**
     * Process on HTTP method GET on /alerts
     *
     * @throws Exception
     */
    private function canWPS() {
        // Verify user is set
        if (isset($this->user->profile['email'])) {
            $result = pg_query($this->dbh, 'SELECT wps from usermanagement.rights WHERE emailorgroup = \'' . pg_escape_string($this->user->profile['email']) . '\'');
            // Return the result
            if (pg_fetch_result($result, 1, 'wps') === '1') {
                return pg_fetch_result($result, 1, 'wps'); 
            }else{
                // If no right is found
                RestoLogUtil::httpError(403);
            }
        }
    
    }   
    
}
