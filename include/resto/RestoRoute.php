<?php
/*
 * Copyright 2014 Jérôme Gasperi
 *
 * Licensed under the Apache License, version 2.0 (the "License");
 * You may not use this file except in compliance with the License.
 * You may obtain a copy of the License at:
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
 * License for the specific language governing permissions and limitations
 * under the License.
 */

/**
 * RESTo REST router
 * 
 * List of routes
 * --------------
 * 
 * ** Collections **
 *  
 *      A collection contains a list of products. Usually a collection contains homogeneous products
 *      (e.g. "Spot" collection should contains products from Spot satellites; "France" collection should
 *      contains products linked to France) 
 *                           
 *    |          Resource                                      |      Description
 *    |________________________________________________________|________________________________________________
 *    |  GET     collections                                   |  List all collections            
 *    |  POST    collections                                   |  Create a new {collection}            
 *    |  GET     collections/{collection}                      |  Get {collection} description
 *    |  DELETE  collections/{collection}                      |  Delete {collection}
 *    |  PUT     collections/{collection}                      |  Update {collection}
 *    |  GET     collections/{collection}/{feature}            |  Get {feature} description within {collection}
 *    |  GET     collections/{collection}/{feature}/download   |  Download {feature}
 *    |  POST    collections/{collection}                      |  Insert new product within {collection}
 *    |  PUT     collections/{collection}/{feature}            |  Update {feature}
 *    |  PUT     collections/{collection}/title/{title}        |  Update {feature}
 *    |  DELETE  collections/{collection}/{feature}            |  Delete {feature}
 * 
 * ** Groups **
 *  
 *      Groups have rights on collections.
 *                           
 *    |          Resource                                      |      Description
 *    |________________________________________________________|________________________________________________
 *    |  GET     groups                                        |  List all groups           
 *    |  POST    groups                                        |  Create a new group   
 *    |  GET     groups/{groupid}                              |  Show group {groupid}                
 *    |  DELETE  groups/{group}                                |  Delete {group}
 *    |  PUT     groups/{group}                                |  Update {group}
 *
 * ** Proactive accounts **
 *  
 *    |          Resource                                      |      Description
 *    |________________________________________________________|________________________________________________
 *    |  GET     proactive                                     |  List all proactive accounts           
 *    |  POST    proactive                                     |  Create a new proactive account   
 *    |  GET     proactive/{gid}                               |  Show proactive account {gid}                
 *    |  DELETE  proactive/{gid}                               |  Delete proactive account {gid}
 *    |  PUT     proactive/{gid}                               |  Update proactive account {gid}
 *
 * ** Users **
 * 
 *      Users have rights on collections and/or products
 * 
 *    |          Resource                                      |     Description
 *    |________________________________________________________|______________________________________
 *    |  GET     users                                         |  List all users
 *    |  POST    users                                         |  Add a user
 *    |  GET     users/{userid}                                |  Show {userid} information
      |  PUT     users/{userid}                                |  Update {userid} information
 *    |  GET     users/{userid}/cart                           |  Show {userid} cart
 *    |  POST    users/{userid}/cart                           |  Add new item in {userid} cart
 *    |  DELETE  users/{userid}/cart                           |  Remove all cart items
 *    |  PUT     users/{userid}/cart/{itemid}                  |  Modify item in {userid} cart
 *    |  DELETE  users/{userid}/cart/{itemid}                  |  Remove {itemid} from {userid} cart
 *    |  GET     users/{userid}/orders                         |  Show orders for {userid}
 *    |  POST    users/{userid}/orders                         |  Send an order for {userid}
 *    |  GET     users/{userid}/orders/{orderid}               |  Show {orderid} order for {userid}
 *    |  GET     users/{userid}/rights                         |  Show rights for {userid}
 *    |  GET     users/{userid}/rights/{collection}            |  Show rights for {userid} on {collection}
 *    |  GET     users/{userid}/rights/{collection}/{feature}  |  Show rights for {userid} on {feature} from {collection}
 * 
 *    Note: {userid} can be replaced by base64(email) 
 * 
 * ** API **
 * 
 *    |          Resource                                      |     Description
 *    |________________________________________________________|______________________________________
 *    |  GET     api/collections/search                        |  Search on all collections
 *    |  GET     api/collections/{collection}/search           |  Search on {collection}
 *    |  GET     api/collections/describe                      |  Opensearch service description at collections level
 *    |  GET     api/collections/{collection}/describe         |  Opensearch service description for products on {collection}
 *    |  POST    api/users/connect                             |  Connect user
 *    |  GET     api/users/disconnect                          |  Disconnect user
 *    |  GET     api/users/checkToken                          |  Check if token is valid (i.e. not revoked)
 *    |  GET     api/users/resetPassword                       |  Ask for password reset (i.e. reset link sent to user email adress)
 *    |  GET     api/users/{userid}/activate                   |  Activate users with activation code
 *
 */
abstract class RestoRoute {
    
    const ERROR_UAVAILABLE = 1;
    const ERROR_BADRIGTHS = 2;
    const ERROR_FEATUREMOVE = 3;
    
    /*
     * RestoContext
     */
    protected $context;
    
    /*
     * RestoUser
     */
    protected $user;
    
    /**
     * Constructor
     */
    public function __construct($context, $user) {
        $this->context = $context;
        $this->user = $user;
    }
   
    /**
     * Route to resource
     * 
     * @param array $segments : path as route segments
     */
    abstract public function route($segments);
    
