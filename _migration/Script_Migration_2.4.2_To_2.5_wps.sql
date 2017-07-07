--
-- VERSION 2.0 de PEPS
--
-- WPS
--

/* Script de migration beta*/

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
ALTER TABLE usermanagement.groups ALTER COLUMN canwps SET DEFAULT FALSE;
UPDATE usermanagement.groups SET canwps = FALSE;
UPDATE usermanagement.groups SET canwps = TRUE WHERE groupname = 'admin';

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


-- ----------------------------------------------------------------------------------------
--
-- Creates TABLE usermanagement.processingcart
--
------------------------------------------------------------------------------------------- 

/*-- SEQUENCE --*/
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

/*-- TABLE --*/
CREATE TABLE usermanagement.processingcart
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

/*-- INDEX SUR LA CLE ETRANGERE USERID --*/
CREATE INDEX fki_userid_fkey ON usermanagement.processingcart USING btree (userid);


-- Table: usermanagement.wps_results

-- DROP TABLE usermanagement.wps_results;

CREATE TABLE usermanagement.wps_results
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
)
WITH (
  OIDS=FALSE
);
ALTER TABLE usermanagement.wps_results OWNER TO resto;


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

CREATE INDEX idx_loginpwd_proactive
  ON usermanagement.proactive
  USING btree
  (login COLLATE pg_catalog."default", password COLLATE pg_catalog."default");

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
ALTER TABLE usermanagement.wpsrights_gid_seq OWNER TO postgres;
GRANT ALL ON SEQUENCE usermanagement.wpsrights_gid_seq TO postgres;
GRANT SELECT, UPDATE ON SEQUENCE usermanagement.wpsrights_gid_seq TO resto;

CREATE TABLE IF NOT EXISTS usermanagement.wpsrights
(
  wpsrightsid serial NOT NULL DEFAULT nextval('usermanagement.wpsrights_gid_seq'::regclass),
  groupid serial NOT NULL
  identifier text NOT NULL,
  CONSTRAINT wpsrights_pkey PRIMARY KEY (gid)
);
ALTER TABLE usermanagement.wpsrights OWNER TO resto;
GRANT ALL ON TABLE usermanagement.wpsrights TO resto;
GRANT ALL ON TABLE usermanagement.wpsrights TO postgres;

CREATE INDEX idx_identifier_wpsrights
  ON usermanagement.wpsrights
  USING btree
  (identifier COLLATE pg_catalog."default");

