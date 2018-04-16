 *    | HTTP/GET        wps/users/{userid}/results/{resultid}/download  | 
    private function initialize()
                 * HTTP/GET wps/users/{userid}/results/{resultid}/download
            if ($executeResponse->getIdentifier() === WPS_RequestManager::WPS_STATUS_SERVICE)
            {
                return $response;
            }
            
            $query = ($method === HttpRequestMethod::GET) 


            if ($response === false){
                null,
                array('value=' . $this->context->dbDriver->quote($resource)),
                true);
            return $this->wpsRequestManager->download($result[0]['value'], null, $result[0]['identifier']);
     *              HTTP/GET wps/users/{userid}/jobs/stats
     *              HTTP/GET wps/users/{userid}/jobs/{jobid}/logs
     *              
     *              HTTP/GET wps/users/{userid}/results
     *              HTTP/GET wps/users/{userid}/results/{resultid}/download
     *
     * 				HTTP/GET wps/users/{userid}/processings
     *
     *              HTTP/GET wps/processings/{identifier}/describe 
        
            // wps/users/{userid}/processings
            if ($segments[2] === 'processings') {
     * 
     *              HTTP/GET wps/users/{userid}/jobs
     *              HTTP/GET wps/users/{userid}/jobs/stats
     *              HTTP/GET wps/users/{userid}/jobs/{jobid}
     *              HTTP/GET wps/users/{userid}/jobs/{jobid}/logs
     *              HTTP/GET wps/users/{userid}/jobs/{jobid}/download
     * 
     * @param unknown $segments
     * @return unknown|NULL
     */
    private function GET_users_jobs($userid, $segments){
        
        // wps/users/{userid}/jobs
        if (!isset($segments[3]))
        {
            $jobs = $this->GET_userWPSJobs($userid);
            return RestoLogUtil::success('WPS jobs for user ' . $userid, array ('data' => $jobs));
        }

        // wps/users/{userid}/jobs/stats
        if (!isset($segments[4]))
        {
            if ($segments[3] === 'stats') {
                $count = $this->getCompletedJobsStats($userid);
                return RestoLogUtil::success('WPS jobs stats for user ' . $userid, array ('data' => $count));
            }
        }
        else 
        {
            $jobid = $segments[3];
            $action = $segments[4];
            if (!ctype_digit($jobid)) {
                RestoLogUtil::httpError(400);
            }

            switch ($action) {
                // wps/users/{userid}/jobs/{jobid}/download
                case 'download':
                    // ? Is processings file result
                    $result = $this->getProcessingResults($userid, $jobid, null, null, true);
                    
                    if (count($result) > 0)
                    {
                        return $this->wpsRequestManager->download($result[0]['value'], null, $result[0]['identifier']);
                    }
                    break;
                // wps/users/{userid}/jobs/{jobid}/logs
                case 'logs':
                    $job = $this->getJobs($userid, $jobid, array(), false);
                    if (isset($job[0]['logs']))
                    {
                        $content = Curl::Get($job[0]['logs'], array(), $this->curlOpts);
                        if ($content)
                        {
                            return RestoLogUtil::success('WPS job  for user ' . $userid, array ('data' => $content));
                        }
                    }
                default:
                    break;
            }
        }
        return RestoLogUtil::httpError(404);
    }

    /**
     *              HTTP/GET wps/users/{userid}/results
     *              HTTP/GET wps/users/{userid}/results/{resultid}/download
     *              
     * @param unknown $segments
     * @return unknown|NULL
     */
    private function GET_users_results($userid, $segments){
        
        // users/{userid}/results
        if (!isset($segments[3]))
        {
            $results = $this->getProcessingResults($userid);
            return RestoLogUtil::success('WPS jobs results for user ' . $userid, array ('data' => $results));
        }

        // wps/users/{userid}/results/{resultid}/download
        if (isset($segments[4]) && $segments[4] === 'download') {
            
            $resultid = $segments[3];
            if (!ctype_digit($resultid)) {
                RestoLogUtil::httpError(400);
            }
            // ? Is processings file result
            $result = $this->getProcessingResults($userid, null, $resultid, null, true);
            
            if (count($result) > 0)
            {
                return $this->wpsRequestManager->download($result[0]['value'], null, $result[0]['identifier']);
            }
        }
        return RestoLogUtil::httpError(404);
    }
    
    
    
    /**
     * @param unknown $jobid
     * @param unknown $resultid
     * @param array $filters
     * @param string $rootPath
     * @return array|unknown
    private function getProcessingResults($userid, $jobid = null, $resultid =null, $filters= array(), $realValue = false) {
        // ? Result id is setted
        if (isset($resultid))
        {
            $filters[] = 'usermanagement.wps_results.uid=' . $this->context->dbDriver->quote($resultid);
        }
        
        $value = $realValue ? 'value' : $this->context->dbDriver->quote($this->externalServerAddress . '/users/' . $userid . '/results/') . ' || usermanagement.wps_results.uid || ' . $this->context->dbDriver->quote('/download');
        
                . $value . ' as value ';
        $removeType = $this->doesRemoveAlsoDeletesProcessingsFromDatabase 
        $wpsStatus = $proactiveStatus;
                $wpsStatus = 'ProcessAccepted';
                break;
                $wpsStatus = 'ProcessStarted';
                break;
                $wpsStatus = 'ProcessSucceeded';
                break;
                $wpsStatus = 'ProcessPaused';
                break;
                $wpsStatus = 'ProcessFailed';
                break;
                break;
        return $wpsStatus;
            return RestoLogUtil::success('WPS processing description for identifier ' . $segments[1], array ('data' => $description));
            $resultid = $data[$i];
                    $resultid);
                $item['id'] = $result[0]['identifier'];
 *    | HTTP/GET        wps/users/{userid}/results/{resultid}/download  | 
    private function initialize()
                 * HTTP/GET wps/users/{userid}/results/{resultid}/download
            if ($executeResponse->getIdentifier() === WPS_RequestManager::WPS_STATUS_SERVICE)
            {
                return $response;
            }
            
            $query = ($method === HttpRequestMethod::GET) 


            if ($response === false){
                null,
                array('value=' . $this->context->dbDriver->quote($resource)),
                true);
            return $this->wpsRequestManager->download($result[0]['value'], null, $result[0]['identifier']);
     *              HTTP/GET wps/users/{userid}/jobs/stats
     *              HTTP/GET wps/users/{userid}/jobs/{jobid}/logs
     *              
     *              HTTP/GET wps/users/{userid}/results
     *              HTTP/GET wps/users/{userid}/results/{resultid}/download
     *
     * 				HTTP/GET wps/users/{userid}/processings
     *
     *              HTTP/GET wps/processings/{identifier}/describe 
        
            // wps/users/{userid}/processings
            if ($segments[2] === 'processings') {
     * 
     *              HTTP/GET wps/users/{userid}/jobs
     *              HTTP/GET wps/users/{userid}/jobs/stats
     *              HTTP/GET wps/users/{userid}/jobs/{jobid}
     *              HTTP/GET wps/users/{userid}/jobs/{jobid}/logs
     *              HTTP/GET wps/users/{userid}/jobs/{jobid}/download
     * 
     * @param unknown $segments
     * @return unknown|NULL
     */
    private function GET_users_jobs($userid, $segments){
        
        // wps/users/{userid}/jobs
        if (!isset($segments[3]))
        {
            $jobs = $this->GET_userWPSJobs($userid);
            return RestoLogUtil::success('WPS jobs for user ' . $userid, array ('data' => $jobs));
        }

        // wps/users/{userid}/jobs/stats
        if (!isset($segments[4]))
        {
            if ($segments[3] === 'stats') {
                $count = $this->getCompletedJobsStats($userid);
                return RestoLogUtil::success('WPS jobs stats for user ' . $userid, array ('data' => $count));
            }
        }
        else 
        {
            $jobid = $segments[3];
            $action = $segments[4];
            if (!ctype_digit($jobid)) {
                RestoLogUtil::httpError(400);
            }

            switch ($action) {
                // wps/users/{userid}/jobs/{jobid}/download
                case 'download':
                    // ? Is processings file result
                    $result = $this->getProcessingResults($userid, $jobid, null, null, true);
                    
                    if (count($result) > 0)
                    {
                        return $this->wpsRequestManager->download($result[0]['value'], null, $result[0]['identifier']);
                    }
                    break;
                // wps/users/{userid}/jobs/{jobid}/logs
                case 'logs':
                    $job = $this->getJobs($userid, $jobid, array(), false);
                    if (isset($job[0]['logs']))
                    {
                        $content = Curl::Get($job[0]['logs'], array(), $this->curlOpts);
                        if ($content)
                        {
                            return RestoLogUtil::success('WPS job  for user ' . $userid, array ('data' => $content));
                        }
                    }
                default:
                    break;
            }
        }
        return RestoLogUtil::httpError(404);
    }

    /**
     *              HTTP/GET wps/users/{userid}/results
     *              HTTP/GET wps/users/{userid}/results/{resultid}/download
     *              
     * @param unknown $segments
     * @return unknown|NULL
     */
    private function GET_users_results($userid, $segments){
        
        // users/{userid}/results
        if (!isset($segments[3]))
        {
            $results = $this->getProcessingResults($userid);
            return RestoLogUtil::success('WPS jobs results for user ' . $userid, array ('data' => $results));
        }

        // wps/users/{userid}/results/{resultid}/download
        if (isset($segments[4]) && $segments[4] === 'download') {
            
            $resultid = $segments[3];
            if (!ctype_digit($resultid)) {
                RestoLogUtil::httpError(400);
            }
            // ? Is processings file result
            $result = $this->getProcessingResults($userid, null, $resultid, null, true);
            
            if (count($result) > 0)
            {
                return $this->wpsRequestManager->download($result[0]['value'], null, $result[0]['identifier']);
            }
        }
        return RestoLogUtil::httpError(404);
    }
    
    
    
    /**
     * @param unknown $jobid
     * @param unknown $resultid
     * @param array $filters
     * @param string $rootPath
     * @return array|unknown
    private function getProcessingResults($userid, $jobid = null, $resultid =null, $filters= array(), $realValue = false) {
        // ? Result id is setted
        if (isset($resultid))
        {
            $filters[] = 'usermanagement.wps_results.uid=' . $this->context->dbDriver->quote($resultid);
        }
        
        $value = $realValue ? 'value' : $this->context->dbDriver->quote($this->externalServerAddress . '/users/' . $userid . '/results/') . ' || usermanagement.wps_results.uid || ' . $this->context->dbDriver->quote('/download');
        
                . $value . ' as value ';
        $removeType = $this->doesRemoveAlsoDeletesProcessingsFromDatabase 
        $wpsStatus = $proactiveStatus;
                $wpsStatus = 'ProcessAccepted';
                break;
                $wpsStatus = 'ProcessStarted';
                break;
                $wpsStatus = 'ProcessSucceeded';
                break;
                $wpsStatus = 'ProcessPaused';
                break;
                $wpsStatus = 'ProcessFailed';
                break;
                break;
        return $wpsStatus;
            return RestoLogUtil::success('WPS processing description for identifier ' . $segments[1], array ('data' => $description));
            $resultid = $data[$i];
                    $resultid);
                $item['id'] = $result[0]['identifier'];