    /**
     * Launch module run() function if exist otherwise returns 404 Not Found
     * 
     * @param array $segments - path (i.e. a/b/c/d) exploded as an array (i.e. array('a', 'b', 'c', 'd')
     * @param array $data - data (POST or PUT)
     */
    protected function processModuleRoute($segments, $data = array()) {
        
        $module = null;
        
        foreach (array_keys($this->context->modules) as $moduleName) {
            
            if (isset($this->context->modules[$moduleName]['route'])) {
                
                $moduleSegments = explode('/', $this->context->modules[$moduleName]['route']);
                $routeIsTheSame = true;
                $count = 0;
                for ($i = 0, $l = count($moduleSegments); $i < $l; $i++) {
                    $count++;
                    if (!isset($segments[$i]) || $moduleSegments[$i] !== $segments[$i]) {
                        $routeIsTheSame = false;
                        break;
                    } 
                }
                if ($routeIsTheSame) {
                    $module = RestoUtil::instantiate($moduleName, array($this->context, $this->user));
                    for ($i = $count; $i--;) {
                        array_shift($segments);
                    }
                    return $module->run($segments, $data);
                }
            }
        }
        if (!isset($module)) {
            RestoLogUtil::httpError(404);
        }
    }

    /**
     * Store query to database
     * 
     * @param string $serviceName
     * @param string $collectionName
     */
    protected function storeQuery($serviceName, $collectionName, $featureIdentifier) {
        if ($this->context->storeQuery === true && isset($this->user)) {
            $this->user->storeQuery($this->context->method, $serviceName, isset($collectionName) ? $collectionName : null, isset($featureIdentifier) ? $featureIdentifier : null, $this->context->query, $this->context->getUrl());
        }
    }
   
    /**
     * Send user activation code by email
     * 
     * @param array $params
     */
    protected function sendMail($params) {
        $headers = 'From: ' . $params['senderName'] . ' <' . $params['senderEmail'] . '>' . "\r\n";
        $headers .= 'Reply-To: doNotReply <' . $params['senderEmail'] . '>' . "\r\n";
        $headers .= 'X-Mailer: PHP/' . phpversion() . "\r\n";
        $headers .= 'X-Priority: 3' . "\r\n";
        $headers .= 'MIME-Version: 1.0' . "\r\n";
        $headers .= 'Content-type: text/html; charset=UTF-8' . "\r\n";
        if (mail($params['to'], $params['subject'], $params['message'] , $headers, '-f' . $params['senderEmail'])) {
            return true;
        }
        return false;
    }

    /**
     * Return userid from base64 encoded email or id string
     * 
     * @param string $emailOrId
     */
    protected function userid($emailOrId) {
        
        if (!ctype_digit($emailOrId)
                && isset($this->user->profile['email']) 
                && $this->user->profile['email'] === strtolower(base64_decode($emailOrId))) {
                    return $this->user->profile['userid'];
        }
        
        return $emailOrId;
    }
    
    /**
     * Return user object if authorized
     * 
     * @param string $emailOrId
     */
    protected function getAuthorizedUser($emailOrId)
    {
        $user = $this->user;
        $userid = $this->userid($emailOrId);
        if ($user->profile['userid'] !== $userid) {
            if (!$user->isAdmin()) {
                RestoLogUtil::httpError(403);
            }
            else {
                if (!ctype_digit($emailOrId)) {
                    $user = new RestoUser($this->context->dbDriver->get(RestoDatabaseDriver::USER_PROFILE, array('email' => strtolower(base64_decode($emailOrId)))), $this->context);
                }
                else {
                    $user = new RestoUser($this->context->dbDriver->get(RestoDatabaseDriver::USER_PROFILE, array('userid' => $userid)), $this->context);
                }
            }
        }
        
        return $user;
        
    }
    
    /**
     * Validate that a user can download a certain product
     * 
     * @param {object} $feature 
     * returns {mixed} "OK" si tous les contrôles sont ok sinon array(message => {string}, feature => {string})
     */
    protected function checkFeatureAvailability($feature)
    {     
        // First we verify if the product's file is in our infrastructure
        // We verify th existence of a file in the server
        if (isset($feature['properties']['resourceInfos']['path'])) {
            $filePath = $feature['properties']['resourceInfos']['path'];
            if ( !file_exists($filePath) || ($fp = fopen($filePath, "rb"))===false ) {
                return array(
                    "error" => self::ERROR_UAVAILABLE,
                    "message" => "Resource is unavailable",
                    "feature" => $feature['id']
                );
            }
        // We verify the existence of an external file
        } elseif (isset($feature['properties']['services']['download']['url']) && RestoUtil::isUrl($feature['properties']['services']['download']['url'])) {
            $filePath = $feature['properties']['services']['download']['url'];
            if ( ($fp = fopen($filePath, "rb"))===false ) {
                return array(
                    "error" => self::ERROR_UAVAILABLE,
                    "message" => "Resource is unavailable",
                    "feature" => $feature['id']
                );
            }
        }
    
        if(isset($feature['properties']['visible']) && $feature['properties']['visible'] == 0) {
            RestoLogUtil::httpError(404, 'Feature has been moved, new feature id is : ' . $feature['properties']['newVersion']);
            return array(
                "error" => self::ERROR_FEATUREMOVE,
                "message" => "Feature has been moved",
                "feature" => $feature['id']
            );
        }
        
        // Secondly we verify all the rights
        
        /*
        * User do not have right to download product
        */
        if (!$this->user->canDownload($feature['properties']['collection'], $feature['id'])) {
            return array(
                "error" => self::ERROR_BADRIGTHS,
                "message" => "User doesn't have rights",
                "feature" => $feature['id']
            );
        }
    
        /*
        * Existinf file + rights = OK
        */
        return "OK";
    }
}
