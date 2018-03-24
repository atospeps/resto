--
-- VERSION 2.0 de PEPS
--
-- WPS
--

-- ----------------------------------------------------------------------------------------
--
-- Updates TABLE usermanagement.groups: add 'canwps' column
--
------------------------------------------------------------------------------------------- 
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

SELECT f_add_col('usermanagement.groups', 'canwps', 'boolean');
SELECT f_add_col('usermanagement.groups', 'proactiveid', 'integer');
ALTER TABLE usermanagement.groups ALTER COLUMN canwps SET DEFAULT FALSE;
UPDATE usermanagement.groups SET canwps = FALSE;
UPDATE usermanagement.groups SET canwps = TRUE WHERE groupname = 'admin';


DROP TABLE IF EXISTS usermanagement.jobs CASCADE;
DROP TABLE IF EXISTS usermanagement.wps_results CASCADE;
DROP TABLE IF EXISTS usermanagement.processingcart CASCADE;
DROP TABLE IF EXISTS usermanagement.wpsrights CASCADE;

DROP SEQUENCE IF EXISTS usermanagement.processingcart_gid_seq CASCADE;
DROP SEQUENCE IF EXISTS usermanagement.proactiveid_seq CASCADE;
DROP SEQUENCE IF EXISTS usermanagement.wpsrights_gid_seq CASCADE;

-- ----------------------------------------------------------------------------------------
--
-- Creates TABLE usermanagement.jobs in order to manage user's instances of WPS services.
--
------------------------------------------------------------------------------------------- 

CREATE TABLE IF NOT EXISTS usermanagement.jobs
(
	gid serial NOT NULL,
	userid serial NOT NULL,
	title text,
  	identifier text NOT NULL,
  	querytime timestamp without time zone,
  	data text,
  	method text,
  	status text,
  	statuslocation text,
  	statusmessage text,
  	statustime timestamp without time zone,
  	percentcompleted integer DEFAULT 0,
  	nbresults integer,
    acknowledge boolean DEFAULT false,
    last_dispatch timestamp without time zone,
    visible boolean DEFAULT true,
  	CONSTRAINT jobs_pkey PRIMARY KEY (gid)
);

ALTER TABLE usermanagement.jobs OWNER TO resto;
GRANT ALL ON TABLE usermanagement.jobs TO postgres;
GRANT ALL ON TABLE usermanagement.jobs TO resto;

GRANT ALL ON SEQUENCE usermanagement.jobs_gid_seq TO postgres;
GRANT SELECT, UPDATE ON SEQUENCE usermanagement.jobs_gid_seq TO resto;

DROP INDEX IF EXISTS usermanagement.jobs_userid_idx;
CREATE INDEX jobs_userid_idx ON usermanagement.jobs (userid DESC);

DROP INDEX IF EXISTS usermanagement.jobs_querytime_idx;
--CREATE INDEX jobs_querytime_idx ON usermanagement.jobs (querytime DESC) where visible=true;

DROP INDEX IF EXISTS usermanagement.jobs_last_dispatch_idx;
--CREATE INDEX jobs_last_dispatch_idx ON usermanagement.jobs (last_dispatch DESC);

DROP INDEX IF EXISTS usermanagement.jobs_statuslocation_idx;
CREATE INDEX jobs_statuslocation_idx ON usermanagement.jobs (statuslocation DESC);

DROP INDEX IF EXISTS usermanagement._usermanagement_jobs_stats_idx;
CREATE INDEX _usermanagement_jobs_stats_idx ON usermanagement.jobs USING btree (querytime DESC, userid) where visible=true and acknowledge = FALSE AND (status = 'ProcessSucceeded' OR status = 'ProcessFailed');

DROP INDEX IF EXISTS usermanagement._usermanagement_jobs_list_idx;
CREATE INDEX _usermanagement_jobs_list_idx ON usermanagement.jobs USING btree (querytime DESC, userid) where visible=true;

-- ----------------------------------------------------------------------------------------
--
-- Creates TABLE usermanagement.processingcart
--
------------------------------------------------------------------------------------------- 
--
-- SEQUENCE --
--

CREATE SEQUENCE usermanagement.processingcart_gid_seq
  INCREMENT 1
  MINVALUE 1
  MAXVALUE 9223372036854775807
  START 1
  CACHE 1;
ALTER TABLE usermanagement.processingcart_gid_seq
  OWNER TO postgres;
GRANT ALL ON SEQUENCE usermanagement.processingcart_gid_seq TO postgres;
GRANT SELECT, UPDATE ON SEQUENCE usermanagement.processingcart_gid_seq TO resto;

--
-- TABLE --
--

