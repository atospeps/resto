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

/*
 * 
 */
class RestoContext {
    
    /**
     * Base url
     */
    public $baseUrl = '//localhost/';
    
    /**
     * Database driver
     */
    public $dbDriver;
    
    /**
     * Dictionary
     */
    public $dictionary;

    /*
     * Available languages
     */
    public $languages = array('en');
    
    /**
     * HTTP method
     */
    public $method = 'GET';
    
    /**
     * Mail configuration
     */
    public $mail = array();
    
    /**
     * Available modules list/configuration
     */
    public $modules = array();
    
    /**
     * Format
     */
    public $outputFormat = 'json';
    
    /**
     * Path
     */
    public $path = '';
    
    /**
     * Query
     */
    public $query = array();
    
    /**
     * Reset password page url
     */
    public $resetPasswordUrl = 'http://localhost/resto-client/#/resetPassword';
    
    /**
     * Store query
     */
    public $storeQuery = false;
   
    /*
     * Server timezone
     */
    public $timezone = 'Europe/Paris';
    
    /*
     * Application name
     */
    public $title = 'resto';
    
    /*
     * Upload directory
     */
    public $uploadDirectory = '/tmp/resto_uploads';
    
    /*
     * Stream method 
     */
    public $streamMethod = 'php';
    
    /*
     * Instant download limit (nb max)
     */
    public $instantDownloadLimit = 0;
    
    /*
     * Weekly download limit (nb max)
     */
    public $weeklyDownloadLimit = 200;
    
    /*
     * 
     * Default value : 900 0000 milliseconds (15 minutes)
     */
    public $hpssRetryAfter = 900000;
    
    /*
     *
     * Default value: 30 seconds
     */
    public $hpssTimeout = 30;

    /*
     * 
     */
    public $hpssRestApi;

    /*
     *  JSON Web Token passphrase
     * (see https://tools.ietf.org/html/draft-ietf-oauth-json-web-token-32)
     */
    private $passphrase;

    /*
     * Shared links validity duration (in seconds)
     * Default is 1 day (i.e. 86400 seconds)
     */
    public $sharedLinkDuration = 86400;
    
    /*
     * Maximum number of products that the user can add in the cart (0 = no limit)
     */
    public $cartMaxProducts = 0;
    
    /*
     * Maximum number of products that the user can add in the processing cart (0 = no limit)
     */
    public $processingCartMaxProducts = 0;
    
    /*
     * obsolescenceS1useDhusIngestDate
     */
    public $obsolescenceS1useDhusIngestDate = false;
    
    /*
     * JSON Web Token duration (in seconds)
     */
    private $tokenDuration = 3600;
    
    /*
     * JSON Web Token duration for administration (in seconds)
     */
    private $tokenAdministrationDuration = 86400;
    
    /*
     * JSON Web Token accepted encryption algorithms
     */
    private $tokenEncryptions = array('HS256');

    
    /**
     * Constructor
     * 
     * @param array $config : configuration extracted from config.php file
     * @throws Exception
     */
    public function __construct($config) {
        /*
         * JSON Web Token is mandatory
         */
        if (!isset($config['general']['passphrase'])) {
            RestoLogUtil::httpError(4000);
        }
        
        /*
         * Set variables
         */
        $this->configure($config);
        
        /*
         * Initialize objects
         */
        $this->initialize($config);
        
    }
    
    /**
     * Return complete url
     * 
     * @param boolean $withparams : true to return url with parameters (i.e. with ?key=value&...) / false otherwise
     */
    public function getUrl($withparams = true) {
        return $this->baseUrl . '/' . $this->path . '.' . $this->outputFormat . (isset($withparams) ? RestoUtil::kvpsToQueryString($this->query) : '');
    }
    
    /**
     * Create a Json Web Token
     * 
     * @param string $identifier
     * @param json $jsonData
     * @return string
     */
    public function createToken($identifier, $jsonData, $isadmin = false) {
        $expiration = time();
        $expiration += $isadmin ? $this->tokenAdministrationDuration : $this->tokenDuration;

        return JWT::encode(array(
            'iss' => 'resto:server',
            'sub' => $identifier,
            'iat' => time(),
            'exp' => $expiration,
            'data' => $jsonData
        ), $this->passphrase);
    }
    
    /**
     * Decode and verify signed JSON Web Token
     * 
     * @param string $token
     * @return array
     */
    public function decodeJWT($token) {
        return JWT::decode($token, $this->passphrase, $this->tokenEncryptions);
    }
    
