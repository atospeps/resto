<?php
/*
 * Full description of a WPS process.
 */
class WPS_Process {
    /*
     * WPS Process identifier.
     */
    private $identifier;
    
    /*
     * WPS Process title.
     */
    private $title;
    
    /*
     * WPS Process description.
     * 
     */
    private $description;
    
    /*
     * WPS Process data inputs.
     */
    private $dataInputs = array();
    
    /*
     * WPS Process outputs.
     */
    private $processOutputs = array();
    
    /*
     * ? Store supported.
     * Indicates if the execute response document shall be stored. 
     * If "true" then the executeResponseLocation attribute in the execute response becomes mandatory, which will point to the 
     * location where the executeResponseDocument is stored. The service shall respond immediately to the request and return an 
     * executeResponseDocument containing the executeResponseLocation and the status element which has five possible 
     * subelements (choice):ProcessAccepted, ProcessStarted, ProcessPaused, ProcessFailed and ProcessSucceeded, 
     * which are chosen and populated as follows: 1) If the process is completed when the initial executeResponseDocument is returned, 
     * the element ProcessSucceeded is populated with the process results. 2) If the process already failed when the initial 
     * executeResponseDocument is returned, the element ProcessFailed is populated with the Exception. 3) 
     * If the process has been paused when the initial executeResponseDocument is returned, the element ProcessPaused is populated. 4) 
     * If the process has been accepted when the initial executeResponseDocument is returned, the element ProcessAccepted is populated, 
     * including percentage information. 5) If the process execution is ongoing when the initial executeResponseDocument is returned, 
     * the element ProcessStarted is populated. In case 3, 4, and 5, if status updating is requested, updates are made to the 
     * executeResponseDocument at the executeResponseLocation until either the process completes successfully or fails. Regardless, 
     * once the process completes successfully, the ProcessSucceeded element is populated, and if it fails, 
     * the ProcessFailed element is populated.
     */
    private $storeSupported = false;
    
    /*
     * ? Status supported.
     * Indicates if the stored execute response document shall be updated to provide ongoing reports on the status of execution. 
     * If status is "true" and storeExecuteResponse is "true" (and the server has indicated that both storeSupported and 
     * statusSupported are "true") then the Status element of the execute response document stored at executeResponseLocation 
     * is kept up to date by the process. While the execute response contains ProcessAccepted, ProcessStarted, or ProcessPaused, 
     * updates shall be made to the executeResponse document until either the process completes successfully (in which case ProcessSucceeded 
     * is populated), or the process fails (in which case ProcessFailed is populated). If status is "false" then the Status element shall not 
     * be updated until the process either completes successfully or fails. If status="true" and storeExecuteResponse is "false" then the 
     * service shall raise an exception.
     */
    private $statusSupported = false;
    
    /*
     * Indicates if the Execute operation response shall include the DataInputs and OutputDefinitions elements. 
     * If lineage is "true" the server shall include in the execute response a complete copy of the DataInputs 
     * and OutputDefinition elements as received in the execute request. 
     * If lineage is "false" then these elements shall be omitted from the response.
     */
    private $lineage = false;
    
    public function __construct() {
        
    }

    /**
     * 
     */
    public function getIdentifier(){
        return $this->identifier;
    }
    
    /**
     * 
     * @param unknown $pIdentifier
     */
    public function setIdentifier($pIdentifier){
        $this->identifier = $pIdentifier;
    }
    
    /**
     * 
     */
    public function getTitle(){
        return $this->title;
    }
    
    /**
     * 
     * @param unknown $pTitle
     */
    public function setTitle($pTitle){
        $this->title = $pTitle;
    }
    
    /**
     * 
     */
    public function getDescription(){
        return $this->description;
    }
    
    /**
     * 
     * @param unknown $pDescription
     */
    public function setDescription($pDescription){
        $this->description = $pDescription;
    }
    
    /**
     * 
     */
    public function getStoreSupported(){
        return $this->storeSupported;
    }
    
    /**
     * 
     * @param unknown $pStoreSupported
     */
    public function setStoreSupported($pStoreSupported){
        $this->storeSupported = $pStoreSupported;
    }
    
    /**
     * 
     */
    public function getStatusSupported(){
        return $this->statuSupported;
    }
    
    /**
     * 
     * @param unknown $pStatusSupported
     */
    public function setStatusSupported($pStatusSupported){
        $this->statusSupported = $pStatusSupported;
    }

    /**
     * 
     */
    public function getInputs(){
        return $this->dataInputs;
    }
    
    /**
     * 
     * @param unknown $input
     */
    public function addInput($input) {
        $this->dataInputs[] = $input;
    }
    
    /**
     * 
     * @return multitype:boolean NULL string
     */
    public function toArray() {
        return array(
                'identifier' => $this->identifier,
                'storeSupported' => !!$this->storeSupported,
                'statusSupported' => !!$this->statusSupported,
                'description' => $this->description,
                //'inputs' => $this->inputs
                'inputs' => array('identifierKey' => count($this->dataInputs) > 0 ? $this->dataInputs[0] :  '')
        );
    }
}