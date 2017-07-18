<?php
/*
 * Copyright 2014 Jérôme Gasperi
 *
 * Licensed under the Apache License, version 2.0 (the "License");
 * You may not use this file except in compliance with the License.
 * You may obtain a copy of the License at:
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
 * License for the specific language governing permissions and limitations
 * under the License.
 */

/**
 * RESTo PostgreSQL features functions
 */
class Functions_features {
    
    /*
     * Reference to database driver
     */
    private $dbDriver = null;
    
    /*
     * Reference to database handler
     */
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
     * Get all features.
     * 
     * @param int $number
     * @param int $offset
     * @return array
     */
    public function getAllFeatures($number, $offset) {
        $query = 'SELECT collection, keywords FROM resto.features LIMIT ' . $number . ' OFFSET ' . $offset;
        $results = $this->dbDriver->query($query);

        $features = array();
        while ($result = pg_fetch_assoc($results)) {
            $features[] = array(
                    'collection' => $result['collection'],
                    'keywords' => json_decode(trim($result['keywords'], '\''), true)
            );
        }
        return $features;
    }
    
    /**
     * 
     * Get an array of features descriptions
     * 
     * @param RestoContext $context
     * @param RestoUser $user
     * @param RestoCollection $collection
     * @param RestoModel $params
     * @param array $options
     *      array(
     *          'limit',
     *          'offset',
     *          'count'// true to return the total number of results without pagination
     * 
     * @return array
     * @throws Exception
     */
    public function search($context, $user, $collection, $params, $options) {
                
        /*
         * Search filters functions
         */
        $filtersUtils = new Functions_filters();
        
        /*
         * Set model
         */
        $model = isset($collection) ? $collection->model : new RestoModel_default();
        
        /*
         * Check that mandatory filters are set
         */
        $this->checkMandatoryFilters($model, $params);
        
        /*
         * Set search filters
         */
        $filters = $filtersUtils->prepareFilters($model, $params);
        
        /*
         * TODO - get count from facet statistic and not from count() OVER()
         * 
         * TODO - Add filters depending on user rights
         * $oFilter = superImplode(' AND ', array_merge($filters, $this->getRightsFilters($this->R->getUser()->getRights($this->description['name'], 'get', 'search'))));
         */
        $oFilter = implode(' AND ', $filters);

        /*
         * Prepare query
         */
        $fields = implode(',', $filtersUtils->getSQLFields($model));
        $from = ' FROM ' . (isset($collection) ? '_' . strtolower($collection->name) : 'resto') . '.features' . ($oFilter ? ' WHERE ' . $oFilter : '');
        
        /*
         * Result set ordering and limit
         */
        $extra = ' ORDER BY startdate DESC, identifier';
        $extra .= ' LIMIT ' . $options['limit'] . ' OFFSET ' . $options['offset'];

        /*
         * Retrieve products from database
         * Note: totalcount is estimated except if input search contains a lon/lat filter
         */
        return array(
                'count' => $this->getCount($from, $params),
                'features' => $this->toFeatureArray($context, $user, $collection, $this->dbDriver->query('SELECT ' . $fields . $from . $extra))
        );
    }

    /**
     * 
     * Get feature description
     *
     * @param RestoContext $context
     * @param RestoUser $user
     * @param integer $identifier
     * @param RestoModel $model
     * @param RestoCollection $collection
     * @param array $filters
     * 
     * @return array
     * @throws Exception
     */
    public function getFeatureDescription($context, $user, $identifier, $collection = null, $filters = array()) {
        $model = isset($collection) ? $collection->model : new RestoModel_default();
        $filtersUtils = new Functions_filters();
        $query = 'SELECT ' . implode(',', $filtersUtils->getSQLFields($model)) . ' FROM ' . (isset($collection) ? '_' . strtolower($collection->name) : 'resto') . '.features WHERE ' . $model->getDbKey('identifier') . "='" . pg_escape_string($identifier) . "'" . (count($filters) > 0 ? ' AND ' . join(' AND ', $filters) : '');
        $results = $this->dbDriver->query($query);
        $arrayOfFeatureArray = $this->toFeatureArray($context, $user, $collection, $results);
        return isset($arrayOfFeatureArray[0]) ? $arrayOfFeatureArray[0] : null;
    }