-- ----------------------------------------------------------------------------------------
-- Jeu de données de test
--
------------------------------------------------------------------------------------------- 
INSERT INTO usermanagement.jobs (gid, userid, identifier, querytime, data, method, status, statuslocation, statusmessage, percentcompleted, outputs) VALUES (167, 1, 'S2L1C_MERGE', '2016-03-24 15:22:59', NULL, NULL, 'ProcessSucceeded', 'http://172.24.218.59:8081/wps/outputs/pywps-e829bfd4-f1cb-11e5-ad0c-0242ac110003.xml', 'PyWPS Process S2L1C_MERGE successfully calculated', 100, '[{"identifier":"file_url","title":"Image url","type":"string","value":"http:\/\/172.24.218.59\/results\/S2A_OPER_PRD_MSIL1C_PDMC_20160128T061653_R105_V20160126T054720_20160126T054720.tif"}]');
INSERT INTO usermanagement.jobs (gid, userid, identifier, querytime, data, method, status, statuslocation, statusmessage, percentcompleted, outputs) VALUES (165, 1, 'assyncprocess', '2016-03-24 14:31:04', NULL, NULL, 'ProcessSucceeded', 'http://172.24.218.59:8081/wps/outputs/pywps-a80fcc88-f1c4-11e5-9a51-0242ac110003.xml', 'PyWPS Process assyncprocess successfully calculated', 100, '[]');
INSERT INTO usermanagement.jobs (gid, userid, identifier, querytime, data, method, status, statuslocation, statusmessage, percentcompleted, outputs) VALUES (171, 1, 'S2L1C_MERGE', '2016-05-10 17:00:32', NULL, NULL, 'ProcessFailed', 'http://172.24.218.59:8081/wps/outputs/pywps-f1405cae-16bf-11e6-aeb1-0242ac110003.xml', 'Failed to execute WPS process [S2L1C_MERGE]: Process CANCELED', 100, NULL);
INSERT INTO usermanagement.jobs (gid, userid, identifier, querytime, data, method, status, statuslocation, statusmessage, percentcompleted, outputs) VALUES (164, 1, 'S2L1C_MERGE', '2016-03-24 14:14:52', NULL, NULL, 'ProcessSucceeded', 'http://172.24.218.59:8081/wps/outputs/pywps-64bc8a86-f1c2-11e5-b13f-0242ac110003.xml', 'PyWPS Process S2L1C_MERGE successfully calculated', 100, '[{"identifier":"file_url","title":"Image url","type":"string","value":"http:\/\/172.24.218.59:8080\/results\/S2A_OPER_PRD_MSIL1C_PDMC_20160128T061653_R105_V20160126T054720_20160126T054720.tif"}]');
INSERT INTO usermanagement.jobs (gid, userid, identifier, querytime, data, method, status, statuslocation, statusmessage, percentcompleted, outputs) VALUES (163, 1, 'assyncprocess', '2016-03-24 14:13:46', NULL, NULL, 'ProcessSucceeded', 'http://172.24.218.59:8081/wps/outputs/pywps-3d030d94-f1c2-11e5-9cd5-0242ac110003.xml', 'PyWPS Process assyncprocess successfully calculated', 100, '[]');
INSERT INTO usermanagement.jobs (gid, userid, identifier, querytime, data, method, status, statuslocation, statusmessage, percentcompleted, outputs) VALUES (172, 1, 'S2L1C_MERGE', '2016-05-10 17:01:18', NULL, NULL, 'ProcessFailed', 'http://172.24.218.59:8081/wps/outputs/pywps-0c23f030-16c0-11e6-acf4-0242ac110003.xml', NULL, 100, NULL);
INSERT INTO usermanagement.jobs (gid, userid, identifier, querytime, data, method, status, statuslocation, statusmessage, percentcompleted, outputs) VALUES (173, 1, 'S2L1C_MERGE', '2016-05-10 17:02:45', NULL, NULL, 'ProcessFailed', 'http://172.24.218.59:8081/wps/outputs/pywps-4064edf4-16c0-11e6-b4ae-0242ac110003.xml', NULL, 100, NULL);
INSERT INTO usermanagement.jobs (gid, userid, identifier, querytime, data, method, status, statuslocation, statusmessage, percentcompleted, outputs) VALUES (169, 1, 'S2L1C_MOSAIC', '2016-05-10 09:59:53', NULL, NULL, 'ProcessStarted', 'http://localhost:4444/wps/outputs/pywps-2c3c68f6-1685-11e6-ad21-0242ac110001.xml', 'Process S2L1C_MOSAIC accepted', 45, NULL);
INSERT INTO usermanagement.jobs (gid, userid, identifier, querytime, data, method, status, statuslocation, statusmessage, percentcompleted, outputs) VALUES (166, 1, 'S2L1C_MERGE', '2016-03-24 14:46:29', NULL, NULL, 'ProcessSucceeded', 'http://172.24.218.59:8081/wps/outputs/pywps-cf20122c-f1c6-11e5-8861-0242ac110003.xml', 'PyWPS Process S2L1C_MERGE successfully calculated', 100, '[{"identifier":"file_url","title":"Image url","type":"string","value":"http:\/\/172.24.218.59\/results\/S2A_OPER_PRD_MSIL1C_PDMC_20160128T061653_R105_V20160126T054720_20160126T054720.tif"}]');
INSERT INTO usermanagement.jobs (gid, userid, identifier, querytime, data, method, status, statuslocation, statusmessage, percentcompleted, outputs) VALUES (170, 1, 'S2L1C_MOSAIC', '2016-05-10 10:00:09', NULL, NULL, 'ProcessAccepted', 'http://localhost:4444/wps/outputs/pywps-370fcd40-1685-11e6-beba-0242ac110001.xml', 'Process S2L1C_MOSAIC accepted', 0, NULL);
INSERT INTO usermanagement.jobs (gid, userid, identifier, querytime, data, method, status, statuslocation, statusmessage, percentcompleted, outputs) VALUES (168, 1, 'S2L1C_MERGE', '2016-05-09 14:33:25', NULL, NULL, 'ProcessFailed', 'http://172.24.218.59:8081/wps/outputs/pywps-384df95a-15e2-11e6-95c3-0242ac110003.xml', 'Failed to execute WPS process [S2L1C_MERGE]: Process CANCELED', 100, NULL);
