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
	userid serial NOT NULL,
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
    acknowledge boolean DEFAULT false,
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
)
WITH (
  OIDS=FALSE
);
ALTER TABLE usermanagement.processingcart
  OWNER TO postgres;
GRANT ALL ON TABLE usermanagement.processingcart TO postgres;
GRANT SELECT, UPDATE, INSERT, DELETE ON TABLE usermanagement.processingcart TO resto;

/*-- INDEX SUR LA CLE ETRANGERE USERID --*/
CREATE INDEX fki_userid_fkey
  ON usermanagement.processingcart
  USING btree
  (userid);

-- ----------------------------------------------------------------------------------------
-- Jeu de données de test
--
------------------------------------------------------------------------------------------- 
INSERT INTO usermanagement.jobs (gid, userid, email, identifier, querytime, query, data, method, status, statuslocation, statusmessage, percentcompleted, outputs) VALUES (167, 1, 'admin', 'S2L1C_MERGE', '2016-03-24 15:22:59', NULL, NULL, NULL, 'ProcessSucceeded', 'http://172.24.218.59:8081/wps/outputs/pywps-e829bfd4-f1cb-11e5-ad0c-0242ac110003.xml', 'PyWPS Process S2L1C_MERGE successfully calculated', 100, '[{"identifier":"file_url","title":"Image url","type":"string","value":"http:\/\/172.24.218.59\/results\/S2A_OPER_PRD_MSIL1C_PDMC_20160128T061653_R105_V20160126T054720_20160126T054720.tif"}]');
INSERT INTO usermanagement.jobs (gid, userid, email, identifier, querytime, query, data, method, status, statuslocation, statusmessage, percentcompleted, outputs) VALUES (165, 1, 'admin', 'assyncprocess', '2016-03-24 14:31:04', NULL, NULL, NULL, 'ProcessSucceeded', 'http://172.24.218.59:8081/wps/outputs/pywps-a80fcc88-f1c4-11e5-9a51-0242ac110003.xml', 'PyWPS Process assyncprocess successfully calculated', 100, '[]');
INSERT INTO usermanagement.jobs (gid, userid, email, identifier, querytime, query, data, method, status, statuslocation, statusmessage, percentcompleted, outputs) VALUES (171, 1, 'admin', 'S2L1C_MERGE', '2016-05-10 17:00:32', NULL, NULL, NULL, 'ProcessFailed', 'http://172.24.218.59:8081/wps/outputs/pywps-f1405cae-16bf-11e6-aeb1-0242ac110003.xml', 'Failed to execute WPS process [S2L1C_MERGE]: Process CANCELED', 100, NULL);
INSERT INTO usermanagement.jobs (gid, userid, email, identifier, querytime, query, data, method, status, statuslocation, statusmessage, percentcompleted, outputs) VALUES (164, 1, 'admin', 'S2L1C_MERGE', '2016-03-24 14:14:52', NULL, NULL, NULL, 'ProcessSucceeded', 'http://172.24.218.59:8081/wps/outputs/pywps-64bc8a86-f1c2-11e5-b13f-0242ac110003.xml', 'PyWPS Process S2L1C_MERGE successfully calculated', 100, '[{"identifier":"file_url","title":"Image url","type":"string","value":"http:\/\/172.24.218.59:8080\/results\/S2A_OPER_PRD_MSIL1C_PDMC_20160128T061653_R105_V20160126T054720_20160126T054720.tif"}]');
INSERT INTO usermanagement.jobs (gid, userid, email, identifier, querytime, query, data, method, status, statuslocation, statusmessage, percentcompleted, outputs) VALUES (163, 1, 'admin', 'assyncprocess', '2016-03-24 14:13:46', NULL, NULL, NULL, 'ProcessSucceeded', 'http://172.24.218.59:8081/wps/outputs/pywps-3d030d94-f1c2-11e5-9cd5-0242ac110003.xml', 'PyWPS Process assyncprocess successfully calculated', 100, '[]');
INSERT INTO usermanagement.jobs (gid, userid, email, identifier, querytime, query, data, method, status, statuslocation, statusmessage, percentcompleted, outputs) VALUES (172, 1, 'admin', 'S2L1C_MERGE', '2016-05-10 17:01:18', NULL, NULL, NULL, 'ProcessFailed', 'http://172.24.218.59:8081/wps/outputs/pywps-0c23f030-16c0-11e6-acf4-0242ac110003.xml', NULL, 100, NULL);
INSERT INTO usermanagement.jobs (gid, userid, email, identifier, querytime, query, data, method, status, statuslocation, statusmessage, percentcompleted, outputs) VALUES (173, 1, 'admin', 'S2L1C_MERGE', '2016-05-10 17:02:45', NULL, NULL, NULL, 'ProcessFailed', 'http://172.24.218.59:8081/wps/outputs/pywps-4064edf4-16c0-11e6-b4ae-0242ac110003.xml', NULL, 100, NULL);
INSERT INTO usermanagement.jobs (gid, userid, email, identifier, querytime, query, data, method, status, statuslocation, statusmessage, percentcompleted, outputs) VALUES (169, 1, 'admin', 'S2L1C_MOSAIC', '2016-05-10 09:59:53', NULL, NULL, NULL, 'ProcessStarted', 'http://localhost:4444/wps/outputs/pywps-2c3c68f6-1685-11e6-ad21-0242ac110001.xml', 'Process S2L1C_MOSAIC accepted', 45, NULL);
INSERT INTO usermanagement.jobs (gid, userid, email, identifier, querytime, query, data, method, status, statuslocation, statusmessage, percentcompleted, outputs) VALUES (166, 1, 'admin', 'S2L1C_MERGE', '2016-03-24 14:46:29', NULL, NULL, NULL, 'ProcessSucceeded', 'http://172.24.218.59:8081/wps/outputs/pywps-cf20122c-f1c6-11e5-8861-0242ac110003.xml', 'PyWPS Process S2L1C_MERGE successfully calculated', 100, '[{"identifier":"file_url","title":"Image url","type":"string","value":"http:\/\/172.24.218.59\/results\/S2A_OPER_PRD_MSIL1C_PDMC_20160128T061653_R105_V20160126T054720_20160126T054720.tif"}]');
INSERT INTO usermanagement.jobs (gid, userid, email, identifier, querytime, query, data, method, status, statuslocation, statusmessage, percentcompleted, outputs) VALUES (170, 1, 'admin', 'S2L1C_MOSAIC', '2016-05-10 10:00:09', NULL, NULL, NULL, 'ProcessAccepted', 'http://localhost:4444/wps/outputs/pywps-370fcd40-1685-11e6-beba-0242ac110001.xml', 'Process S2L1C_MOSAIC accepted', 0, NULL);
INSERT INTO usermanagement.jobs (gid, userid, email, identifier, querytime, query, data, method, status, statuslocation, statusmessage, percentcompleted, outputs) VALUES (168, 1, 'admin', 'S2L1C_MERGE', '2016-05-09 14:33:25', NULL, NULL, NULL, 'ProcessFailed', 'http://172.24.218.59:8081/wps/outputs/pywps-384df95a-15e2-11e6-95c3-0242ac110003.xml', 'Failed to execute WPS process [S2L1C_MERGE]: Process CANCELED', 100, NULL);
