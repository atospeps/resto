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
---
--- Table 'jobs'
---                       
---
-------------------------------------------------------------------------------------------- 
SELECT f_add_col('usermanagement.jobs', 'logs', 'text');
SELECT f_drop_col('usermanagement.jobs', 'email');

--DROP INDEX IF EXISTS usermanagement.idx_jobs_querytime;
--CREATE INDEX idx_jobs_querytime ON usermanagement.jobs (querytime DESC);

-- ----------------------------------------------------------------------------------------
--
-- Table 'rights'
--                      
--
------------------------------------------------------------------------------------------- 
SELECT f_drop_col('usermanagement.rights', 'wps');


-- ----------------------------------------------------------------------------------------
--
-- Table 'resto.keywords'
--                       
--
------------------------------------------------------------------------------------------- 
INSERT INTO resto.keywords (name, value, lang, type) VALUES ('L1C', 'level1c', '**', 'processingLevel');
INSERT INTO resto.keywords (name, value, lang, type) VALUES ('L2A', 'level2a', '**', 'processingLevel');

-- ----------------------------------------------------------------------------------------
--
-- Table 'resto.osdescriptions'
--                                  FT-489
--
-------------------------------------------------------------------------------------------
UPDATE resto.osdescriptions SET developper = 'CNES'


-- ----------------------------------------------------------------------------------------
-- 
-- PEPS-FT-638 
-- Les datatakeID des produits S2 sont manquants
--
-- ----------------------------------------------------------------------------------------
UPDATE _s2st.features SET s2takeid='G' || substr(title, 1, 4) || substr(title, 12, 15) || to_char(orbitnumber, '000000') || '_N' || substr(title, 29, 2) || '.' || substr(title, 31, 2) where s2takeid IS NULL OR s2takeid='';

