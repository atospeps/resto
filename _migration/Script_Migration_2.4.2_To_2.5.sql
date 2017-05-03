--
--
-- VERSION 2.0 de PEPS

/* Script de migration beta*/

CREATE OR REPLACE function f_add_col(
   _tbl regclass, _col  text, _type regtype, OUT success bool)
    LANGUAGE plpgsql AS
$func$
BEGIN

IF EXISTS (
   SELECT 1 FROM pg_attribute
   WHERE  attrelid = _tbl
   AND    attname = _col
   AND    NOT attisdropped) THEN
   success := FALSE;

ELSE
   EXECUTE '
   ALTER TABLE ' || _tbl || ' ADD COLUMN ' || _col || ' ' || _type;
   success := TRUE;
END IF;

END
$func$;

SELECT f_add_col('usermanagement.rights', 'wps', 'integer');
ALTER TABLE usermanagement.rights ALTER COLUMN wps SET DEFAULT 0;


-- ----------------------------------------------------------------------------------------
--
-- Creates TABLE usermanagement.jobs in order to manage user's instances of WPS services.
--
------------------------------------------------------------------------------------------- 
CREATE TABLE IF NOT EXISTS usermanagement.jobs
(
	gid serial NOT NULL,
	email text,
  	identifier text NOT NULL,
  	querytime timestamp without time zone,
  	query text,
  	data text,
  	method text,
  	status text,
  	statuslocation text,
  	statusmessage text,
  	percentcompleted integer DEFAULT 0,
  	outputs text,
  	CONSTRAINT jobs_pkey PRIMARY KEY (gid)
);

ALTER TABLE usermanagement.jobs OWNER TO postgres;
GRANT ALL ON TABLE usermanagement.jobs TO postgres;
GRANT ALL ON TABLE usermanagement.jobs TO resto;

GRANT ALL ON SEQUENCE usermanagement.jobs_gid_seq TO postgres;
GRANT SELECT, UPDATE ON SEQUENCE usermanagement.jobs_gid_seq TO resto;