    /**
     * Initialize context variable
     * 
     * @param array $config configuration
     */
    private function initialize($config) {
        
        /*
         * Initialize path
         */
        $this->setPath();
        
        /*
         * Initialize output format
         */
        $this->setOutputFormat();
        
        /*
         * Initialize database driver
         */
        $this->setDbDriver($config['database']);
        
        /*
         * Initialize dictionary
         */
        $this->setDictionary();
        
        /*
         * Initialize server endpoint url
         */
        $this->setBaseURL($config['general']['rootEndpoint'], $config['general']['protocol']);
        
        /*
         * Initialize query array
         */
        $this->setQuery();
        
    }
    
    /**
     * Set configuration variables
     * 
     * @param array $config configuration
     */
    private function configure($config) {
        
        /*
         * Set TimeZone
         */
        date_default_timezone_set(isset($config['general']['timezone']) ? $config['general']['timezone'] : 'Europe/Paris');

        /*
         * HTTP Method is one of GET, POST, PUT or DELETE
         */
        $this->method = strtoupper(filter_input(INPUT_SERVER, 'REQUEST_METHOD', FILTER_SANITIZE_STRING));
        
        /*
         * True to store queries within database
         */
        if (isset($config['general']['storeQuery'])) {
            $this->storeQuery = $config['general']['storeQuery'];
        }
                
        /*
         * Passphrase for JSON Web Token signing/veryfying
         */
        $this->passphrase = $config['general']['passphrase'];
        
        /*
         * JSON Web Token accepted encryption algorithms
         */
        $this->tokenEncryptions = $config['general']['tokenEncryptions'];
        
        /*
         * Shared links validity duration
         */
        if (isset($config['general']['sharedLinkDuration'])) {
            $this->sharedLinkDuration = $config['general']['sharedLinkDuration'];
        }
        
        /*
         * Maximum products in cart
         */
        if (isset($config['general']['cartMaxProducts'])) {
            $this->cartMaxProducts = $config['general']['cartMaxProducts'];
        }
        
        /*
         * Maximum products in processing cart
         */
        if (isset($config['general']['processingCartMaxProducts'])) {
            $this->processingCartMaxProducts = $config['general']['processingCartMaxProducts'];
        }
        
        /*
         * JSON Web Token duration
         */
        if (isset($config['general']['tokenDuration'])) {
            $this->tokenDuration = $config['general']['tokenDuration'];
        }

        /*
         * JSON Web Token administration duration
         */
        if (isset($config['general']['tokenAdministrationDuration'])) {
            $this->tokenAdministrationDuration = $config['general']['tokenAdministrationDuration'];
        }
        
        /*
         * Available languages
         */
        if (isset($config['general']['languages'])) {
            $this->languages = $config['general']['languages'];
        }
        
        /*
         * Mail configuration
         */
        if (isset($config['mail'])) {
            $this->mail = $config['mail'];
        }
      
        /*
         * Contact mail
         */
        if (isset($config['general']['contactEmail'])) {
            $this->contactEmail = $config['general']['contactEmail'];
        }
        
        /*
         * reCaptcha secret key
         */
        if (isset($config['reCaptcha'])) {
            $this->reCaptcha = $config['reCaptcha'];
        }
        
        /*
         * Reset password url
         */
        if (isset($config['general']['resetPasswordUrl'])) {
            $this->resetPasswordUrl = $config['general']['resetPasswordUrl'];
        }
        
        /*
         * Title
         */
        if (isset($config['general']['title'])) {
            $this->title = $config['general']['title'];
        }
        
        /*
         * Upload directory
         */
        if (isset($config['general']['uploadDirectory'])) {
            $this->uploadDirectory = $config['general']['uploadDirectory'];
        }
        
        /*
         * Stream method
         */
        if (isset($config['general']['streamMethod'])) {
            $this->streamMethod = $config['general']['streamMethod'];
        }

        /*
         * Instant download limit
         */
        if (isset($config['general']['instantLimitDownload'])) {
            $this->instantDownloadLimit = $config['general']['instantLimitDownload'];
        }

        /*
         * Weekly download limit
         */
        if (isset($config['general']['weeklyLimitDownload'])) {
            $this->weeklyDownloadLimit = $config['general']['weeklyLimitDownload'];
        }
        
        /*
         * Tape data management : HTTP Header Retry-After
         */
        if (isset($config['general']['hpss']['retryAfter'])) {
            $this->hpssRetryAfter = $config['general']['hpss']['retryAfter'];
        }

        /*
         * Tape data management : Timeout on file access
         */
        if (isset($config['general']['hpss']['timeout'])) {
            $this->hpssTimeout = $config['general']['hpss']['timeout'];
        }

        /*
         * HPSS : REST Api services
         */
        if (isset($config['general']['hpss']['restapi'])) {
            $this->hpssRestApi = $config['general']['hpss']['restapi'];
        }
        
        /*
         * S1 obsolescence
         */
        if (isset($config['general']['obsolescenceS1useDhusIngestDate'])) {
            $this->obsolescenceS1useDhusIngestDate = $config['general']['obsolescenceS1useDhusIngestDate'];
        }
        
        /*
         * Initialize modules
         */
        $this->setModules($config['modules']);
    }
    
