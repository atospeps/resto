<?php
/**
 * Sentinel utilities functions
 * @author Atos
 *
 */
class SentinelUtil {
    
    /**
     * Reads Footprint from geolocation grid point
     * @param unknown $geolocationGridPoint
     * @param unknown $orbitDirection
     */
    public static function readFootprintFromGeolocationGridPoint($geolocationGridPoint, $orbitDirection){
        /*
         * On Ascending orbit, we are:
         * ll : pt(0, 0) i.e pixel 0, line 0 in geolocation grid point
         * lr : pt(max, 0)
         * ul : pt(0, max)
         * ur : pt(max, max),
         * 
         * On Descending orbit, we are:
         * ul : pt(0, 0) i.e pixel 0, line 0 in geolocation grid point
         * ur : pt(max, 0)
         * ll : pt(0, max)
         * lr : pt(max, max), 
         */
        $ll = array();
        $lr = array();
        $ul = array();
        $ur = array();
        $lineMax = 0;
        $lineMin = 0;
        $lineMinStatus = 0;
        $pixelMax = 0;
        for ($i = 0, $ii = $geolocationGridPoint->length; $i < $ii; $i++) {
            $line = (integer) $geolocationGridPoint->item($i)->getElementsByTagName('line')->item(0)->nodeValue;
            $pixel = (integer) $geolocationGridPoint->item($i)->getElementsByTagName('pixel')->item(0)->nodeValue;
            $coordinates = array($geolocationGridPoint->item($i)->getElementsByTagName('longitude')->item(0)->nodeValue, $geolocationGridPoint->item($i)->getElementsByTagName('latitude')->item(0)->nodeValue);
            if ($lineMinStatus == 0)
            {
                $lineMinStatus = 1;
                $lineMin=$line;
            }
            if ($line === $lineMin) {
                if ($pixel === 0) {
                    $ll = $coordinates;
                }
                else if ($pixel >= $pixelMax) {
                    $pixelMax = $pixel;
                    $lr = $coordinates;
                }
            }
            else if ($line >= $lineMax) {
                $lineMax = $line;
                if ($pixel === 0) {
                    $ul = $coordinates;
                }
                else if ($pixel >= $pixelMax) {
                    $pixelMax = $pixel;
                    $ur = $coordinates;
                }
            }
        }
        /*
         * On descending orbit, the North and South are inverted
         */
        if (strtolower($orbitDirection) == "descending"){
            /*
             * Temporary coordinates
             */
            $ll_ = $ll;
            $lr_ = $lr;
            $ur_ = $ur;
            $ul_ = $ul;
            /*
             * Inverts North and South
             */
            $lr = $ul_;
            $ll = $ur_;
            $ul = $lr_;
            $ur = $ll_;
        }
        return array($ll, $lr, $ur, $ul, $ll);
    }

    /**
     * Performs an inversion of the specified Sentinel-1 quicklooks footprint (DHUS).
     * The datahub systematically performs an inversion of the Sentinel-1 quicklooks taking as input the quicklook images (.png) inside 
     * the ZIP files (i.e. as produced by the S1 ground segment).
     * @param unknown $polygon polygon array(ll, lr, ur, ul, ll)
     * @param unknown $orbitDirection orbit direction
     * @return multitype:
     */
    public static function reorderDhusFootprintToSafe($footprint, $orbitDirection){
        return self::flipQuicklookFootprint($footprint, $orbitDirection);
    }

    /**
     * Performs an inversion of the specified Sentinel-1 quicklooks footprint (inside the ZIP files, i.e SAFE product).
     * The datahub systematically performs an inversion of the Sentinel-1 quicklooks taking as input the quicklook images (.png) inside 
     * the ZIP files (i.e. as produced by the S1 ground segment).
     * @param unknown $polygon polygon array(ll, lr, ur, ul, ll)
     * @param unknown $orbitDirection orbit direction
     * @return multitype:
     */
    public static function reorderSafeFootprintToDhus($footprint, $orbitDirection){
        return self::flipQuicklookFootprint($footprint, $orbitDirection);
    }

    /**
     * Flip quicklook footprint according to orbit direction
     * @param unknown $footprint polygon array(ll, lr, ur, ul, ll)
     * @param unknown $orbitDirection orbit direction
     * @return multitype:unknown
     */
    private static function flipQuicklookFootprint($footprint, $orbitDirection){
        /*
         * For ascending orbits, the quicklook is flipped horizontally (i.e. North/South inversion)
         */
        if (strtolower($orbitDirection) == "ascending"){
            $ul = $footprint[0];
            $ur = $footprint[1];
            $lr = $footprint[2];
            $ll = $footprint[3];
        }
        /*
         * For descending orbits, the quiclook is flipped vertically and horizontally (i.e. West/East and North/South inversions)
         */
        else {
            $lr = $footprint[0];
            $ll = $footprint[1];
            $ul = $footprint[2];
            $ur = $footprint[3];
        }

        return array($ll, $lr, $ur, $ul, $ll);
    }
}