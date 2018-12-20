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
     * 
     */
    private $fileSizeLimit = 1024;

    /*
     * 
     */
    private $areaLimit = 1000;    
    
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
        
        $this->client = new \Celery\Celery(
            'localhost', /* Server */
            '', /* Login */
            '', /* Password */
            '0', /* vhost */
            'celery', /* exchange */
            'celery', /* binding */
            6379, /* port */
            'redis' /* connector */
            );

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
                return $this->process_GET($segments);
            /*
             * HTTP/POST
             */
            case HttpRequestMethod::POST:
                return $this->process_POST($segments, $data);
            default :
                RestoLogUtil::httpError(404);
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
            return RestoLogUtil::httpError(404);
        }
        $taskId = $segments[0];
        if (!RestoUtil::isValidUUID($taskId))
        {
            return RestoLogUtil::httpError(400);
        }
        $removeMessageFromQueue = false;
        $res = $this->client->getAsyncResultMessage(self::TASK_NAME, $taskId, null, $removeMessageFromQueue);

        if ($res === false){
            return RestoLogUtil::httpError(400);
        }
        $res = $res['complete_result'];
        $status = $res['status'];
        if ($status === 'ERROR') {
            return RestoLogUtil::httpError(500);
        }
        if ($status === 'SUCCESS') 
        {
            $code = $res['result']['code'];
            if ($code === 200)
            {
                $this->context->outputFormat = 'json';
                return new GeoJSON($res['result']['data']);
            }
            return RestoLogUtil::error('Cannot process successfully shape file.', array('code' => $code));
        }
        header('HTTP/1.1 202 You should retry the request');
        return null;
    }
        
    /**
     * 
     * @return NULL[]|unknown
     */
    private function process_POST() {
        
        // upload file
        $options = array('extensions' => $this->extensions);
        $file = RestoUtil::uploadFile($this->context->uploadDirectory, $options);

        $ext = $file['extension'];
        switch ($ext) {
            case 'json':
            case 'geojson':
            case 'kml':
            case 'zip':
                return $this->PostTask(self::TASK_NAME, array($file['path']));
            default:
                unlink($file['path']);
                return RestoLogUtil::httpError(400, 'Cannot upload file(s) - Extension \'' . $ext . '\'not allowed, please choose a valid file.');
        }
    }

    /**
     * 
     * @param unknown $task
     * @param unknown $args
     * @return NULL[]|unknown
     */
    private function postTask($task, $args){
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
            'taskId' => $task->getId()
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


