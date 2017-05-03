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

