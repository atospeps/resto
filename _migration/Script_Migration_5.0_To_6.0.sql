--
-- VERSION 6.0 de PEPS
--

-- ajout d'une colonne
--
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

--
-- PEPS-FT-765 - Requête OpenSearch très longue
--

DROP INDEX IF EXISTS _s2st._s2st_features_mgrs_idx;
CREATE INDEX _s2st_features_mgrs_idx ON _s2st.features (mgrs DESC);

--
-- PEPS-FT-773 - Prise en compte de liens mapserver dans les résultats des traitements
--
SELECT f_add_col('usermanagement.jobs', 'wms', 'text');

-- 
-- PEPS-FT-785
--
UPDATE resto.osdescriptions set attribution='Distributed by CNES. Copernicus data following ESA 2014-2018 - TERMS AND CONDITIONS';


