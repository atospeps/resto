<?php

/**
 * RESTo PostgreSQL files functions
 */
class Functions_files {
    
    private $dbDriver = null;
    private $dbh = null;
    
    /**
     * Constructor
     * 
     * @param array $config
     * @param RestoCache $cache
     * @throws Exception
     */
    public function __construct($dbDriver) {
        $this->dbDriver = $dbDriver;
        $this->dbh = $dbDriver->dbh;
    }

    
    /**
     * Return all files (user files, entry processing files, or fileId)
     *
     * @param string $userid (email)
     * @param boolean $entryProcessing
     * @param int $fileid
     * 
     * @return array
     * @throws exception
     */
    public function getFiles($userid, $entryProcessing, $fileid) {  
        $query = ''; 
        
        if($entryProcessing) {
            $query = 'SELECT * FROM usermanagement.files WHERE type = \'entryprocessing\'';
        } else {
            $query = 'SELECT * FROM usermanagement.files';
            if(isset($fileid)) {
                $sql[] = " gid = '$fileid' ";
            }
            if(isset($userid)) {
                $sql[] = " email = '$userid' ";
            }
            
            if (!empty($sql)) {
                $query .= ' WHERE ' . implode(' AND ', $sql);
            }
        }

        $files = array();
        $results = $this->dbDriver->query($query);
        while ($result = pg_fetch_assoc($results)) {
            $files[] = array(
                    'id' => $result['gid'],
                    'userid' => $result['email'],
                    'jobid' => $result['jobid'],
                    'name' => $result['name'],
                    'type' => $result['type'],
                    'path' => $result['path'],
                    'date' => $result['date'],
                    'size' => $result['size'],
                    'format' => $result['format']
            );
        }
        
        return $files;
    }
    
    
    /**
     * Add file
     * 
     * @param string $userid (email)
     * @param int $jobid
     * @param string $type
     * @param string $path
     * @param date $date
     * @param int $size
     * @param string $format
     *   
     * @return boolean
     * @throws exception
     */
    public function addFile($userid, $jobid, $name, $type, $path, $size, $format) {
        if (!isset($userid) && $type != 'entryprocessing') {
            return false;
        }

        // Check if file already exists in the database
        if($this->isFileExists($userid, $name, $type == "entryprocessing")) {
            RestoLogUtil::httpError(6000, 'Cannot add file : ' . $name . ', it already exists');
        }
            	
        $values = array(
            '\'' . pg_escape_string($userid) . '\'',
            pg_escape_string($jobid),
            '\'' . pg_escape_string($name) . '\'',
            '\'' . pg_escape_string($type) . '\'',
            '\'' . pg_escape_string($path) . '\'',
            'now()',
            pg_escape_string($size),
            '\'' . pg_escape_string($format) . '\''
        );
        
        $query = 'INSERT INTO usermanagement.files (email, jobid, name, type, path, date, size, format) VALUES (' . join(',', $values) . ')';
        
        $this->dbDriver->query($query);
        return true;
    }

    
    /**
     * Return true if the file $name exists for a specific user or for globally files (if the type = 'entryprocessing')
     *
     * @param string $userid
     * @param string $name
     * @param boolean $entryProcessing
     * @return boolean
     * @throws exception
     */
    public function isFileExists($userid, $name, $entryProcessing) {
        if (!isset($name)) {
            return false;
        }
        $query = 'SELECT 1 FROM usermanagement.files WHERE name=\'' . pg_escape_string($name) . '\'';
        if(isset($entryProcessing) && $entryProcessing == true) {
            $query .= ' AND type=\'entryprocessing\'';
        } else if(isset($userid)) {
            $query .= ' AND email=\'' . pg_escape_string($userid) . '\'';
        }

        $results = $this->dbDriver->fetch($this->dbDriver->query(($query)));
        return !empty($results);
    }
    
    
    /**
     * Remove file
     * 
     * @param string $identifier
     * @return boolean
     * @throws exception
     */
    public function removeFile($identifier) {
        if (!isset($identifier)) {
            return false;
        }
        $results = $this->dbDriver->query('DELETE FROM usermanagement.files WHERE gid=\'' . pg_escape_string($identifier) . '\'');
        
        if ($results) {
            return true;
        } else {
            return false;
        }
    }
    
}
