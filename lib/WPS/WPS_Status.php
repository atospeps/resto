<?php
/**
 *
 * WPS:WPS_Response
 * @author Driss El maalem
 *
 */
class WPS_Status {
    
    
   public function __construct(){
       
   }
    
    /*
     * WPS status events.
     */
    public static $statusEvents = array (
            'ProcessAccepted',
            'ProcessSucceeded',
            'ProcessFailed',
            'ProcessStarted',
            'ProcessPaused'
    );
    
    
}