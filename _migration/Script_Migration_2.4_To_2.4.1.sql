--
-- VERSION 1.3.2.1 de PEPS
--

--
-- PEPS-FT-446
--
DROP INDEX IF EXISTS resto.resto_features_startdate_idx;
CREATE INDEX resto_features_startdate_idx ON resto.features  (startdate DESC, identifier);

DROP INDEX IF EXISTS _s1._s1_features_startdate_idx;
CREATE INDEX _s1_features_startdate_idx ON _s1.features  (startdate DESC, identifier);

DROP INDEX IF EXISTS _s2._s2_features_startdate_idx;
CREATE INDEX _s2_features_startdate_idx ON _s2.features  (startdate DESC, identifier);

DROP INDEX IF EXISTS _s2st._s2st_features_startdate_idx;
CREATE INDEX _s2st_features_startdate_idx ON _s2st.features  (startdate DESC, identifier);

DROP INDEX IF EXISTS _s3._s3_features_startdate_idx;
CREATE INDEX _s3_features_startdate_idx ON _s3.features  (startdate DESC, identifier);

--
-- PEPS-FT-458
-- PEPS-FT-291
--

CREATE OR REPLACE FUNCTION ST_SplitDateLine(geom_in geometry)
RETURNS geometry AS $$
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
$$ LANGUAGE 'plpgsql' IMMUTABLE;


--
-- PEPS-FT-477
--
ALTER TABLE resto.features ALTER COLUMN relativeorbitnumber TYPE integer USING (relativeorbitnumber::integer);
ALTER TABLE _s1.features ALTER COLUMN cyclenumber TYPE integer USING (cyclenumber::integer);
ALTER TABLE _s3.features ALTER COLUMN cyclenumber TYPE integer USING (cyclenumber::integer);

--
-- PEPS-FT-402
--

-- ajout de colonnes pour la gestion des produits S3

CREATE OR REPLACE function f_add_col(_tbl regclass, _col  text, _type regtype)
  RETURNS bool AS
$func$
BEGIN
   IF EXISTS (SELECT 1 FROM pg_attribute
              WHERE  attrelid = _tbl
              AND    attname = _col
              AND    NOT attisdropped) THEN
      RETURN FALSE;
   ELSE
      EXECUTE 'ALTER TABLE ' || _tbl || ' ADD COLUMN ' || _col || ' ' || _type;
      RETURN TRUE;
   END IF;
END
$func$  LANGUAGE plpgsql;



SELECT f_add_col('_s3.features', 'approxSize', 'TEXT');
SELECT f_add_col('_s3.features', 'ecmwfType', 'TEXT');
SELECT f_add_col('_s3.features', 'processingName', 'TEXT');
SELECT f_add_col('_s3.features', 'onlineQualityCheck', 'TEXT');

--
-- PEPS-FT-334
--
SELECT f_add_col('resto.features', 'checksum', 'TEXT');

--
-- PEPS-FT-376 - Optimisation Postgresql
--

--
-- Search index on property published
-- ---------------------------------------------------------------------------------
DROP INDEX IF EXISTS resto.resto_features_published_idx;
CREATE INDEX resto_features_published_idx ON resto.features (published DESC);

DROP INDEX IF EXISTS _s1._s1_features_published_idx;
CREATE INDEX _s1_features_published_idx ON _s1.features (published DESC);

DROP INDEX IF EXISTS _s2._s2_features_published_idx;
CREATE INDEX _s2_features_published_idx ON _s2.features (published DESC);

DROP INDEX IF EXISTS _s2st._s2st_features_published_idx;
CREATE INDEX _s2st_features_published_idx ON _s2st.features (published DESC);

DROP INDEX IF EXISTS _s3._s3_features_published_idx;
CREATE INDEX _s3_features_published_idx ON _s3.features (published DESC);

--
-- Search index on property sensormode
-- ---------------------------------------------------------------------------------
DROP INDEX IF EXISTS resto.resto_features_sensormode_idx;
CREATE INDEX resto_features_sensormode_idx ON resto.features (sensormode DESC);

DROP INDEX IF EXISTS _s1._s1_features_sensormode_idx;
CREATE INDEX _s1_features_sensormode_idx ON _s1.features (sensormode DESC);

DROP INDEX IF EXISTS _s2._s2_features_sensormode_idx;
CREATE INDEX _s2_features_sensormode_idx ON _s2.features (sensormode DESC);

DROP INDEX IF EXISTS _s2st._s2st_features_sensormode_idx;
CREATE INDEX _s2st_features_sensormode_idx ON _s2st.features (sensormode DESC);

DROP INDEX IF EXISTS _s3._s3_features_sensormode_idx;
CREATE INDEX _s3_features_sensormode_idx ON _s3.features (sensormode DESC);

--
-- Indexes for requests which manages obsolescence
-- ---------------------------------------------------------------------------------

--
-- dhusingestdate
--
DROP INDEX IF EXISTS resto.resto_features_dhusingestdate_idx;
CREATE INDEX resto_features_dhusingestdate_idx ON resto.features (dhusingestdate DESC);

DROP INDEX IF EXISTS _s1._s1_features_dhusingestdate_idx;
CREATE INDEX _s1_features_dhusingestdate_idx ON _s1.features (dhusingestdate DESC);

DROP INDEX IF EXISTS _s2._s2_features_dhusingestdate_idx;
CREATE INDEX _s2_features_dhusingestdate_idx ON _s2.features (dhusingestdate DESC);

DROP INDEX IF EXISTS _s2st._s2st_features_dhusingestdate_idx;
CREATE INDEX _s2st_features_dhusingestdate_idx ON _s2st.features (dhusingestdate DESC);

DROP INDEX IF EXISTS _s3._s3_features_dhusingestdate_idx;
CREATE INDEX _s3_features_dhusingestdate_idx ON _s3.features (dhusingestdate DESC);

-- productidentifier
-- 
DROP INDEX IF EXISTS resto.resto_features_productidentifier_idx;
CREATE INDEX resto_features_productidentifier_idx ON resto.features (productidentifier DESC);

DROP INDEX IF EXISTS _s1._s1_features_productidentifier_idx;
CREATE INDEX _s1_features_productidentifier_idx ON _s1.features (productidentifier DESC);

DROP INDEX IF EXISTS _s2._s2_features_productidentifier_idx;
CREATE INDEX _s2_features_productidentifier_idx ON _s2.features (productidentifier DESC);

DROP INDEX IF EXISTS _s2st._s2st_features_productidentifier_idx;
CREATE INDEX _s2st_features_productidentifier_idx ON _s2st.features (productidentifier DESC);

DROP INDEX IF EXISTS _s3._s3_features_productidentifier_idx;
CREATE INDEX _s3_features_productidentifier_idx ON _s3.features (productidentifier DESC);

--
-- Admin module
-- ----------------------------------------------------------------------------------

--
-- querytime
--
DROP INDEX IF EXISTS usermanagement.history_querytime_idx;
CREATE INDEX history_querytime_idx ON usermanagement.history (querytime DESC);


--
-- Opération de maintenance
-- Récupération de l'espace de stockage occupé par des lignes supprimées et collecte des statistiques Postgresql
--
 
vacuum analyse resto.keywords;
vacuum analyse _s1.features;
vacuum analyse _s2.features;
vacuum analyse _s2st.features;
vacuum analyse _s3.features;
vacuum analyse resto.features;