CREATE TABLE IF NOT EXISTS usermanagement.processingcart
(
  gid integer NOT NULL DEFAULT nextval('usermanagement.processingcart_gid_seq'::regclass),
  userid integer NOT NULL,
  itemid text NOT NULL,
  title text,
  querytime timestamp without time zone,
  CONSTRAINT processingcart_pkey PRIMARY KEY (gid),
  CONSTRAINT userid_fkey FOREIGN KEY (userid)
      REFERENCES usermanagement.users (userid) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION
);

ALTER TABLE usermanagement.processingcart OWNER TO postgres;
GRANT ALL ON TABLE usermanagement.processingcart TO postgres;
GRANT SELECT, UPDATE, INSERT, DELETE ON TABLE usermanagement.processingcart TO resto;

--
-- INDEX SUR LA CLE ETRANGERE USERID --
--
DROP INDEX IF EXISTS usermanagement.processingcart_userid_idx;
CREATE INDEX processingcart_userid_idx ON usermanagement.processingcart (userid);


-- Table: usermanagement.wps_results

-- DROP TABLE usermanagement.wps_results;

CREATE TABLE IF NOT EXISTS usermanagement.wps_results
(
  uid serial NOT NULL,
  userid serial NOT NULL,
  identifier text,
  type text,
  value text,
  jobid serial NOT NULL,
  CONSTRAINT wps_results_pkey PRIMARY KEY (uid),
  CONSTRAINT wps_results_jobid_fkey FOREIGN KEY (jobid)
      REFERENCES usermanagement.jobs (gid) MATCH SIMPLE
      ON UPDATE RESTRICT ON DELETE CASCADE
);
ALTER TABLE usermanagement.wps_results OWNER TO resto;

DROP INDEX IF EXISTS usermanagement.wps_results_userid_idx;
CREATE INDEX wps_results_userid_idx ON usermanagement.wps_results (userid DESC);

DROP INDEX IF EXISTS usermanagement.wps_results_jobid_idx;
CREATE INDEX wps_results_jobid_idx ON usermanagement.wps_results (jobid DESC);

DROP INDEX IF EXISTS usermanagement.wps_results_value_idx;
CREATE INDEX wps_results_value_idx ON usermanagement.wps_results (value DESC);

-- ----------------------------------------------------------------------------------------
--
-- Creates TABLE usermanagement.proactive
--
------------------------------------------------------------------------------------------- 

CREATE SEQUENCE usermanagement.proactiveid_seq
  INCREMENT 1
  MINVALUE 1
  MAXVALUE 9223372036854775807
  START 1
  CACHE 1;
ALTER TABLE usermanagement.proactiveid_seq OWNER TO postgres;
GRANT ALL ON SEQUENCE usermanagement.proactiveid_seq TO postgres;
GRANT SELECT, UPDATE ON SEQUENCE usermanagement.proactiveid_seq TO resto;

--
-- TABLE --
--
CREATE TABLE IF NOT EXISTS usermanagement.proactive
(
  proactiveid integer NOT NULL DEFAULT nextval('usermanagement.proactiveid_seq'::regclass),
  login text NOT NULL,
  password text NOT NULL,
  CONSTRAINT proactive_pkey PRIMARY KEY (proactiveid)
);
ALTER TABLE usermanagement.proactive OWNER TO resto;
GRANT ALL ON TABLE usermanagement.proactive TO resto;
GRANT ALL ON TABLE usermanagement.proactive TO postgres;

DROP INDEX IF EXISTS usermanagement.proactive_login_idx;
CREATE INDEX proactive_login_idx ON usermanagement.proactive (login);

-- ----------------------------------------------------------------------------------------
--
-- Creates TABLE usermanagement.wpsrights
--
------------------------------------------------------------------------------------------- 

CREATE SEQUENCE usermanagement.wpsrights_gid_seq
  INCREMENT 1
  MINVALUE 1
  MAXVALUE 9223372036854775807
  START 1
  CACHE 1;

--
-- TABLE --
--
CREATE TABLE IF NOT EXISTS usermanagement.wpsrights
(
  wpsrightsid integer NOT NULL DEFAULT nextval('usermanagement.wpsrights_gid_seq'::regclass),
  identifier text NOT NULL,
  groupid serial NOT NULL,
  CONSTRAINT wpsrights_pkey PRIMARY KEY (wpsrightsid)
);
  
ALTER TABLE usermanagement.wpsrights_gid_seq OWNER TO resto;
GRANT ALL ON SEQUENCE usermanagement.wpsrights_gid_seq TO postgres;
GRANT SELECT, UPDATE ON SEQUENCE usermanagement.wpsrights_gid_seq TO resto;

ALTER TABLE usermanagement.wpsrights OWNER TO resto;
GRANT ALL ON TABLE usermanagement.wpsrights TO resto;
GRANT ALL ON TABLE usermanagement.wpsrights TO postgres;

DROP INDEX IF EXISTS usermanagement.wpsrights_identifier_idx;
CREATE INDEX wpsrights_identifier_idx ON usermanagement.wpsrights (identifier);
