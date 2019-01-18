<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . '../../../lib/celery-php/vendor/autoload.php';

/**
 * @author Atos
 * RESTo Upload module.
 *    upload/area                                                       |  Upload a SHP, KML or GeoJSON file
 *
 *    | 
 *    | Resource                                                        | Description
 *    |_________________________________________________________________|______________________________________
 *    | HTTP/GET        wps/users/{userid}/jobs                         | List of all user's jobs
 *    | HTTP/GET        wps/status                                      | Check VIZO status
 *    
 *    
 */
class Upload extends RestoModule {
    /*
     * Resto context
     */
    public $context;
    
    /*
     * Current user (only set for administration on a single user)
     */
    public $user = null;
    
    const TASK_NAME = 'tasks.process';
    
    /*
     * 
     */
    private $client;
    
    /*
     * File extensions allowed
     */
    private $extensions = array('geojson', 'json', 'zip', 'kml');
    
    /*
     * Upload max file size
     */
    private $maxFileSize = 1048576; // 1Mo
    
    /*
     * Enable antivirus
     */
    private $antivirusEnabled = true;
    
    /*
     * Default working directory
     */
    private $workingDirectory = '/var/run/peps-geoprocessor/resto_uploads';
    
    /*
     * Message broker configuration
     */
    private $brokerCfg = array(
        'server' => 'localhost',
        'login' => '',
        'password' => '',
        'vhost' => '0',
        'exchange' => 'celery',
        'binding' => 'celery',
        'port' => 6379,
        'connector' => 'redis'
    );
    
    /*
     * Antivirus
     * geometry empty
     * geometry complexe
     * geometry trop de points
     * geometry trop grande surface
     * erreur de lecture des fichiers geo
     * fichier geo invalides
     * 
     * 
     */
    
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
        if (isset($this->context->dbDriver)) {
            $this->context->dbDriver->closeDbh();
        }
        $this->initialize();
    }

    /**
     * Initializes Upload module.
     */
    private function initialize(){
        $options = $this->context->modules[get_class($this)];
        if (isset($options['maxFileSize'])){
            $this->maxFileSize = $options['maxFileSize'];
        }
        if (isset($options['antivirusEnabled'])){
            $this->antivirusEnabled = $options['antivirusEnabled'];
        }
        
        if (isset($options['workingDirectory'])){
            $this->workingDirectory = $options['workingDirectory'];
        }

        /*
         * Intialize message broker settings
         */
        $properties = array('server', 'login', 'password', 'vhost', 'exchange', 'binding', 'port', 'connector');
        foreach ($properties as $property) {
            if (isset($options['broker'][$property])){
                $this->brokerCfg[$property] = $options['broker'][$property];
            }
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see RestoModule::run()
     */
    public function run($segments, $data = []) {        
        
        $method = $this->context->method;

        // Switch on HTTP methods
        switch ($method) {
            /*
             * HTTP/GET
             */
            case HttpRequestMethod::GET:
                $this->initializeMessageBroker();
                return $this->process_GET($segments);
            /*
             * HTTP/POST
             */
            case HttpRequestMethod::POST:
                $this->initializeMessageBroker();
                return $this->process_POST($segments, $data);
            default :
                RestoLogUtil::httpError(404);
        }
    }

    /**
     * Initialize celery connection
     */
    private function initializeMessageBroker(){
        try {
            $this->client = new \Celery\Celery(
                $this->brokerCfg['server'],
                $this->brokerCfg['login'],
                $this->brokerCfg['password'],
                $this->brokerCfg['vhost'],
                $this->brokerCfg['exchange'],
                $this->brokerCfg['binding'],
                $this->brokerCfg['port'],
                $this->brokerCfg['connector']
                );
        } catch (Exception $e)
        {
            RestoLogUtil::httpError(500, RestoLogUtil::$codes[9000]);
        }
    }
    
    /**
     * 
     * @param array $segments path
     * @return unknown|GeoJSON|NULL
     */
    private function process_GET($segments) {
        if (!isset($segments[0]))
        {
            RestoLogUtil::httpError(404);
        }
        $taskId = $segments[0];
        if (!RestoUtil::isValidUUID($taskId))
        {
            RestoLogUtil::httpError(400);
        }
        $removeMessageFromQueue = true;
        $res = $this->client->getAsyncResultMessage(self::TASK_NAME, $taskId, null, $removeMessageFromQueue);

        if ($res !== false) {
            $res = $res['complete_result'];
            $status = $res['status'];
            
            if ($status === 'ERROR') {
                RestoLogUtil::httpError(500);
            }
            if ($status === 'SUCCESS')
            {
                $code = $res['result']['code'];
                if ($code === 200)
                {
                    $this->context->outputFormat = 'json';
                    return new GeoJSON($res['result']['data']);
                }
                return RestoLogUtil::error(
                    isset(RestoLogUtil::$codes[$code]) ? RestoLogUtil::$codes[$code] : 'Unknown error', 
                    array('code' => $code));
            }
        }        
        header('HTTP/1.1 202 Accepted');
        header('Retry-After: 5');
        return null;
    }
        
    /**
     * 
     * @return NULL[]|unknown
     */
    private function process_POST() {
        
        // upload file
        $options = array('extensions' => $this->extensions, 'max_file_size' => $this->maxFileSize);
        $file = RestoUtil::uploadFile($this->workingDirectory, $options);
        
        $ext = $file['extension'];
        
        switch ($ext) {
            case 'json':
            case 'geojson':
            case 'kml':
            case 'zip':
                return $this->PostTask(self::TASK_NAME, array($file['path'], $this->antivirusEnabled));
            default:
                unlink($file['path']);
                return RestoLogUtil::httpError(400, 9001);
        }
    }

    /**
     * 
     * @param unknown $task
     * @param unknown $args
     * @return NULL[]|unknown
     */
    private function PostTask($task, $args){
        try
        {
            $task = $this->client->PostTask($task, $args, true, "celery", array('id' => self::UUIDv4()));
            return $this->answer($task);
        } catch (Exception $e)
        {
            unlink($args[0]);
            return RestoLogUtil::httpError(500, 'Cannot upload file(s) - An unexpected error occurred. Please retry again more later.');
        }
    }
    
    /**
     * 
     * @param unknown $task
     */
    private function answer($task) {
        return array(
            'taskid' => $task->getId()
        );
    }

    /*
     * 
     * http://php.net/manual/fr/function.uniqid.php#94959
     */
    public static function UUIDv4() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            
            // 32 bits for "time_low"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            
            // 16 bits for "time_mid"
            mt_rand(0, 0xffff),
            
            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000,
            
            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000,
            
            // 48 bits for "node"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );
    }

}

class GeoJSON {
    private $json;
    
    public function __construct($json){
        $this->json = $json;
    }
    
    public function toJSON(){
        return $this->json;
    }
}


