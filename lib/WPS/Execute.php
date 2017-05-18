<?php

class Execute {

    public static function Get($url, $data, $processes_enabled, $options){
        /*
         * Filter
         */
        if (isset($data['identifier'])) {
            $identifiers = array();
            $processes = explode(',', $data['identifier']);
            
            $full_wps_rights = in_array('all', $processes_enabled);
            if ($full_wps_rights == false){
                foreach ($processes as $key => $identifier){
                    if ($identifier == 'all') {
                        unset ($processes[$key]);
                        $processes = array_merge($processes, $processes_enabled);
                        break;
                    }
                }
                foreach ($processes as $key => $identifier) {
                    if (in_array($identifier, $processes_enabled)) {
                        $identifiers[] = $identifier;
                    }
                }
                $data['identifier'] = implode(',', $identifiers);
            }
        }

        /**
         * Forward
         */
        return Curl::Get($url, $data, $options);
    }
    
    public static function Post($url, $data, $processes_enabled, $options){
        return Curl::Post($url, $data, $options);
    }
}