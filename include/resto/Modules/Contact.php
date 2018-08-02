<?php

/**
 *
 * @author Atos
 * Contact Module
 *    
 *    contact |  Send contact form to contact email
 *
 */
class Contact extends RestoModule {
    
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
     * Module configuration
     */
    private $config;
    
    
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
        
        $this->initialize();
    }
    
    /**
     * Initialize module
     */
    private function initialize() {
        // Close database handler
        if (isset($this->context->dbDriver)){
            $this->context->dbDriver->closeDbh();
        }
        // Load options
        $this->config = $this->context->modules[get_class($this)];        
    }
    /**
     * 
     * {@inheritDoc}
     * @see RestoModule::run()
     */
    public function run($segments, $data = array()) {

        $this->segments = $segments;
        $method = $this->context->method;
        
        if ($method === 'POST')
        {
            return $this->processPOST($data);
        }
        return RestoLogUtil::httpError(404);
    }
    
    /**
     * 
     * @param array $data Posted data
     * @return mixed output execution status
     */
    private function processPOST($data) {
        // check email
        if (!isset($data['email'])) {
            RestoLogUtil::httpError(1101, 'Email is not set');
        }
        if (!RestoUtil::isValidEmail($data['email'])) {
            RestoLogUtil::httpError(1102, "Email is invalid");
        }
        
        if (!isset($this->config['verifyUrl'])) {
            RestoLogUtil::httpError(500, 'Contact module - configuration failed (verifyUrl)');
        }
        if (!isset($this->config['secret'])) {
            RestoLogUtil::httpError(500, "Contact module - configuration failed (secret)");
        }
        if (!isset($this->config['contactEmail']) || !RestoUtil::isValidEmail($this->config['contactEmail'])) {
            RestoLogUtil::httpError(500, "Contact module - configuration failed (contactEmail)");
        }

        // check reCaptcha
        $response = Curl::Post(
                $this->config['verifyUrl'],
                array(
                        'secret' => $this->config['secret'],
                        'response' => $data['response']
                ),
                isset($this->config['curlOpts']) ? $this->config['curlOpts'] : array()
                );
        $decode = json_decode($response, true);
        if ($decode === null) {
            RestoLogUtil::httpError(1104, "Contact module - Captcha checking failed");
        }
        if (!isset($decode['success']) || $decode['success'] !== true) {
            RestoLogUtil::httpError(1104, "Contact module - Captcha checking failed" . (isset($decode['error-codes']) && is_array($decode['error-codes'])) ? ': ' . implode(', ', $decode['error-codes']) : '');
        }
        
        // send
        $rn = "\r\n";
        $to = $this->config['contactEmail'];
        $subject = 'PEPS Contact: ' . $data['name'];
        $message = wordwrap(str_replace("\n", "\r\n", $data['message']), 70, "\r\n");
        $params = '-f' . $data['email'];
        $headers = 'From: ' . $data['email'] 
                    . $rn . 'Reply-To: ' . $to 
                    . $rn . 'X-Mailer: PHP/' . phpversion();
        if (mail($to, $subject, $message, $headers, $params) !== true) {
            RestoLogUtil::httpError(1103, 'Email can not be sent');
        }
        
        return RestoLogUtil::success('Email has been successfully sent');
    }
    
}