    /**
     * Get url with no parameters
     * Note that trailing '/' is systematically removed
     * 
     * @param string $endPoint
     * @param string $protocol
     * 
     * @return string $endPoint
     */
    private function setBaseURL($endPoint, $protocol) {
        if ($protocol === 'auto') {
            $https = filter_input(INPUT_SERVER, 'HTTPS', FILTER_SANITIZE_STRING);
            $protocol = isset($https) && $https === 'on' ? 'https' : 'http';
        }
        $host = filter_input(INPUT_SERVER, 'HTTP_HOST', FILTER_SANITIZE_STRING);
        $this->baseUrl = $protocol . '://' . $host . (substr($endPoint, -1) === '/' ? substr($endPoint, 0, strlen($endPoint) - 1) : $endPoint);
    }
    
    /**
     * Set query parameters from input
     */
    private function setQuery() {
        
        /*
         * Aggregate input parameters
         * 
         * Note: PUT is handled by RestoUtil::readInputData() function
         */
        $query = array();
        switch ($this->method) {
            case 'HEAD':
            case 'GET':
            case 'DELETE':
            case 'POST':
                $query = RestoUtil::sanitize($_GET);
                break;
            default:
                break;
        }
        
        /*
         * Remove unwanted parameters
         */
        if (isset($query['RESToURL'])) {
            unset($query['RESToURL']);
        }
        
        /*
         * Trim all values
         */
        if (!function_exists('trim_value')) {
            function trim_value(&$value) {
                $value = trim($value);
            }
        }
        array_walk_recursive($query, 'trim_value');
        
        $this->query = $query;
        
    }
    
    /**
     * Set dictionary from input language - default is english
     */
    private function setDictionary() {
        
        $lang = filter_input(INPUT_GET, 'lang', FILTER_SANITIZE_STRING);
        
        if (!isset($lang) || !in_array($lang, $this->languages) || !class_exists('RestoDictionary_' . $lang)) {
            $lang = 'en';
        }
        
        $this->dictionary = RestoUtil::instantiate('RestoDictionary_' . $lang, array($this->dbDriver));
        
    }
    
    /**
     * Set Database driver
     * 
     * @param array $databaseConfig
     */
    private function setDbDriver($databaseConfig) {
        
        /*
         * Database
         */
        if (!class_exists('RestoDatabaseDriver_' . $databaseConfig['driver'])) {
            RestoLogUtil::httpError(4002);
        }
        try {
            $databaseClass = new ReflectionClass('RestoDatabaseDriver_' . $databaseConfig['driver']);
            if (!$databaseClass->isInstantiable()) {
                throw new Exception();
            }
        } catch (Exception $e) {
            RestoLogUtil::httpError(4003);
        }   
        
        $this->dbDriver = $databaseClass->newInstance($databaseConfig, new RestoCache(isset($databaseConfig['dircache']) ? $databaseConfig['dircache'] : null));      
    }
    
    /**
     * Set REST path
     */
    private function setPath() {
        $restoUrl = filter_input(INPUT_GET, 'RESToURL', FILTER_SANITIZE_STRING);
        if (isset($restoUrl)) {
            $this->path = substr($restoUrl, -1) === '/' ? substr($restoUrl, 0, strlen($restoUrl) - 1) : $restoUrl;
        }
    }

    /**
     * Set output format from suffix or HTTP_ACCEPT
     */
    private function setOutputFormat() {
        
        $this->outputFormat = $this->getPathSuffix();
        
        /*
         * Extract outputFormat from HTTP_ACCEPT 
         */
        if (!isset($this->outputFormat)) {
            $httpAccept = filter_input(INPUT_SERVER, 'HTTP_ACCEPT', FILTER_SANITIZE_STRING);
            $acceptedFormats = explode(',', strtolower(str_replace(' ', '', $httpAccept)));
            foreach ($acceptedFormats as $format) {
                $weight = 1;
                if (strpos($format, ';q=')) {
                    list($format, $weight) = explode(';q=', $format);
                }
                $AcceptTypes[$format] = $weight;
            }
            foreach (RestoUtil::$contentTypes as $key => $value) {
                if (isset($AcceptTypes[$value]) && $AcceptTypes[$value] !== 0) {
                    $this->outputFormat = $key;
                    break;
                }
            }
            
            if (!isset($this->outputFormat)) {
                $this->outputFormat = Resto::DEFAULT_GET_OUTPUT_FORMAT;
            }
        }
        
    }
    
