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
        
        // Only admin users can notify users of the publication of new products
        if ($this->user->profile['groupname'] !== 'admin') {
            RestoLogUtil::httpError(403);
        }

        try {
            // We get the current date rounding the hours
            $now = time();
            $date = date("Y-m-d H:00:00", $now);

            $query = "SELECT u.country, a.aid, a.title, a.creation_time, a.email, a.last_dispatch, a.expiration, a.criterias"
                    . " FROM usermanagement.alerts a"
                    . " INNER JOIN usermanagement.users u"
                    . " ON u.email=a.email WHERE u.activated=1 AND '" . $date . "'  >= date_trunc('hour', a.last_dispatch)::timestamp + ( a.period || ' hour')::interval AND a.hasSubscribe=1";

            $alerts = pg_query($this->dbh, $query);
            if (!$alerts){
                throw new Exception("Alerts module - An unexpected error has occurred. $query", 500);
            }

            // We iterate over all the results.
            // We will make the research and send the mail
            while ($row = pg_fetch_assoc($alerts)) { 
                // We validate if the expiration is set. Then we compare with the current date
                // If it's not the case we launch mails
                if (!empty($row['expiration'])) {
                    $execute = ($row['expiration'] > $date) ? true : false;
                } else {
                    $execute = true;
                }
                // If execution is ok, we can start the mail process
                if ($execute === true) {

                    // Builds OpenSearch URL from user's search criteria
                    // Initializes completion date with new last dispatch date
                    $params = array('publishedEnd' => date("Y-m-d\TH:00:00", $now));
                    $url = $this->getUrl($row, $params);

                    // we execute the open search
                    $products = $this->openSearchRequest($url);
                    // We decode the results
                    $answer = json_decode($products, true);

                    // If there's no result, we don't send any mail
                    if (isset($answer['features']) && (count($answer['features']) > 0)) {
                        // we create the download links and the tokens on the database associated with the user
                        foreach ($answer['features'] as $feature) {
                            if (isset($feature['properties']['services']['download']['url'])){
                                $this->createAlertSharedLink($feature['properties']['services']['download']['url'], $row['email']);
                            }                            
                        }
                        // We create the content for a meta4 file from the products
                        $content = $this->alertsToMETA4($answer['features'], $row['email']);
                        if ($content !== FALSE) {
                            
                            $criterias = isset($row['criterias']) ? json_decode($row['criterias'], true) : array();
                            $row['title'] = !empty($row['title']) ? $row['title'] : http_build_query($criterias);

                            // We established all the parameters used on the mail
                            $params['filename'] = date("Y-m-d H:i:s", $now) . '.meta4';
                            $params['to'] = $row['email'];
                            $params['message'] = $this->getBodyMessage($row);
                            $params['senderName'] = $this->context->mail['senderName'];
                            $params['senderEmail'] = $this->context->mail['senderEmail'];
                            $langage = (isset($row['country']) && strtolower($row['country'])==='fr') ? 'fr' : 'en';
                            $params['subject'] = $this->context->dictionary->translate($this->options['notification'][$langage]['subject'], $row['title']);
                            $params['content'] = $content ;

                            // We send the mail
                            if (!$this->sendAttachedMeta4Mail($params)) {
                                // TODO: log error sending mail...
                            }
                        }
                    }
                }
                // Updates new last_dispatch of alert 
                $query = "UPDATE usermanagement.alerts SET last_dispatch='" . $date . "' WHERE aid=" . $row["aid"];               
                pg_query($this->dbh, $query);
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

        // Encode attachment file content
        $data = chunk_split(base64_encode($params['content']));
        $uid = md5(uniqid(time()));
        $rn = "\r\n";

        // Headers
        $headers = 'From: ' . $params['senderName'] . ' <' . $params['senderEmail'] . '>' . $rn;
        $headers .= 'Reply-To: doNotReply <' . $params['senderEmail'] . '>' . $rn;
        $headers .= 'X-Mailer: PHP/' . phpversion() . $rn;
        $headers .= 'X-Priority: 3' . $rn;
        $headers .= 'MIME-Version: 1.0' . $rn;
        $headers .= 'Content-Type: multipart/mixed; boundary="' . $uid .'"' . $rn;

        // Message body
        $msg = "--" . $uid . $rn;
        $msg .= 'Content-Type: text/html; charset=UTF-8' . $rn;
        $msg .= 'Content-Transfer-Encoding: binary' . $rn . $rn;
        $msg .= $params['message'] . $rn;

        // Message attachment
        $msg .= "--" . $uid . $rn;
        $msg .= 'Content-Type: application/octet-stream; name="' . $params['filename'] . '"' . $rn;
        $msg .= 'Content-Transfer-Encoding: base64' . $rn;
        $msg .= 'Content-Disposition: attachment; filename="' . $params['filename'] . '"' . $rn . $rn;
        $msg .= $data . $rn;
        $msg .= $rn . "--" . $uid . "--" . $rn;

        if (mail($params['to'], $params['subject'], $msg, $headers, '-f' . $params['senderEmail'])) {
            return true;
        }
        return false;
    }

    /**
     * Builds OpenSearch url from users's search criteria (alerts).
     * @param array $row Element returned from the database
     */
    private function getUrl($row, $params = null) {
        // We get the criterias to add them at the end of the url

        $criterias = isset($row['criterias']) ? array_merge(json_decode($row['criterias'], true), $params) : $params;
        $collection = isset($criterias['collection']) ? $criterias['collection'] : null;
        $searchUrl = $this->context->baseUrl . '/api/collections/' . (isset($collection) ? $collection . '/' : '') . 'search.json?';

        $queryParams = array();
        // Initializes search parameters
        if (!empty($criterias)){
            // completionDate is equals to new last dispatch date (ie. now)
            // startDate is equals to last dispatch date
            foreach ($criterias as $key => $value) {
                // Ignores following criteria (collection, startDate)
                if ($key != 'collection' && $key != 'startDate' && $key != 'completionDate') {
                    $queryParams[] = $key . '=' . $value;
                }
            }
        }
        $queryParams[] = 'publishedBegin=' . date("Y-m-d\TH:i:s", strtotime($row["last_dispatch"]));
        return $searchUrl . join('&', $queryParams);        
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
                CURLOPT_URL => $url,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => 0
        ));

        $products = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if(curl_errno($curl)){
          $error = curl_error($curl);
          throw new Exception($error);
        }
        curl_close($curl);
        return $products;
    }

    /**
     * Create the message body for the mail
     *
     * @param array $row Element returned from the database
     */
    private function getBodyMessage($row) {

        $langage = (isset($row['country']) && strtolower($row['country'])==='fr') ? 'fr' : 'en';
        $body = $this->context->dictionary->translate(
                $this->options['notification'][$langage]['message'], 
                $row['title']);
        return $body;
    }
}