<?php
class WSDL {

    const PATTERN_WSDL_OPERATION = '/^ExecuteProcess(Async)?_(((?!(Request)|(Response)).*)*)$/';
    const PATTERN_WSDL_MESSAGE = '/^ExecuteProcess(Async)?_(((?!Response|Request).)+)(Response|Request)?$/';

    /**
     *
     * TODO
     *
     * @param unknown $url
     * @param unknown $data
     * @param unknown $processes_enabled
     * @param unknown $options
     */
    public static function Get($url, $data, $processes_enabled, $options) {

        $data = array();
        $url = $url . (substr($url, -1) == '?' ? '' : '?') . WPS_RequestManager::WSDL;

        $full_wps_rights = in_array('all', $processes_enabled);
        $response = Curl::Get($url, $data, $options);

        if ($full_wps_rights == false) 
        {

            $dom = new DOMDocument;
            // pretty print options
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = true;
        
            $dom->loadXML($response);
        
            $itemsToRemove = array();
            
            // Removes operations from xml response
            $operations = $dom->getElementsByTagNameNS('http://schemas.xmlsoap.org/wsdl/', 'operation');

            if ($operations && $operations->length > 0) 
            {
                foreach ($operations as $operation) 
                {                    
                    $name = $operation->getAttribute('name');
                    
                    if (preg_match(self::PATTERN_WSDL_OPERATION, $name, $matches) 
                            && count($matches) === 4) 
                    {
                        $identifier = $matches[2];
                        if (!in_array($identifier, $processes_enabled)) 
                        {
                            $itemsToRemove[] = $operation;
                        }
                    }
                    else 
                    {
                        $itemsToRemove[] = $operation;
                    }
                }
            }
            
            // Removes message from xml response
            $messages = $dom->getElementsByTagNameNS('http://schemas.xmlsoap.org/wsdl/', 'message');
            
            if ($messages && $messages->length > 0)
            {
                foreach ($messages as $message)
                {
                    $name = $message->getAttribute('name');
            
                    if (preg_match(self::PATTERN_WSDL_MESSAGE, $name, $matches)
                            && (count($matches) === 5 || count($matches) === 4))
                    {
                        $identifier = $matches[2];
                        if (!in_array($identifier, $processes_enabled))
                        {
                            $itemsToRemove[] = $message;
                        }
                    }
                    else
                    {
                        // Not remove 'ExceptionResponse'
                        if ($name !== 'ExceptionResponse')
                        {
                            $itemsToRemove[] = $message;
                        }
                    }
                }
            }
            
            ////////////////////
            
            // Removes schemas from xml response
            $schemas = $dom->getElementsByTagNameNS('http://www.w3.org/2001/XMLSchema', 'schema');
            
            if ($schemas && $schemas->length > 0)
            {
                foreach ($schemas as $schema)
                {
                    $element = $schema->getElementsByTagName('element');
                    if ($element && $element->length > 0)
                    {
                        
                        $name = $element->item(0)->getAttribute('name');
                        error_log($name, 0);
                        if (preg_match(self::PATTERN_WSDL_MESSAGE, $name, $matches)
                                && (count($matches) === 5 || count($matches) === 4))
                        {
                            $identifier = $matches[2];
                            if (!in_array($identifier, $processes_enabled))
                            {
                                $itemsToRemove[] = $schema;
                            }
                        }
                        else
                        {
                            // Not remove 'ExceptionResponse'
                            if ($name !== 'ExceptionReport')
                            {
                                $itemsToRemove[] = $schema;
                            }
                        }
                    }            
                }
            }
            
            ///////////////////
            
            // updates xml response
            foreach($itemsToRemove as $item)
            {
                $item->parentNode->removeChild($item);
            }
            $response = $dom->saveXML();            
        }
        return $response;
        
    }
    
}