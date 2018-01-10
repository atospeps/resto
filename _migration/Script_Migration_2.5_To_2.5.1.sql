--
-- VERSION 2.1 de PEPS
--

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
-- suppression d'une colonne
--
CREATE OR REPLACE function f_drop_col(_tbl regclass, _col  text)
  RETURNS bool AS
$func$
BEGIN
      EXECUTE 'ALTER TABLE ' || _tbl || ' DROP COLUMN IF EXISTS ' || _col;
      RETURN TRUE;
END
$func$  LANGUAGE plpgsql;


-- ----------------------------------------------------------------------------------------
--
-- Table 'rights'
--                       suppression colonne 'wps'
--
------------------------------------------------------------------------------------------- 
SELECT f_drop_col('usermanagement.rights', 'wps');

-- ----------------------------------------------------------------------------------------
--
-- Table 'wps-results'
--                       ajout colonne 'userinfo'
--
------------------------------------------------------------------------------------------- 
SELECT f_add_col('usermanagement.wps_results', 'userinfo', 'json');

-- ----------------------------------------------------------------------------------------
--
-- Table 'resto.keywords'
--                       ajout mots clés L1C, L2A
--
------------------------------------------------------------------------------------------- 
INSERT INTO resto.keywords (name, value, lang, type) VALUES ('L1C', 'level1c', '**', 'processingLevel');
INSERT INTO resto.keywords (name, value, lang, type) VALUES ('L2A', 'level2a', '**', 'processingLevel');

-- ----------------------------------------------------------------------------------------
--
-- Table 'resto.osdescriptions'
--                       Mis à jour champs Developper dans describe.xml
--
-------------------------------------------------------------------------------------------
UPDATE resto.osdescriptions SET developper = 'CNES'

