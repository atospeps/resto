--
-- VERSION 2.1 de PEPS
--

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
-- Suppression colonne 'wps' table 'rights'
--
------------------------------------------------------------------------------------------- 
SELECT f_drop_col('usermanagement.rights', 'wps');
