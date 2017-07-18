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
        try 
        {
            $query = 'with t as (select (st_dump(ST_Shift_Longitude(\'' . $wkt . '\'))).geom) select st_astext(ST_Shift_Longitude(st_union(geom))) from t;';
            $result = $this->dbDriver->query($query);
            $arr = $this->dbDriver->fetch($result);
            return isset($arr[0]['st_astext']) ? $arr[0]['st_astext'] : null;
        }
        catch (Exception $e) 
        {
            error_log(__method__ . ': Invalid input WKT : ' . $wkt, 0);
            throw new Exception(__method__ . ': Invalid input WKT.', 500);
        }
    }

}