    /**
     *
     * Get feature description by title
     *
     * @param RestoContext $context
     * @param RestoUser $user
     * @param integer $title
     * @param RestoModel $model
     * @param RestoCollection $collection
     * @param array $filters
     *
     * @return array
     * @throws Exception
     */
    public function getFeatureDescriptionByTitle($context, $user, $title, $collection = null, $filters = array()) {
        $model = isset($collection) ? $collection->model : new RestoModel_default();
        $filtersUtils = new Functions_filters();
        $query = 'SELECT ' . implode(',', $filtersUtils->getSQLFields($model)) . ' FROM ' . (isset($collection) ? '_' . strtolower($collection->name) : 'resto') . '.features WHERE ' . $model->getDbKey('title') . "='" . pg_escape_string($title) . "'" . (count($filters) > 0 ? ' AND ' . join(' AND ', $filters) : '');
        $results = $this->dbDriver->query($query);
        $arrayOfFeatureArray = $this->toFeatureArray($context, $user, $collection, $results);
        return isset($arrayOfFeatureArray[0]) ? $arrayOfFeatureArray[0] : null;
    }    

    /**
     * Get all versions of a product
     * 
     * @param RestoContext $context
     * @param RestoUser $user
     * @param string $productIdentifier
     * @param string $dhusIngestDate
     * @param RestoCollection $collection
     * @param string $pattern
     * @return array|null
     */
    public function getAllVersions($context, $user, $productIdentifier, $dhusIngestDate, $collection, $pattern)
    {
        $filtersUtils = new Functions_filters();
        
        $schema = !empty($collection) ? '_' . strtolower($collection->name) : 'resto';
        $model = isset($collection) ? $collection->model : new RestoModel_default();

        /*
         * WHERE
         */
        $whereClause = " WHERE productidentifier LIKE '" . pg_escape_string($pattern) . "'";
        
        /*
         * FROM
         */ 
        $fromClause = ' FROM ' . pg_escape_string($schema) . '.features';

        /*
         * ORDER BY
         */
        switch($schema) {
            case '_s1':
                $orderByClause = " ORDER BY"
                               .   " isnrt ASC,"
                               .   " CASE realtime"
                               .     " WHEN 'Reprocessing' THEN 1"
                               .     " WHEN 'Off-line'     THEN 2"
                               .     " WHEN 'Fast-24h'     THEN 3"
                               .     " WHEN 'NRT-3h'       THEN 4"
                               .     " WHEN 'NRT-1h'       THEN 5"
                               .     " WHEN 'NRT-10m'      THEN 6"
                               .     " ELSE 7"
                               .   " END";
                if ($context->obsolescenceS1useDhusIngestDate === true) {
                    $orderByClause .= ", dhusingestdate DESC";
                }
                break;
            case '_s2st':
                $orderByClause = " ORDER BY"
                               .   " isnrt ASC,"
                               .   " CASE realtime"
                               .     " WHEN 'Nominal' THEN 1"
                               .     " WHEN 'NRT'     THEN 2"
                               .     " WHEN 'RT'      THEN 3"
                               .     " ELSE 4"
                               .   " END,"
                               .   " SUBSTRING (productidentifier, 29, 4) DESC"; // version number
                break;
            case '_s3':
                $orderByClause = " ORDER BY"
                               . " isnrt ASC,"
                               .   " CASE realtime"
                               .     " WHEN 'NTC' THEN 1"
                               .     " WHEN 'STC' THEN 2"
                               .     " WHEN 'NRT' THEN 3"
                               .     " ELSE 4"
                               .   " END,"
                               . " SUBSTRING (productidentifier, 49, 15) DESC"; // creation date
                break;
        }

        /*
         * Query
         */ 
        $query = 'SELECT ' . implode(',', $filtersUtils->getSQLFields($model));
        $query .= $fromClause;
        $query .= $whereClause;
        $query .= $orderByClause;
        
        /*
         * Results
         */
        $results = $this->dbDriver->query($query);
        return $this->toFeatureArray($context, $user, $collection, $results);
    }

    /**
     * Return true if a version of a product has already the specified realtime  
     *    
     * @param string $realtime
     * @param string $pattern
     * 
     * @return {bool} true a product version has already the specified realtime
     */
    public function checkRealtimeExists($collectionName, $realtime, $pattern)
    {
        $schema = !empty($collectionName) ? '_' . strtolower($collectionName) : 'resto';
        $query = "SELECT realtime"
               . "  FROM " . $schema . ".features"
               . "  WHERE productidentifier LIKE '" . pg_escape_string($pattern) . "'"
               . "  AND realtime = '" . $realtime . "'";
        $results = $this->dbDriver->query($query);
        $rows = pg_num_rows($results);
        return ($rows > 0);
    }
    
