--
-- VERSION 5.0 de PEPS
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



-- ---------------------------------------------------------------------------------
--
-- PEPS-FT-640
--
-- ---------------------------------------------------------------------------------

SELECT f_add_col('usermanagement.jobs', 'mailnotif', 'boolean');
ALTER TABLE usermanagement.jobs ALTER COLUMN mailnotif SET DEFAULT FALSE;

--
-- Perfs
--


DROP INDEX IF EXISTS usermanagement.usermanagement_jobs_wpsid_idx;
CREATE INDEX usermanagement_jobs_wpsid_idx ON usermanagement.jobs (substring(statuslocation from 'pywps-(.+)[.]xml')) where visible=TRUE;

DROP INDEX IF EXISTS usermanagement.usermanagement_users_userid_idx;
CREATE INDEX usermanagement_users_userid_idx ON usermanagement.users (userid) where activated = 1;

