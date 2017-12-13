<?php
/**
 *
 * WPS:ExecuteResponse
 * Full description of DescribeProcess operation response.
 * @author delmaale
 *
 */
class WPS_DescribeProcessResponse extends WPS_Response {
    
    /*
     * Ordered list of one or more full Process descriptions, 
     * listed in the order in which they were requested in the DescribeProcess operation request.
     */
    private $processes = array();
    
    /**
     *
     * @param string $pXml
    */
    function __construct($pXml) { 

        $dom = new DOMDocument;
        $dom->loadXML($pXml);

        $processes = $dom->getElementsByTagName('ProcessDescription');

        if ($processes && $processes->length > 0)
        {
            foreach ($processes as $process) 
            {
                $this->processes[] = $this->parseProcessDescription($process);
            }
        }
    }

    /**
     * 
     * @param unknown $process
     * @throws ExecuteResponseException
     * @return multitype:string multitype:NULL
     */
    private function parseProcessDescription($processDescriptionDomNode) {
        
        $process = new WPS_Process();
        
        // Store supported ?
        $process->setStoreSupported($processDescriptionDomNode->getAttribute('storeSupported'));
        
        // Status supported ?
        $process->setStatusSupported($processDescriptionDomNode->getAttribute('statusSupported'));

        // Identifier
        $identifier = $processDescriptionDomNode->getElementsByTagNameNS('http://www.opengis.net/ows/1.1', 'Identifier');
        if ($identifier && $identifier->length > 0)
        {
            $process->setIdentifier($identifier->item(0)->textContent);
        }

        // Title
        $title = $processDescriptionDomNode->getElementsByTagNameNS('http://www.opengis.net/ows/1.1', 'Title');
        if ($title && $title->length > 0)
        {
            $process->setTitle($title->item(0)->textContent);
        }
        
        // Description
        $description = $processDescriptionDomNode->getElementsByTagNameNS('http://www.opengis.net/ows/1.1', 'Abstract');
        if ($description && $description->length > 0)
        {

            $item = $description->item(0);
            
            $descriptionText = $item->C14N();            
            $process->setDescription($descriptionText);
        }
        
        // Data inputs
        $dataInputs = $processDescriptionDomNode->getElementsByTagName('DataInputs');
        if ($dataInputs && $dataInputs->length > 0)
        {
            $_dataInputs = $dataInputs->item(0);
            $inputs = $_dataInputs->getElementsByTagName('Input');
            if ($inputs && $inputs->length > 0)
            {
                foreach ($inputs as $input) 
                {
                    $inputIdentifier = $input->getElementsByTagNameNS('http://www.opengis.net/ows/1.1', 'Identifier');
                    if ($inputIdentifier && $inputIdentifier->length > 0)
                    {
                        $process->addInput($inputIdentifier->item(0)->textContent);
                    }
                }
            }
        }
        return $process;
    }

    /**
     * 
     */
    public function getProcesses(){
        return $this->processes;
    }

}