    /**
     * 
     * @param string $collection
     * @param array $featuresArray
     * @param int $visible
     * @param string $newVersion    feature id of the new 
     * @return string
     */
    public function updateFeatureVersions($collection, $featuresArray, $visible, $newVersion)
    {
        // Column/Values to update into database
        $columnsAndValues = array (
                $collection->model->getDbKey('visible') => $visible,
                $collection->model->getDbKey('newVersion') => '\'' . $newVersion . '\'' ,
                'updated' => 'now()'
        );

        // Convert the array in a format accepted for the "update" sql query
        $values = implode(', ', array_map(function ($v, $k) { return $k . '=' . $v; }, $columnsAndValues, array_keys($columnsAndValues)));

        // List of product (by id) to update
        $oldFeaturesIdList = implode(', ', array_values(array_map(function ($feature) { return "'{$feature['id']}'"; }, $featuresArray)));

        // Database schema
        $schema = isset($collection) ? ('_' . strtolower($collection->name)) : 'resto';

        // SQL update query
        $query = 'UPDATE ' . pg_escape_string($schema) . '.features SET ' . $values . ' WHERE identifier in (' . $oldFeaturesIdList . ')';

        try {

            /*
             * Start transaction
             */
            pg_query($this->dbh, 'BEGIN');

            /*
             * Store feature
            */
            pg_query($this->dbh, $query);

            pg_query($this->dbh, 'COMMIT');
        } catch (Exception $e) {
            pg_query($this->dbh, 'ROLLBACK');
            RestoLogUtil::httpError(500, 'Versions of product ' . $newVersion . ' cannot be updated in database');
        }
    }

    /**
     * Check if feature identified by $identifier exists within {schemaName}.features table
     * 
     * @param string $identifier - feature unique identifier 
     * @param string $schema - schema name
     * @return boolean
     * @throws Exception
     */
    public function featureExists($identifier, $schema = null) {
        $query = 'SELECT 1 FROM ' . (isset($schema) ? pg_escape_string($schema) : 'resto') . '.features WHERE identifier=\'' . pg_escape_string($identifier) . '\'';
        $results = $this->dbDriver->fetch($this->dbDriver->query(($query)));
        return !empty($results);
    }
    
    /**
     * Insert feature within collection
     * 
     * @param RestoCollection $collection
     * @param array $featureArray
     * @throws Exception
     */
    public function storeFeature($collection, $featureArray) {

        /*
         * Check that resource does not already exist in database
         */
        if ($collection->context->dbDriver->check(RestoDatabaseDriver::FEATURE, array('featureIdentifier' => $featureArray['id']))) {
            RestoLogUtil::httpError(409, 'Feature ' . $featureArray['id'] . ' already in database');
        }
        
        /*
         * Get database columns array
         */
        $columnsAndValues = $this->getColumnsAndValues($collection, $featureArray, true);

        try {
            
            /*
             * Start transaction
             */
            $this->dbDriver->query('BEGIN');

            /*
             * Store feature
             */
            $query = 'INSERT INTO ' . pg_escape_string('_' . strtolower($collection->name)) . '.features (' . join(',', array_keys($columnsAndValues)) . ') VALUES (' . join(',', array_values($columnsAndValues)) . ')';
            $this->dbDriver->query($query);

            /*
             * Store facets
             */
            $this->storeKeywordsFacets($collection, json_decode(trim($columnsAndValues['keywords'], '\''), true));
            
            $this->dbDriver->query('COMMIT');
            
        } 
        catch (Exception $e) 
        {
            $this->dbDriver->query('ROLLBACK');
            RestoLogUtil::httpError(500, 'Feature ' . $featureArray['id'] . ' cannot be inserted in database');
        }
    }
    
