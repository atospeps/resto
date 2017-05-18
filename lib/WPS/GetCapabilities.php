<?php

class GetCapabilities {

    public static function Get($url, $data, $processes_enabled, $options){
        
        $response = Curl::Get($url, $data, $options);
        return $response;
    }

    public static function Post($url, $data, $processes_enabled, $options){
        // TODO
        return Curl::Post($url, $data, $options);
    }
    
}