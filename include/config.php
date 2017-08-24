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
return array(
    
    /*
     * General
     */
    'general' => array(
        
        /*
         * Title
         */
        'title' => 'resto',
        
        /*
         * Relative endpoint to directory containing index.php
         * i.e. if index.php is at http://localhost/resto then
         * rootEndPoint would be '/resto'
         */
        'rootEndpoint' => '/resto',
        
        /*
         * Supported languages
         * 
         * All supported languages must be associated with a dictionary class
         * called RestoDictionary_{language} (usually located under $RESTO_BUILD/include/resto/Dictionaries) 
         */
        'languages' => array('en', 'fr'),
        
        /*
         * Debug mode
         */
        'debug' => false,
        
        /*
         * Timezone
         */
        'timezone' => 'UTC',
        
        /*
         * Protocol :
         *  - http : use http
         *  - https : use https
         *  - auto : server will choose depending on input request
         */
        'protocol' => 'auto',
        
        /*
         * Store queries ? (i.e. logs)
         */
        'storeQuery' => true,
        
        /*
         * Shared links validity duration (in seconds)
         * Default is 1 day (i.e. 86400 seconds)
         */
        'sharedLinkDuration' => 86400,
        
        /*
         * Maximum number of products that the user can add in the cart (0 = no limit)
         */
        'cartMaxProducts' => 0,
        
        /*
         * Maximum number of products that the user can add in the processing cart (0 = no limit)
         */
        'processingCartMaxProducts' => 0,
        
        /*
         * Authentication tokens validity duration (in seconds)
         * Default is 1 hour (i.e. 3600 seconds)
         */
        'tokenDuration' => 3600,
        
        /*
         * Authentication tokens validity duration (in seconds) for administration
         * Default is 24 hours (i.e. 86400 seconds)
         */
        'tokenAdministrationDuration' => 86400,
        
        /*
         * JSON Web Token passphrase
         * (see https://tools.ietf.org/html/draft-ietf-oauth-json-web-token-32)
         */
        'passphrase' => 'Super secret passphrase',
        
        /*
         * JSON Web Token accepted encryption algorithms
         */
        'tokenEncryptions' => array('HS256','HS512','HS384','RS256'),
        
        /*
         * Url to call for password reset
         */
        'resetPasswordUrl' => 'http://localhost/rocket/#/resetPassword',
        
        /*
         * Upload directory (for POST with attachement request)
         */
        'uploadDirectory' => '/tmp/resto_uploads',
        
        /*
         * Set how the products are streamed to user :
         *   - 'php' : stream through PHP process (slowest but works on all platforms)
         *   - 'apache' : stream through Apache (needs the XSendfile module to be installed and configured)
         *   - 'nginx' : stream through Nginx using the X-accel method
         */
        'streamMethod' => 'php',
        
        /*
         * List of http origin that have CORS access to server
         * (see http://en.wikipedia.org/wiki/Cross-origin_resource_sharing)
         * 
         * If the array is empty, then every http origin have CORS access
         */
        'corsWhiteList' => array(
            'localhost'
        ),
            
        /*
         * Set the default user download limit (nb max - 0 = no limit)
         */
        'instantLimitDownload' => 0,
        'weeklyLimitDownload' => 200,

        /*
         * Tape data management (download service)
         */
        'hpss' => array(
                'restapi' => array(
                        'timeout' => 1500, // milliseconds
                        /* 
                         * Returns storage information
                         * {"path": <file_path>, "storage": "<disk or tape>", "id": "< 0 if storage disk, otherwise XXXXX (tape identifier)"}
                         */
                        'getStorageInfo' => 'http://pepsvfs:8081/hpss'
                ),
                'timeout' => 2, // seconds
                'retryAfter' =>  180000 // milliseconds
        )
    ),

    /*
     * Database configuration
     */
    'database' => array(
        
        /*
         * Driver name must be associated to a RestoDatabaseDriver class called
         * RestoDatabaseDriver_{driver} (usually located under $RESTO_BUILD/include/resto/Drivers)
         */
        'driver' => 'PostgreSQL',
        
        /*
         * Cache directory used to store Database queries
         * Must be readable and writable for Webserver user
         * If not set, then no cache is used
         */
        //'dircache' => '/tmp',
        
        /*
         * Database name
         */
        'dbname' => 'resto',
        
        /*
         * Database host - if not specified connect through socket instead of TCP/IP
         */
//         'host' => 'localhost',
        
        /*
         * Database port
         */
        'port' => 5432,
        
        /*
         * Pagination
         * Default number of search results returned by page if not specified in the request
         */
        'resultsPerPage' => 20,
        
        /*
         * Database user with READ+WRITE privileges (see http://github.com/jjrom/resto/README.md)
         */
        'user' => 'resto',
        'password' => 'resto'
    ),
    
    /*
     * Authentication
     */
    'mail' => array(
        
        /*
         * Name display to users when they receive email from application
         */
        'senderName' => 'admin',
        
        /*
         * Email display to users when they receive email from application
         */
        'senderEmail' => 'restoadmin@localhost',
        
        /*
         * Account activation email
         */
        'accountActivation' => array(
            'en' => array(
                'subject' => '[{a:1}] Activation code',
                'message' => 'Hi,<br>You have registered an account to {a:1} application<br><br>To validate this account, go to {a:2} <br><br>Regards<br><br>{a:1} team"'
            ),
            'fr' => array(
                'subject' => '[{a:1}] Code d\'activation',
                'message' => "Bonjour,<br><br>Vous vous êtes enregistré sur l'application {a:1}<br><br>Pour valider votre compte, cliquer sur le lien {a:2} <br><br>Cordialement<br><br>L'équipe {a:1}"
            )
        ),
        
        /*
         * Reset password email
         */
        'resetPassword' => array(
            'en' => array(
                'subject' => '[{a:1}] Reset password',
                'message' => 'Hi,<br><br>You ask to reset your password for the {a:1} application<br><br>To reset your password, go to {a:2} <br><br>Regards<br><br>{a:1} team'
            ),
            'fr' => array(
                'subject' => '[{a:1}] Demande de réinitialisation de mot de passe',
                'message' => "Bonjour,<br><br>Vous avez demandé une réinitialisation de votre mot de passe pour l'application {a:1}<br><br>Pour réinitialiser ce mot de passe, veuillez vous rendre sur le lien suivant {a:2} <br><br>Cordialement<br><br>L'équipe {a:1}"
            )
        )
    ),
    
    /*
     * Modules
     */
    'modules' => array(
    	'HPSS' => array(
    	        'activate' => true,
    	        'route' => 'hpss',
    	        'options' => array()
    	),
        'WPS' => array(
                'activate' => true,
                'route' => 'wps',
                'options' => array(
                        // public config
                        'serverAddress' => 'http://192.168.56.102/resto/wps',
                        'outputsUrl' => 'http://192.168.56.102/resto/wps/outputs/',
                        // pywps configuration
                        'pywps' => array(
                                'serverAddress' => "http://localhost:8081/cgi-bin/pywps.cgi", // VIZO TEST
                                'outputsUrl' => 'http://localhost:8081/wps/outputs/',
                                'conf' => array(
                                    'serverAddress' => 'http://localhost:8081/cgi-bin/pywps.cgi',
                                    'outputsUrl' => 'http://localhost:8081/wps/outputs/'
                                )
                        ),
                        'users' => array(
                                /*
                                 * Minimum period (seconds) between processings updates. 
                                 * This option prevent user from abusing of manual refresh.
                                 * Default value: 10
                                 */ 
                                'minPeriodBetweenProcessingsRefresh' => 20,
                                /* 
                                 * ? "Remove" also deletes processings from database
                                 * Default value: false
                                 */
                                'doesRemoveAlsoDeletesProcessingsFromDatabase' => false,
                                /* 
                                 * Time life of processings (days)
                                 * Default value : 0 (0 => Infinite)
                                 */
                                'timeLifeOfProcessings' => 1
                        ),
                        'curlOpts' => array(
                                CURLOPT_PROXY => '',
                                CURLOPT_TIMEOUT => 30,
                                CURLOPT_CONNECTTIMEOUT => 30
                        ),
                )
        ),
        /*
         * Alerts
         */
        'Alerts' => array(
               'activate' => true,
               'route' => 'alerts',
               'options' => array(
                   'notification' => array(
                           'fr' => array(
                                   'subject' => 'PEPS : les résultats de votre abonnement « {a:1} »',
                                   'message' => 'Bonjour,<br><br>' 
                                    . 'Vous trouverez ci-dessous un fichier meta4 qui contient la liste des nouveaux produits acquis par PEPS qui correspondent à votre recherche « {a:1} ».<br>'
                                    . 'Pour visualiser le contenu de votre recherche ou éventuellement modifier les critères de cette recherche rendez-vous <a href="https://peps.cnes.fr/rocket/#/account/alerts">ici</a>.<br><br>'
                                    . 'Cordialement<br>'
                                    . '<img src="https://peps.cnes.fr/rocket/assets/img/peps_logo_alert.jpg"><br>'
                                    . 'L’équipe d’exploitation PEPS<br>'
                                    . 'CNES<br>'
                                    . '<a href="https://peps.cnes.fr">https://peps.cnes.fr</a>'
                           ),
                           'en' => array(
                                   'subject' => 'PEPS : your « {a:1} » subscription results',
                                   'message' => 'Dear PEPS user,<br><br>' 
                                    . 'Please find below a meta4 file containing the list of new acquired PEPS products corresponding to your search « {a:1} ».<br>'
                                    . 'To display your search content or to modify your search criteria, go <a href="https://peps.cnes.fr/rocket/#/account/alerts">there</a>.<br><br>'
                                    . 'Regards<br>'
                                    . '<img src="https://peps.cnes.fr/rocket/assets/img/peps_logo_alert.jpg"><br>'
                                    . 'PEPS operation team<br>'
                                    . 'CNES<br>'
                                    . '<a href="https://peps.cnes.fr">https://peps.cnes.fr</a>'
                           )
                   )
               )
        ),
            
    	/*
    	 * Administration
    	 */
    	'Administration' => array(
    			'activate' => true,
    			'route' => 'administration',
    			'options' => array()
    	),
        
        /*
         * OAuth authentication module
         */
        'Auth' => array(
            'activate' => true,
            'route' => 'api/auth',
            'options' => array(
                'providers' => array(
                    'google' => array(
                        'clientId' => '===>Insert your clienId here<===',
                        'clientSecret' => '===>Insert your clienSecret here<==='
                    ),
                    'linkedin' => array(
                        'clientId' => '===>Insert your clienId here<===',
                        'clientSecret' => '===>Insert your clienSecret here<==='
                    ),
                    'theiatest' => array(
                        'protocol' => 'oauth2',
                        'clientId' => '===>Insert your clienSecret here<===',
                        'clientSecret' => '===>Insert your clienSecret here<===',
                        'accessTokenUrl' => 'https://sso.kalimsat.eu/oauth2/token',
                        'peopleApiUrl' => 'https://sso.kalimsat.eu/oauth2/userinfo?schema=openid',
                        'uidKey' => 'http://theia.org/claims/emailaddress'
                    )
                ),
                /*
                 * PHP >= 5.6 check SSL certificate
                 * Set verify_peer and verify_peer_name to false if you have issue
                 */
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false
                )
            )
        ),
        
        /*
         * Query Analyzer module - convert natural language query to EO query
         */
        'QueryAnalyzer' => array(
            'activate' => true,
            'route' => 'api/query/analyze',
            'options' => array(
                'minimalQuantity' => 25
            )
        ),
        
        /*
         * Gazetteer module - enable location based search
         * Note : set database options if gazetteer is not installed in RESTo database
         * 
         * !!! Require iTag !!!
         */
        'Gazetteer' => array(
            'activate' => true,
            'route' => 'api/gazetteer/search',
            'options' => array(
                'database' => array(
                    'dbname' => 'itag',
                    /*
                     * Database host - if not specified connect through socket instead of TCP/IP
                     */
                    'host' => 'localhost',
                    'user' => 'itag',
                    'password' => 'itag'
                )
            )
        ),
        
        /*
         * Wikipedia module - enable location based wikipedia entries display
         * 
         * !!! Require iTag !!!
         */
        'Wikipedia' => array(
            'activate' => false,
            'route' => 'api/wikipedia/search',
            'options' => array(
                'database' => array(
                    'dbname' => 'itag',
                    /*
                     * Database host - if not specified connect through socket instead of TCP/IP
                     */
                    //'host' => 'localhost',
                    'user' => 'itag',
                    'password' => 'itag'
                )
            )
        ),
        
        /*
         * iTag module - automatically tag posted feature 
         * 
         * !!! Require iTag !!!
         */
        'Tag' => array(
            'activate' => true,
            'route' => 'api/tag',
            'options' => array(
                'database' => array(
                    'dbname' => 'itag',
                    /*
                     * Database host - if not specified connect through socket instead of TCP/IP
                     */
                    'host' => 'localhost',
                    'user' => 'itag',
                    'password' => 'itag'
                ),
                'taggers' => array(
                    'Political' => array(),
                    'LandCover' => array()
                ),
                /*
                 * iTag doesn't compute land cover keywords if footprint area is greater than "areaLimit" (square kilometers)
                 */ 
                'areaLimit' => 3000000
            )
        )
        
    )
);