    /**
     * Update feature within collection
     *
     * @param RestoCollection $collection
     * @param array $featureArray
     * @throws Exception
     */
    public function updateFeature($collection, $featureArray) {

        $featureId = $featureArray['id'];

        /*
         * Check that resource exists in database
         */
        if ($collection->context->dbDriver->check(RestoDatabaseDriver::FEATURE, array (
            'featureIdentifier' => $featureId 
        ))) {

            /*
             * Get database columns array
             */
            $columnsAndValues = $this->getColumnsAndValues($collection, $featureArray, false, $featureId);
            
            // Convert the array ion a format accepted for the "update" sql query
            $values = implode(', ', array_map(function ($v, $k) {
                return $k . '=' . $v;
            }, $columnsAndValues, array_keys($columnsAndValues)));

            try {
                
                /*
                 * First we delete the current facets
                 */                
                $result = pg_query($this->dbh, "SELECT keywords FROM " . pg_escape_string('_' . strtolower($collection->name)) . ".features WHERE identifier='" . $featureId  . "'");
                // Format correctly the keywords to be treated by the removeFeatureFacet function
                $array = pg_fetch_row($result);
                $keywords['properties']['keywords'] = json_decode($array[0], true);
                $this->removeFeatureFacets($keywords, $collection->name);
                
                /*
                 * Start transaction
                 */
                pg_query($this->dbh, 'BEGIN');
                
                /*
                 * Store feature
                 */
                pg_query($this->dbh, "UPDATE " . pg_escape_string('_' . strtolower($collection->name)) . ".features SET " . $values . " WHERE identifier='" . $featureId  . "'");
                
                /*
                 * We insert the new facets
                 */
                $this->storeKeywordsFacets($collection, json_decode(trim($columnsAndValues['keywords'], '\''), true));
                
                pg_query($this->dbh, 'COMMIT');
            } catch (Exception $e) {
                pg_query($this->dbh, 'ROLLBACK');
                RestoLogUtil::httpError(500, 'Feature ' . $featureId . ' cannot be updated in database');
            }
        } else {
            RestoLogUtil::httpError(409, 'Feature ' . $featureId . ' does not exist in database');
        }
    }

    /**
     * Remove feature from database
     * 
     * @param RestoFeature $feature
     */
    public function removeFeature($feature) {
        
        try {
            
            /*
             * Begin transaction
             */
            $this->dbDriver->query('BEGIN');
            
            /*
             * Remove feature
             */
            $this->dbDriver->query('DELETE FROM ' . (isset($feature->collection) ? '_' . strtolower($feature->collection->name): 'resto') . '.features WHERE identifier=\'' . pg_escape_string($feature->identifier) . '\'');
            
            /*
             * Remove facets
             */
            $this->removeFeatureFacets($feature->toArray());
            
            /*
             * Commit
             */
            $this->dbDriver->query('COMMIT');
            
        } catch (Exception $e) {
            $this->dbDriver->query('ROLLBACK'); 
            RestoLogUtil::httpError(500, 'Cannot delete feature ' . $feature->identifier);
        }
    }

    /**
     * Update feature keywords
     *
     * @param RestoFeature $feature
     * @param array $keywords
     * @throws Exception
     */
    public function updateFeatureKeywords($feature, $keywords) {
       
        $featureId = $feature->identifier;
        
        $toUpdate = array();
        $columns = array();
        /*
         * Store new keywords
        */
        if (is_array($keywords)) {
            $columns[$feature->collection->model->getDbKey('keywords')] = '\'' . pg_escape_string(json_encode($keywords)) . '\'';
            $columns[$feature->collection->model->getDbKey('hashes')] = '\'{' . join(',', $this->getHashes($keywords)) . '}\'';
            foreach ($columns as $columnName => $columnValue) {
                array_push($toUpdate, $columnName . '=' . $columnValue);
            }
        }
        if (empty($toUpdate)) {
            RestoLogUtil::httpError(400, 'Nothing to update for ' . $feature->identifier);
        }
        
        
        if (empty($toUpdate)) {
            RestoLogUtil::httpError(400, 'Nothing to update for ' . $feature->identifier);
        }
        try {
            
            /*
             * First we delete the current facets
             */                
            $result = pg_query($this->dbh, "SELECT keywords FROM " . pg_escape_string('_' . strtolower($feature->collection->name)) . ".features WHERE identifier='" . $featureId  . "'");
            /* 
             * Format correctly the keywords to be treated by the removeFeatureFacet function
             */                 
            $array = pg_fetch_row($result);
            $_keywords['properties']['keywords'] = json_decode($array[0], true);
            $this->removeFeatureFacets($_keywords, $feature->collection->name);
            
            /*
             * Start transaction
             */
            pg_query($this->dbh, 'BEGIN');
            
            /*
             * Update feature
             */
            $this->dbDriver->query('UPDATE ' .  (isset($feature->collection) ? '_' . strtolower($feature->collection->name): 'resto') . '.features SET ' . join(',', $toUpdate) . ' WHERE identifier = \'' . pg_escape_string($feature->identifier) . '\'');
            /*
             * We insert the new facets
             */
            $this->storeKeywordsFacets($feature->collection, $keywords, true);
            
            pg_query($this->dbh, 'COMMIT');
        } catch (Exception $e) {
            pg_query($this->dbh, 'ROLLBACK');
            RestoLogUtil::httpError(500, 'Cannot update feature ' . $featureId);
        }

        return true;
    }

