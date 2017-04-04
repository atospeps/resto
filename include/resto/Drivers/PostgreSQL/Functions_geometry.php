<?php
/**
 * RESTo PostgreSQL geometry functions
 */
class Functions_geometry {
    
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

    public function simplifyPolygon($wkt) {
        $query = 'with t as (select (st_dump(ST_Shift_Longitude(st_geomFromText(\'' . $wkt . '\')))).geom) select st_astext(st_union(ST_Shift_Longitude(geom))) from t';
        $result = $this->dbDriver->fetch($this->dbDriver->query($query));
        return isset($result[0]['st_astext']) ? $result[0]['st_astext'] : null;
    }

}
