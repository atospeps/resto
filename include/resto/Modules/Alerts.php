<?php

/**
 *
* Alerts module
*
* Manage alerts
* 
*    |          Resource                                                |     Description
*    |__________________________________________________________________|______________________________________
*    |  GET     alerts                                                  |  List all subscriptions
*    |  POST    alerts                                                  |  Create or edit a subscription
*    |  POST    alerts/clear                                            |  Delete a subscription
*    |  GET     alerts/execute                                          |  Delete a subscription
* 
*/

class Alerts extends RestoModule {
    
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
         * Only GET method and POST are accepted
         */
        if ($this->context->method !== 'GET' && $this->context->method !== 'POST') {
            RestoLogUtil::httpError(404);
        }
        
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
    }
    
    /**
     * Process on HTTP method GET on /alerts
     *
     * @throws Exception
     */
    private function processGET() {
        if (isset($this->segments[0]) && $this->segments[0] = 'execute') {
          // Execute  
          $this->alertExecute(); 
        } else if (!isset($this->segments[0])) {
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
        } else {
            RestoLogUtil::httpError(404);
        }
    }
    
    /**
     * Process on HTTP method POST on /alerts and alerts/clear
     *
     * @throws Exception
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
            $alerts = pg_query($this->dbh, 'INSERT INTO usermanagement.alerts (email, title, creation_time, expiration, last_dispatch, period, criterias)
                    VALUES (\''. pg_escape_string($data['email']) . '\', \'' . pg_escape_string($data['title']) . '\', \'' . date("Y-m-d h:i:s", time()) . '\', \'' . 
                    pg_escape_string($data['expiration']) . '\', \'' . date("Y-m-d h:i:s", time()) . '\', \'' . $data['period'] . '\', \'' . 
                    json_encode($data['criterias']) . '\')');
            return array('status' => 'success', 'message' => 'success');
        } catch (Exception $e) {
            RestoLogUtil::httpError($e->getCode(), $e->getMessage());
        }
    }
    
    /**
     * We edit an alert
     *
     * @throws Exception
     */
    private function editAlert($data) {
        // Edit an alert using the alert id
        if (isset($data['aid'])) {
            try {
                $alerts = pg_query($this->dbh, 'UPDATE usermanagement.alerts SET 
                        email=\'' . pg_escape_string($data['email']) . '\', title=\'' . pg_escape_string($data['title']) . 
                        '\', creation_time=\'' . date("Y-m-d h:i:s", time()) . '\', expiration=\'' . pg_escape_string($data['expiration']) . 
                        '\', last_dispatch=\'' . date("Y-m-d h:i:s", time()) . '\', period=\'' . pg_escape_string($data['period']) .
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
     * We delete an alert
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
     * We execute the alerts. We send the maisl to the users
     *
     */
    private function alertExecute() {
        
        // We get the current date rounding the hours
        $date = date("Y-m-d H:00:00", time());
        $alerts = pg_query($this->dbh, "SELECT title, creation_time, email, criterias FROM usermanagement.alerts 
                WHERE expiration >= '" . $date . "' AND '" . $date . "'  >= last_dispatch + ( period || ' hour')::interval");
        
        // We iterate over all the results.
        // We will make the research and send the mail
        while ($row = pg_fetch_assoc($alerts)) {
            
            
            if (isset($row['criterias'])) {
                $criterias = json_decode($row['criterias']);
            }
            

            
            $http = 'http://localhost/resto/api/collections/' . (isset($criterias->collection) ? $criterias->collection .'/'  : '' )  . 
            'search.json?completionDate=2015-09-04T17:52:53&instrument=HRS&lang=fr&platform=S1A';
            
        }
        exit();
        
        // Call Resto to get 
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $http,
        ));
        $resp = curl_exec($curl);
        curl_close($curl);
        
        $answer = json_decode($resp, true); 
        
        
        foreach ($answer["features"] as $feature) {
            $this->createAlertSharedLink($feature['properties']['services']['download']['url'], 'ivan.raichs@gmail.com');
        }
        
        
        $content = $this->alertsToMETA4($answer["features"]);


        $params['filename'] = 'test.meta4';
        $params['to'] = 'ivan.raichs@atos.net';
        $params['message'] = 'Hello here we are!';
        $params['senderName'] = 'Resto Admin';
        $params['senderEmail'] = 'resto_admin@atos.net';
        $params['subject'] = 'testing that';
        $params['content'] = $content;
        
        $this->sendAttachedMeta4Mail($params);

    }
    
    /**
     * We pass the products returned in the opensearch and we convert them into 
     * a meta4 links ready to be downloaded  
     * 
     * @param array $items : all the products returned byt the open search
     */
    private function alertsToMETA4($items) {
        
        $meta4 = new RestoMetalink($this->context);
        
        /*
         * One metalink file per item - if user has rights to download file
         */
        foreach ($items as $item) {
            
            /*
             * Invalid item
             */
            if (!isset($item['properties']) || !isset($item['properties']['services']) || !isset($item['properties']['services']['download'])) {
                continue;
            }
            
            /*
             * Item not downloadable
             */
            if (!isset($item['properties']['services']['download']['url']) || !RestoUtil::isUrl($item['properties']['services']['download']['url'])) {
                continue;
            }
            
            $exploded = parse_url($item['properties']['services']['download']['url']);
            $segments = explode('/', $exploded['path']);
            $last = count($segments) - 1;
            if ($last > 2) {
                list ($modifier) = explode('.', $segments[$last], 1);
                if ($modifier !== 'download' || !$this->user->canDownload($segments[$last - 2], $segments[$last - 1])) {
                    continue;
                }
            }
            
            /*
             * Add link to the file
             */
            $meta4->addLink($item, $this->user->profile['email']);
        }
        
        // We return the content of the meta4 attached file
        return $meta4->toString();
    }
    
    /**
     * Creates the download link with the validation token
     *
     * @param string $resourceUrl : the url to make the download
     * @param string $email : user's mail
     */
    private function createAlertSharedLink($resourceUrl, $email, $duration = 86400) {
        
        // We validate the url exists
        if (!isset($resourceUrl) || !RestoUtil::isUrl($resourceUrl)) {
            return null;
        }
        // We set the exipration date for the token
        if (!is_int($duration)) {
            $duration = 86400;
        }
        // We create and we insert the token un db.
        $result = pg_query($this->dbh, 'INSERT INTO usermanagement.sharedlinks (url, token, validity, email) VALUES 
                (\'' . pg_escape_string($resourceUrl) . '\',\'' . (RestoUtil::encrypt(mt_rand() . microtime())) . '\',now() + ' . $duration . ' * \'1 second\'::interval,\'' . pg_escape_string($email) . '\') RETURNING token');
        
        // We get the token and we return it with it's url
        $token = pg_fetch_row($result);
        return array (
                'resourceUrl' => $resourceUrl,
                'token' => $token['0'] 
        );
    }
    
    /**
     * Send mails with the meta4 attached document
     *
     * @param array $params : parameters to send the mail
     */
    private function sendAttachedMeta4Mail($params) {
        
        // We get the file's content to be encoded
        $content = chunk_split(base64_encode($params['content']));
        $uid = md5(uniqid(time()));
        
        // Header to send the basic mail
        $headers = 'From: ' . $params['senderName'] . ' <' . $params['senderEmail'] . '>' . "\r\n";
        $headers .= 'Reply-To: doNotReply <' . $params['senderEmail'] . '>' . "\r\n";
        $headers .= 'X-Mailer: PHP/' . phpversion() . "\r\n";
        $headers .= 'X-Priority: 3' . "\r\n";
        $headers .= 'MIME-Version: 1.0' . "\r\n";
        $headers .= "Content-Type: multipart/mixed; boundary=\"" . $uid . "\"\r\n\r\n";
        
        // message & attachment
        $nmessage = "--" . $uid . "\r\n";
        $nmessage .= "Content-type:text/plain; charset=iso-8859-1\r\n";
        $nmessage .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $nmessage .= $params['message'] . "\r\n\r\n";
        $nmessage .= "--" . $uid . "\r\n";
        $nmessage .= "Content-Type: application/octet-stream; name=\"" . $params['filename'] . "\"\r\n";
        $nmessage .= "Content-Transfer-Encoding: base64\r\n";
        $nmessage .= "Content-Disposition: attachment; filename=\"" . $params['filename'] . "\"\r\n\r\n";
        $nmessage .= $content . "\r\n\r\n";
        $nmessage .= "--" . $uid . "--";
        
        if (mail($params['to'], $params['subject'], $nmessage, $headers, '-f' . $params['senderEmail'])) {
            return true;
        }
        return false;
    }
    
}