    /**
     * Return exact count or estimate count from query
     *
     * @param String $from
     * @param Boolean $filters
     */
    public function getCount($from, $filters = array()) {
        /*
         * Determine if the count is estimated or real
         */
        $realCount = false;
        if (isset($filters['geo:lon'])) {
            $realCount = true;
        }
    
        /*
         * Perform count estimation
         */
        $result = -1;
        if (!$realCount) {
            $query = 'SELECT count_estimate(\'' . pg_escape_string('SELECT * ' . $from) . '\') as count';
            $result = pg_fetch_result($this->dbDriver->query($query), 0, 0);
        }
    
        if ($result !== false && $result < 10 * $this->dbDriver->resultsPerPage) {
            $query = 'SELECT count(*) as count ' . $from;
            $result = pg_fetch_result($this->dbDriver->query($query), 0, 0);
            $realCount = true;
        }
    
        return array(
                'total' => $result === false ? -1 : (integer) $result,
                'isExact' => $realCount
        );
    }

    /**
     * Store keywords facets
     * 
     * @param RestoCollection $collection
     * @param array $keywords
     */
    public function storeKeywordsFacets($collection, $keywords) {
        /*
         * One facet per keyword
         */
        $facets = array();
        foreach ($keywords as $hash => $keyword) {
            if ($this->dbDriver->facetUtil->getFacetCategory($keyword['type'])) {
                $facets[] = array(
                    'name' => $keyword['name'],
                    'type' => $keyword['type'],
                    'hash' => $hash,
                    'parentHash' => isset($keyword['parentHash']) ? $keyword['parentHash'] : null
                );
            }
        }
        
        /*
         * Store to database
         */
        $this->dbDriver->store(RestoDatabaseDriver::FACETS, array(
            'facets' => $facets,
            'collectionName' => $collection->name
        ));
            
    }
    /**
     * Convert feature array to database column/value pairs
     *
     * @param RestoCollection $collection
     * @param array $featureArray
     * @throws Exception
     */
    private function getColumnsAndValues($collection, $featureArray, $created, $featureIdentifier = null) {
        if ($created) {
            /*
             * Initialize columns array
             */
            $columns = array_merge(array (
                $collection->model->getDbKey('identifier') => '\'' . $featureArray['id'] . '\'',
                $collection->model->getDbKey('collection') => '\'' . $collection->name . '\'',
                $collection->model->getDbKey('geometry') => 'ST_GeomFromText(\'' . RestoGeometryUtil::geoJSONGeometryToWKT($featureArray['geometry']) . '\', 4326)',
                'updated' => 'now()',
                'published' => 'now()' 
            ), $this->propertiesToColumns($collection, $featureArray['properties']));
        } else {
            /*
             * Initialize update columns array
             */
            $columns = array_merge(array (
                $collection->model->getDbKey('identifier') => '\'' . $featureIdentifier . '\'',
                $collection->model->getDbKey('collection') => '\'' . $collection->name . '\'',
                $collection->model->getDbKey('geometry') => 'ST_GeomFromText(\'' . RestoGeometryUtil::geoJSONGeometryToWKT($featureArray['geometry']) . '\', 4326)',
                'updated' => 'now()' 
            ), $this->propertiesToColumns($collection, $featureArray['properties']));
        }
        return $columns;
    }
    
