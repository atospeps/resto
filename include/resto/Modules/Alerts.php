<?php

/**
 *
 * @author Atos 
 * Alerts module
 *
 * Manage alerts
 *    
 *    | Resource                                                             | Description
 *    |___________________________________________________________________________________________________________
 *    |
 *    | HTTP/GET  /alerts                                                    | List all subscriptions
 *    | HTTP/POST /alerts                                                    | Adds alert
 *    | HTTP/POST /alerts/{alertid}                                          | Edit alert
 *    | HTTP/POST /alerts/{alertid}/clear                                    | Deletes alert
 *    | HTTP/POST /alerts/execute                                            | Execute the subscriptions
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
         * Only autenticated user.
         */
        if ($this->user->profile['userid'] == -1) {
            RestoLogUtil::httpError(401);
        }

        $this->segments = $segments;
        $method = $this->context->method;

        /*
         * Switch on HTTP methods
         * Only GET method and POST are accepted
         */
        switch ($method) {
            case 'GET' :
                return $this->processGET();
            case 'POST' :
                return $this->processPOST($data);
            default :
                RestoLogUtil::httpError(404);
        }
    }
    
    /**
     * Process on HTTP method GET on /alerts
     *
     * @throws Exception
     */
    private function processGET() {
        /*
         * alerts
         */
        if (!isset($this->segments[0])) {
            return $this->getUserAlerts($this->user->profile['email']);
        }         
        /*
         * Unknown route
         */
        else {
            RestoLogUtil::httpError(404);
        }
    }
    
    /**
     * Returns user alerts.
     *
     * @param string $identifier : email
     */
    private function getUserAlerts() {
        $identifier = $this->user->profile['email'];
        $query = 'SELECT * from usermanagement.alerts WHERE email = \'' . pg_escape_string($identifier) . '\'';
        $alerts = pg_query($this->dbh, $query);
        $result = array ();
        while ($row = pg_fetch_assoc($alerts)) {
            $row['criterias'] = json_decode($row['criterias']);
            $result[] = $row;
        }
        
        return RestoLogUtil::success('Searches list for user ' . $this->user->profile['userid'], array (
                'items' => $result 
        ));
    }

    /**
     * Process on HTTP method POST on /alerts and alerts/clear
     *
     *
     * | HTTP/POST /alerts | Adds alert
     * | HTTP/POST /alerts/{alertid} | Edits alert
     * | HTTP/POST /alerts/{alertid}/clear | Deletes alert
     * | HTTP/POST /alerts/execute | Execute the subscriptions
     *
     * @throws Exception
     */
    private function processPOST($data) {

        /*
         * alerts
         */
        if (!isset($this->segments[0])) {
            return $this->createAlert($data);
        }
        /*
         * alerts/execute
         * alerts/{alertid}
         */
        else if (isset($this->segments[0]) && !isset($this->segments[1])) {
            switch ($this->segments[0]) {
                case 'execute':
                    /*
                     * Execute
                     */
                    return $this->alertExecute();
                default:
                    /*
                     * Edits alert
                     */
                    return $this->editAlert($this->segments[0], $data);
            }
        }
        /*
         * alerts/{alertid}/clear
         */
        else if (isset($this->segments[0]) && !isset($this->segments[2]) && ($this->segments[1] == 'clear') ) {
            return $this->deleteAlert($this->segments[0]);
        }
        /*
         * Unknown route
         */
        else {
            RestoLogUtil::httpError(404);
        }
    }

    /**
     * Store alert.
     *
     * @throws Exception
     */
    private function createAlert($data) {
        try {
            /*
             * Gets alert properties.
             */
            $now = '\'' . date("Y-m-d H:i:s") . '\'';
            $identifier = '\'' . pg_escape_string($this->user->profile['email']) . '\'';
            $title = isset($data['title']) ? '\'' . pg_escape_string($data['title']) . '\'' : 'NULL';
            $expiration = isset($data['expiration']) ? '\'' . pg_escape_string($data['expiration']) . '\'' : 'NULL';
            $period = (isset($data['period']) && is_numeric($data['period'])) ? '\'' . $data['period'] . '\'' : 'NULL';
            $hasSubscribe = (isset($data['hasSubscribe']) && $data['hasSubscribe'] == true) ? 1 : 0;
            $criterias = '\'' . json_encode($data['criterias']) . '\'';
            
            $values = array (
                    $identifier,
                    $title,
                    $now,
                    $expiration,
                    $now,
                    $period,
                    $criterias,
                    $hasSubscribe 
            );
            /*
             * Stores alert.
             */
            $query = 'INSERT INTO usermanagement.alerts (email, title, creation_time, expiration, last_dispatch, period, criterias, hasSubscribe)
                    VALUES (' . join(',', $values) . ')';
            
            $alerts = pg_query($this->dbh, $query);

            return RestoLogUtil::success('Search context created for user ' . $this->user->profile['userid']);
        } catch (Exception $e) {
            RestoLogUtil::httpError($e->getCode(), $e->getMessage());
        }
    }

    /**
     * Edit alert.
     *
     * @throws Exception
     */
    private function editAlert($alertid, $data) {
        try {

            $identifier = $this->user->profile['email'];
            $sqlUpdate = array();
            
            /*
             * Gets editable properties
             */
            if (isset($data['title'])){
                $sqlUpdate[] = "title='" . pg_escape_string($data['title']) . '\'';
            }
            if (isset($data['expiration'])){
                if($data['expiration'] == "") {
                    $sqlUpdate[] = "expiration=NULL";
                } else {
                    $sqlUpdate[] = "expiration='" . pg_escape_string($data['expiration']) . '\'';
                }
            }

            if (isset($data['period'])) {
                $sqlUpdate[] =  "period='" . pg_escape_string($data['period']) . '\'';
            }
            if (isset($data['hasSubscribe'])) {
                $sqlUpdate[] = "hassubscribe=" . (($data['hasSubscribe'] == true) ? 1 : 0);
            }
            if (isset($data['criterias'])){
                $sqlUpdate[] = "criterias='" . json_encode($data['criterias']) . '\'';
            }

            if (count($sqlUpdate) > 0) {                
                $query = 'UPDATE usermanagement.alerts'
                . ' SET ' . implode(' , ', $sqlUpdate)
                . ' WHERE aid = \'' . pg_escape_string($alertid) . '\' AND email = \'' . pg_escape_string($identifier) . '\''; 
                $alerts = pg_query($this->dbh, $query);
            }
            return RestoLogUtil::success('Search context ' . $alertid . ' updated');
        } catch (Exception $e) {
            RestoLogUtil::httpError($e->getCode(), $e->getMessage());
        }
    }

    /**
     * Delete alert.
     *
     * @throws Exception
     */
    private function deleteAlert($alertid) {
        try {
            $identifier = $this->user->profile['email'];
            $query = 'DELETE FROM usermanagement.alerts WHERE aid = \'' . pg_escape_string($alertid) . '\' AND email = \'' . pg_escape_string($identifier) . '\'';
            
            $alerts = pg_query($this->dbh, $query);
            
            return RestoLogUtil::success('Search context ' . $alertid . ' deleted');
        } catch (Exception $e) {
            RestoLogUtil::httpError($e->getCode(), $e->getMessage());
        }
    }
    
    /**
     * We execute the alerts.
     * We send the mails to the users
     */
    private function alertExecute() {
        try {
            // We get the current date rounding the hours
            $date = date("Y-m-d H:00:00", time());
            $alerts = pg_query($this->dbh, "SELECT aid, title, creation_time, email, last_dispatch, expiration, criterias FROM usermanagement.alerts
                WHERE '" . $date . "'  >= last_dispatch + ( period || ' hour')::interval");
            
            // We iterate over all the results.
            // We will make the research and send the mail
            while ($row = pg_fetch_assoc($alerts)) { 
                // We validate if the expiration is set. Then we compare with the current date
                // If it's not the case we launch mails
                if (!empty($row['expiration']) && !is_null($row['expiration'])) {
                    if ($row['expiration'] > $date) {
                        $execute = true;
                    } else {
                        $execute = false;
                    }
                } else {
                    $execute = true;
                }
                // If execution is ok, we can start the mail process
                if ($execute === true) {
                    // crete the open search url from the data in the database
                    $url = $this->getUrl($row);
                    // we execute the open search
                    $products = $this->openSearchRequest($url);
                    // We decode the results
                    $answer = json_decode($products, true);
                    // If there's no result, we don't send any mail
                    if ($answer !== FALSE) {
                        // we create the download links and the tokens on the database associated with the user
                        foreach ($answer["features"] as $feature) {
                            $this->createAlertSharedLink($feature['properties']['services']['download']['url'], $row['email']);
                        }
                        // We create the content for a meta4 file from the products
                        $content = $this->alertsToMETA4($answer["features"], $row['email']);
                        if ($content !== FALSE) {
                            // We established all the parameters used on the mail
                            $params['filename'] = date("Y-m-d H:i:s", time()) . '.meta4';
                            $params['to'] = $row['email'];
                            $params['message'] = $this->setMailMessage($row);
                            $params['senderName'] = $this->context->mail['senderName'];
                            $params['senderEmail'] = $this->context->mail['senderEmail'];
                            $params['subject'] = 'PEPS: Abonnement';
                            $params['content'] = $content;
                            
                            // We send the mail
                            if ($this->sendAttachedMeta4Mail($params)) {
                                // After sending the mail we update the database with the new last_dispatch
                                pg_query($this->dbh, "UPDATE usermanagement.alerts SET last_dispatch='" . date("Y-m-d\TH:00:00", time()) . "' WHERE aid=" . $row["aid"]);
                            }
                        }
                    }
                }
            }
            return RestoLogUtil::success('Alerts notification successfully launched');
        } catch (Exception $e) {
            RestoLogUtil::httpError($e->getCode(), $e->getMessage());
        }
    }

    /**
     * We pass the products returned in the opensearch and we convert them into
     * a meta4 links ready to be downloaded
     *
     * @param array $items : all the products returned byt the open search
     */
    private function alertsToMETA4($items, $email) {
        $meta4 = new RestoMetalink($this->context);
        
        // One metalink file per item - if user has rights to download file
        foreach ($items as $item) {
            // Invalid item
            if (!isset($item['properties']) || !isset($item['properties']['services']) || !isset($item['properties']['services']['download'])) {
                continue;
            }
            // Item not downloadable
            if (!isset($item['properties']['services']['download']['url']) || !RestoUtil::isUrl($item['properties']['services']['download']['url'])) {
                continue;
            }
            // We explode the url to get rights
            $exploded = parse_url($item['properties']['services']['download']['url']);
            $segments = explode('/', $exploded['path']);
            $last = count($segments) - 1;
            if ($last > 2) {
                list ($modifier) = explode('.', $segments[$last], 1);
                if ($modifier !== 'download' || !$this->canAlertsDownload('download', $segments[$last - 2], $segments[$last - 1], $email)) {
                    continue;
                }
            }
            
            // Add link to the file
            $meta4->addLink($item, $this->user->profile['email']);
        }
        
        // We return the content of the meta4 attached file
        return $meta4->toString();
    }

    /**
     *
     * @param string $action
     * @param string $collectionName
     * @param string $featureIdentifier
     * @return If user has the rights to download a product
     */
    private function canAlertsDownload($action, $collectionName = null, $featureIdentifier = null, $email) {
        // We need to establish the user group
        $result = pg_query($this->dbh, "SELECT groupname FROM usermanagement.users WHERE email='" . $email . "'");
        $group = pg_fetch_assoc($result);
        // We load the rights for the user in the alert
        $rights = new RestoRights($email, $group['groupname'], $this->context);
        
        // Normal case - checke user rights
        $rights = $rights->getRights($collectionName, $featureIdentifier);
        return $rights[$action];
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

    /**
     * From the elements recuperd on the database we create the opensearch url
     *
     * @param array $row Element returned from the database
     */
    private function getUrl($row) {
        // We get the criterias to add them at the end of the url
        if (isset($row['criterias'])) {
            // We decode the criterias
            $criterias = json_decode($row['criterias']);
            // We add the collection to the url
            $url = $this->context->baseUrl . '/api/collections/' . (isset($criterias->collection) ? $criterias->collection . '/' : '') . 'search.json';
            // We set the arguments
            $arguments = array ();
            foreach ($criterias as $key => $value) {
                // We don't insert the collection as an argument
                if ($key != 'collection') {
                    $arguments[] = $key . '=' . $value;
                }
            }
            // We always have to filter the ingestion date (published) with the las tile we
            // dispatched the alert
            $arguments[] = 'startPublishedDate=' . date("Y-m-d\TH:i:s", strtotime($row["last_dispatch"]));
            // We add the arguments to the url
            return $url . '?' . join('&', $arguments);
        } else {
            // If we want the products ingested into resto from the last alert dispatch we need
            // to filter the "published" column using last_dispatch
            return $this->context->baseUrl . '/api/collections/search.json?startPublishedDate=' . $row["last_dispatch"];
        }
    }

    /**
     * Execute an openserach to the same resto
     *
     * @param array $url Request url
     */
    private function openSearchRequest($url) {
        // Call Resto to get
        $curl = curl_init();
        curl_setopt_array($curl, array (
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_URL => $url 
        ));
        $products = curl_exec($curl);
        curl_close($curl);
        return $products;
    }

    /**
     * Create the message body for the mail
     *
     * @param array $row Element returned from the database
     */
    private function setMailMessage($row) {
        $body .= "Peps Alert\n";
        $body .= "Title: " . $row['title'] . "\n";
        $body .= "Creation time: " . $row['creation_time'] . "\n";
        $body .= "Criterias: " . $row['criterias'] . "\n";
        return $body;
    }
}