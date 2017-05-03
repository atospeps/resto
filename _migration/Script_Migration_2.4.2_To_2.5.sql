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

-- ----------------------------------------------------------------------------------------
--
-- Jeu de données de test
--
------------------------------------------------------------------------------------------- 
INSERT INTO jobs (gid, email, identifier, querytime, status, statuslocation, percentcompleted, outputs, statusmessage) VALUES (173, 'admin', 'S2L1C_MERGE', '2016-05-10 17:02:45', 'ProcessFailed', 'http://172.24.218.59:8081/wps/outputs/pywps-4064edf4-16c0-11e6-b4ae-0242ac110003.xml', 100, NULL, NULL);
INSERT INTO jobs (gid, email, identifier, querytime, status, statuslocation, percentcompleted, outputs, statusmessage) VALUES (168, 'admin', 'S2L1C_MERGE', '2016-05-09 14:33:25', 'ProcessFailed', 'http://172.24.218.59:8081/wps/outputs/pywps-384df95a-15e2-11e6-95c3-0242ac110003.xml', 100, NULL, 'Failed to execute WPS process [S2L1C_MERGE]: Process CANCELED');
INSERT INTO jobs (gid, email, identifier, querytime, status, statuslocation, percentcompleted, outputs, statusmessage) VALUES (169, 'admin', 'S2L1C_MOSAIC', '2016-05-10 09:59:53', 'ProcessAccepted', 'http://localhost:4444/wps/outputs/pywps-2c3c68f6-1685-11e6-ad21-0242ac110001.xml', 0, NULL, 'Process S2L1C_MOSAIC accepted');
INSERT INTO jobs (gid, email, identifier, querytime, status, statuslocation, percentcompleted, outputs, statusmessage) VALUES (171, 'admin', 'S2L1C_MERGE', '2016-05-10 17:00:32', 'ProcessFailed', 'http://172.24.218.59:8081/wps/outputs/pywps-f1405cae-16bf-11e6-aeb1-0242ac110003.xml', 100, NULL, 'Failed to execute WPS process [S2L1C_MERGE]: Process CANCELED');
INSERT INTO jobs (gid, email, identifier, querytime, status, statuslocation, percentcompleted, outputs, statusmessage) VALUES (172, 'admin', 'S2L1C_MERGE', '2016-05-10 17:01:18', 'ProcessFailed', 'http://172.24.218.59:8081/wps/outputs/pywps-0c23f030-16c0-11e6-acf4-0242ac110003.xml', 100, NULL, NULL);
INSERT INTO jobs (gid, email, identifier, querytime, status, statuslocation, percentcompleted, outputs, statusmessage) VALUES (170, 'admin', 'S2L1C_MOSAIC', '2016-05-10 10:00:09', 'ProcessAccepted', 'http://localhost:4444/wps/outputs/pywps-370fcd40-1685-11e6-beba-0242ac110001.xml', 0, NULL, 'Process S2L1C_MOSAIC accepted');
INSERT INTO jobs (gid, email, identifier, querytime, status, statuslocation, percentcompleted, outputs, statusmessage) VALUES (165, 'admin', 'assyncprocess', '2016-03-24 14:31:04', 'ProcessSucceeded', 'http://172.24.218.59:8081/wps/outputs/pywps-a80fcc88-f1c4-11e5-9a51-0242ac110003.xml', 100, '[]', 'PyWPS Process assyncprocess successfully calculated');
INSERT INTO jobs (gid, email, identifier, querytime, status, statuslocation, percentcompleted, outputs, statusmessage) VALUES (163, 'admin', 'assyncprocess', '2016-03-24 14:13:46', 'ProcessSucceeded', 'http://172.24.218.59:8081/wps/outputs/pywps-3d030d94-f1c2-11e5-9cd5-0242ac110003.xml', 100, '[]', 'PyWPS Process assyncprocess successfully calculated');
INSERT INTO jobs (gid, email, identifier, querytime, status, statuslocation, percentcompleted, outputs, statusmessage) VALUES (167, 'admin', 'S2L1C_MERGE', '2016-03-24 15:22:59', 'ProcessSucceeded', 'http://172.24.218.59:8081/wps/outputs/pywps-e829bfd4-f1cb-11e5-ad0c-0242ac110003.xml', 100, '[{"identifier":"file_url","title":"Image url","type":"string","value":"http:\/\/172.24.218.59\/results\/S2A_OPER_PRD_MSIL1C_PDMC_20160128T061653_R105_V20160126T054720_20160126T054720.tif"}]', 'PyWPS Process S2L1C_MERGE successfully calculated');
INSERT INTO jobs (gid, email, identifier, querytime, status, statuslocation, percentcompleted, outputs, statusmessage) VALUES (164, 'admin', 'S2L1C_MERGE', '2016-03-24 14:14:52', 'ProcessSucceeded', 'http://172.24.218.59:8081/wps/outputs/pywps-64bc8a86-f1c2-11e5-b13f-0242ac110003.xml', 100, '[{"identifier":"file_url","title":"Image url","type":"string","value":"http:\/\/172.24.218.59:8080\/results\/S2A_OPER_PRD_MSIL1C_PDMC_20160128T061653_R105_V20160126T054720_20160126T054720.tif"}]', 'PyWPS Process S2L1C_MERGE successfully calculated');
INSERT INTO jobs (gid, email, identifier, querytime, status, statuslocation, percentcompleted, outputs, statusmessage) VALUES (166, 'admin', 'S2L1C_MERGE', '2016-03-24 14:46:29', 'ProcessSucceeded', 'http://172.24.218.59:8081/wps/outputs/pywps-cf20122c-f1c6-11e5-8861-0242ac110003.xml', 100, '[{"identifier":"file_url","title":"Image url","type":"string","value":"http:\/\/172.24.218.59\/results\/S2A_OPER_PRD_MSIL1C_PDMC_20160128T061653_R105_V20160126T054720_20160126T054720.tif"}]', 'PyWPS Process S2L1C_MERGE successfully calculated');