    /**
     * Return suffix from input url
     * @return string
     */
    private function getPathSuffix() {
        
        $splitted = explode('.', $this->path);
        $size = count($splitted);
        if ($size > 1) {
            if (array_key_exists($splitted[$size - 1], RestoUtil::$contentTypes)) {
                $suffix = $splitted[$size - 1];
                array_pop($splitted);
                $this->path = join('.', $splitted);
                return $suffix;
            }
            else {
                /*
                 * TODO
                 * 
                 */
                if (isset($this->modules['WPS']['route'])){
                    $url = filter_input(INPUT_GET, 'RESToURL', FILTER_SANITIZE_STRING);
                    if (strpos($url, $this->modules['WPS']['route']) === 0){
                        $suffix = $splitted[$size - 1];
                        array_pop($splitted);
                        $this->path = join('.', $splitted);
                        return $suffix;
                    }
                }
                RestoLogUtil::httpError(404);
            }
        }
        
        return null;
    }
    
    /**
     * Set activated modules
     * 
     * @param array $modulesConfig
     * 
     */
    private function setModules($modulesConfig) {
        
        $modules = array();
        
        foreach (array_keys($modulesConfig) as $moduleName) {
            
            /*
             * Only activated module are registered
             */
            if (isset($modulesConfig[$moduleName]['activate']) && $modulesConfig[$moduleName]['activate'] === true && class_exists($moduleName)) {
                
                $modules[$moduleName] = isset($modulesConfig[$moduleName]['options']) ? $modulesConfig[$moduleName]['options'] : array();
                
                /*
                 * Add route to module
                 */
                if (isset($modulesConfig[$moduleName]['route'])) {
                    $modules[$moduleName] = array_merge($modules[$moduleName], array('route' => $modulesConfig[$moduleName]['route']));
                }
                
            }
            
        }
        
        $this->modules = $modules;
        
    }
    
}

if(!function_exists('mime_content_type')) {

    function mime_content_type($filename) {

        $mime_types = array(

                'txt' => 'text/plain',
                'htm' => 'text/html',
                'html' => 'text/html',
                'php' => 'text/html',
                'css' => 'text/css',
                'js' => 'application/javascript',
                'json' => 'application/json',
                'xml' => 'application/xml',
                'swf' => 'application/x-shockwave-flash',
                'flv' => 'video/x-flv',

                // images
                'png' => 'image/png',
                'jpe' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'jpg' => 'image/jpeg',
                'gif' => 'image/gif',
                'bmp' => 'image/bmp',
                'ico' => 'image/vnd.microsoft.icon',
                'tiff' => 'image/tiff',
                'tif' => 'image/tiff',
                'svg' => 'image/svg+xml',
                'svgz' => 'image/svg+xml',

                // archives
                'zip' => 'application/zip',
                'rar' => 'application/x-rar-compressed',
                'exe' => 'application/x-msdownload',
                'msi' => 'application/x-msdownload',
                'cab' => 'application/vnd.ms-cab-compressed',

                // audio/video
                'mp3' => 'audio/mpeg',
                'qt' => 'video/quicktime',
                'mov' => 'video/quicktime',

                // adobe
                'pdf' => 'application/pdf',
                'psd' => 'image/vnd.adobe.photoshop',
                'ai' => 'application/postscript',
                'eps' => 'application/postscript',
                'ps' => 'application/postscript',

                // ms office
                'doc' => 'application/msword',
                'rtf' => 'application/rtf',
                'xls' => 'application/vnd.ms-excel',
                'ppt' => 'application/vnd.ms-powerpoint',

                // open office
                'odt' => 'application/vnd.oasis.opendocument.text',
                'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        );

        $ext = strtolower(array_pop(explode('.',$filename)));
        if (array_key_exists($ext, $mime_types)) {
            return $mime_types[$ext];
        }
        elseif (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME);
            $mimetype = finfo_file($finfo, $filename);
            finfo_close($finfo);
            return $mimetype;
        }
        else {
            return 'application/octet-stream';
        }
    }
}