    /**
     * Convert feature properties array to database column/value pairs
     * 
     * @param RestoCollection $collection
     * @param array $properties
     * @throws Exception
     */
    private function propertiesToColumns($collection, $properties) {
        
        /*
         * Roll over properties
         */
        $columns = array();
        foreach ($properties as $propertyName => $propertyValue) {

            /*
             * Do not process null and already processed values
             */
            if (!isset($propertyValue) || in_array($propertyName, array('updated', 'published', 'collection'))) {
                continue;
            }
            
            /*
             * Keywords
             */
            if ($propertyName === 'keywords' && is_array($propertyValue)) {
                
                $columnValue = '\'' . pg_escape_string(json_encode($propertyValue)) . '\'';
                
                /*
                 * Compute hashes
                 */
                $columns[$collection->model->getDbKey('hashes')] = '\'{' . join(',', $this->getHashes($propertyValue)) . '}\'';
                
                /*
                 * landuse keywords are also stored in dedicated
                 * table columns to speed up search requests
                 */
                $columns = array_merge($columns, $this->landuseColumns($propertyValue));
                
            }
            /*
             * Special case for array
             */
            else if ($collection->model->getDbType($propertyName) === 'array') {
                $columnValue = '\'{' . pg_escape_string(join(',', $propertyValue)) . '}\'';
            }
            else {
                $columnValue = '\'' . pg_escape_string($propertyValue) . '\'';
            }
            
            /*
             * Add element
             */
            $columns[$collection->model->getDbKey($propertyName)] = $columnValue;
            
        }
        
        return $columns;

    }
    
    /**
     * Return array of hashes from keywords
     * 
     * @param type $keywords
     */
    private function getHashes($keywords) {
        $hashes = array();
        foreach (array_keys($keywords) as $hash) {
            
            /*
             * Do not index location if cover is lower than 10 %
             */
            if (in_array($keywords[$hash]['type'], array('country', 'region', 'state'))) {
                if (!isset($keywords[$hash]['value']) || $keywords[$hash]['value'] < 10) {
                    continue;
                }
            }
            $hashes[] = '"' . pg_escape_string($hash) . '"';
            $hashes[] = '"' . pg_escape_string($keywords[$hash]['type'] . ':' . (isset($keywords[$hash]['normalized']) ? $keywords[$hash]['normalized'] : strtolower($keywords[$hash]['name']))) . '"';
        }
        return $hashes;
    }
    
    /**
     * Get landuse database columns from input keywords
     * 
     * @param array $keywords
     * @return type
     */
    private function landuseColumns($keywords) {
        $columns = array();
        foreach (array_values($keywords) as $keyword) {
            if ($keyword['type'] === 'landuse') {
                $columns['lu_' . strtolower($keyword['name'])] = $keyword['value'];
            }
        }
        return $columns;
    }

    /**
     * Check that mandatory filters are set
     * 
     * @param RestoModel $model
     * @param Array $params
     * @return boolean
     */
    private function checkMandatoryFilters($model, $params) {
        $missing = array();
        foreach (array_keys($model->searchFilters) as $filterName) {
            if (isset($model->searchFilters[$filterName])) {
                if (isset($model->searchFilters[$filterName]['minimum']) && $model->searchFilters[$filterName]['minimum'] === 1 && (!isset($params[$filterName]))) {
                    $missing[] = $filterName;
                }
            } 
        }
        if (count($missing) > 0) {
            RestoLogUtil::httpError(400, 'Missing mandatory filter(s) ' . join(', ', $filterName));
        }
        
        return true;
        
    }
    
    /**
     * Remove feature facets
     *
     * @param array $featureArray
     */
    private function removeFeatureFacets($featureArray, $collectionName = null) {
        foreach (array_keys($featureArray['properties']['keywords']) as $hash) {
            if (is_null($collectionName)) {
                $this->dbDriver->remove(RestoDatabaseDriver::FACET, array (
                    'hash' => $hash,
                    'collectionName' => $featureArray['properties']['collection'] 
                ));
            } else {
                $this->dbDriver->remove(RestoDatabaseDriver::FACET, array (
                    'hash' => $hash,
                    'collectionName' => $collectionName 
                ));
            }
        }
    }

    /**
     * Return featureArray array from database results
     * 
     * @param RestoContext $context
     * @param RestoUser $user
     * @param RestoCollection $collection
     * @param array $results
     * @return array
     */
    private function toFeatureArray($context, $user, $collection, $results) {
        $featuresArray = array();
        $featureUtil = new RestoFeatureUtil($context, $user, $collection);
        while ($result = pg_fetch_assoc($results)) {
            $featuresArray[] = $featureUtil->toFeatureArray($result);
        }
        return $featuresArray;
    }
}
