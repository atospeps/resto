-- VERSION 1.3.2.1 de PEPS

--
-- Copyright (C) 2016 Jerome Gasperi <jerome.gasperi@gmail.com>
-- With priceless contribution from Nicolas Ribot <nicky666@gmail.com>
--
-- This work is placed into the public domain.
--
-- SYNOPSYS:
--   ST_SplitDateLine(polygon)
--
-- DESCRIPTION:
--
--   This function split the input polygon geometry against the -180/180 date line
--   Returns the original geometry otherwise
--
--   WARNING ! Only work for SRID 4326
--
-- USAGE:
--
CREATE OR REPLACE FUNCTION ST_SplitDateLine(geom_in geometry)
RETURNS geometry AS \$\$
DECLARE
	geom_out geometry;
	blade geometry;
BEGIN

        blade := ST_SetSrid(ST_MakeLine(ST_MakePoint(180, -90), ST_MakePoint(180, 90)), 4326);

	-- Delta longitude is greater than 180 then return splitted geometry
	IF (ST_XMin(geom_in) < -90 AND ST_XMax(geom_in) > 90) OR ST_XMax(geom_in) > 180 OR ST_XMax(geom_in) < -180 THEN

            -- Add 360 to all negative longitudes
            WITH tmp0 AS (
                SELECT geom_in AS geom
            ), tmp AS (
                SELECT st_dumppoints(geom) AS dmp FROM tmp0
            ), tmp1 AS (
                SELECT (dmp).path,
                CASE WHEN st_X((dmp).geom) < 0 THEN st_setSRID(st_MakePoint(st_X((dmp).geom) + 360, st_Y((dmp).geom)), 4326)
                ELSE (dmp).geom END AS geom
                FROM tmp
                ORDER BY (dmp).path[2]
            ), tmp2 AS (
                SELECT st_dump(st_split(st_makePolygon(st_makeline(geom)), blade)) AS d
                FROM tmp1
            )
            SELECT ST_Union(
                (
                    CASE WHEN ST_Xmax((d).geom) > 180 THEN ST_Translate((d).geom, -360, 0, 0)
                    ELSE (d).geom END
                )
            )
            INTO geom_out
            FROM tmp2;

        -- Delta longitude < 180 degrees then return untouched input geometry
        ELSE
            RETURN geom_in;
	END IF;

	RETURN geom_out;
END
\$\$ LANGUAGE 'plpgsql' IMMUTABLE;







------------------
-------------------
dateline geometry;
ST_MakeLine(ST_MakePoint(180, 90),ST_MakePoint(180, -90)) As dateline


--
--
--
CREATE OR REPLACE FUNCTION ST_CrossDateLine(geom_in geometry)
RETURNS boolean AS $$
DECLARE
	geom geometry;
BEGIN
	geom := ST_Shift_Longitude(geom_in);
    -- Delta longitude is greater than 180 then return splitted geometry
    RETURN (ST_XMin(geom) < 180 AND ST_XMax(geom) > 180);
END
$$ LANGUAGE 'plpgsql' IMMUTABLE;


--
-- version Stephane
--
CREATE OR REPLACE FUNCTION ST_CrossDateLine(geom_in geometry)
RETURNS boolean AS $$
BEGIN
    -- Delta longitude is greater than 180 then return splitted geometry
    RETURN (ST_XMin(geom_in) < -90 AND ST_XMax(geom_in) > 90) OR ST_XMax(geom_in) > 180 OR ST_XMax(geom_in) < -180;
END
$$ LANGUAGE 'plpgsql' IMMUTABLE;

--
--
--
CREATE OR REPLACE FUNCTION ST_CrossDateLine(geom_in geometry)
RETURNS geometry AS $$
DECLARE
	geom_out geometry;
	dateline geometry;
BEGIN
	IF (ST_XMin(geom_in) < -90 AND ST_XMax(geom_in) > 90) OR ST_XMax(geom_in) > 180 OR ST_XMax(geom_in) < -180 THEN
		geom := ST_Shift_Longitude(geom_in);
		dateline := ST_MakeLine(ST_MakePoint(180, -90), ST_MakePoint(180, 90));
		WITH
		tmp2 AS (
                SELECT st_dump(st_split(st_makePolygon(st_makeline(geom)), blade)) AS d
                FROM tmp1
            )
		
		SELECT ST_Union((
			        CASE WHEN ST_Xmax(geom) > 180 THEN ST_Translate(geom, -360, 0, 0)
			        ELSE geom 
		        	END
    			))
        INTO geom_out
        FROM tmp2;

	IF (ST_XMin(geom) < 180 AND ST_XMax(geom) > 180) THEN		
		
		SELECT ST_CollectionHomogenize(ST_Shift_Longitude(ST_Split(polygon, line)))
		FROM (SELECT 
		    ST_MakeLine(ST_MakePoint(180, -90), ST_MakePoint(180, 90)) As line,
		    ST_Shift_Longitude(geom_in) As polygon) INTO geom_out;
		RETURN geom_out;
	ELSE
		RETURN geom_in;
	END IF;
END
$$ LANGUAGE 'plpgsql' IMMUTABLE;



--
-- PEPS-FT-446
--
DROP INDEX IF EXISTS resto.resto_features_startdate_idx;
CREATE INDEX resto_features_startdate_idx ON resto.features  (startdate DESC, identifier);

DROP INDEX IF EXISTS _s1._s1_features_startdate_idx;
CREATE INDEX _s1_features_startdate_idx ON _s1.features  (startdate DESC, identifier);

DROP INDEX IF EXISTS _s2._s2_features_startdate_idx;
CREATE INDEX _s2_features_startdate_idx ON _s2.features  (startdate DESC, identifier);

DROP INDEX IF EXISTS _s3._s3_features_startdate_idx;
CREATE INDEX _s3_features_startdate_idx ON _s3.features  (startdate DESC, identifier);



-- ### Probleme observé en base de données : Des produits doublons dans la base de données :
--
-- select identifier from resto.features GROUP BY identifier HAVING COUNT(*) > 1;
-- select distinct(identifier), productidentifier, published from resto.features where identifier in (select identifier from resto.features GROUP BY identifier HAVING COUNT(*) > 1);
--
--
-- http://docs.postgresqlfr.org/9.4/ddl-inherit.html
--
-- Il existe une réelle limitation à la fonctionnalité d'héritage : 
-- les index (dont les contraintes d'unicité) et les contraintes de clés étrangères ne s'appliquent 
-- qu'aux tables mères, pas à leurs héritiers
--
ALTER TABLE _s1.features ADD CONSTRAINT _s1_features_identifier_key UNIQUE (identifier);
ALTER TABLE _s2.features ADD CONSTRAINT _s2_features_identifier_key UNIQUE (identifier);
ALTER TABLE _s2st.features ADD CONSTRAINT _s2st_features_identifier_key UNIQUE (identifier);
ALTER TABLE _s3.features ADD CONSTRAINT _s3_features_identifier_key UNIQUE (identifier);


--
-- FT376 - Performance
--


