/* Script de migration de la base de données Resto de la version 1.1 vers 1.2 */
/* Change user postgres with your database admin user, and change user resto with your database resto user */
/* Change the default value of instantdownaloadlimit and weeklydownloadlimit (value in MegaOctet) */
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
   ALTER TABLE ' || _tbl || ' ADD COLUMN ' || quote_ident(_col) || ' ' || _type;
   success := TRUE;
END IF;

END
$func$;

SELECT f_add_col('usermanagement.users', 'organization', 'text');

SELECT f_add_col('usermanagement.users', 'nationality', 'text');

SELECT f_add_col('usermanagement.users', 'domain', 'text');

SELECT f_add_col('usermanagement.users', 'use', 'text');

SELECT f_add_col('usermanagement.users', 'adress', 'text');

SELECT f_add_col('usermanagement.users', 'numtel', 'text');

SELECT f_add_col('usermanagement.users', 'numfax', 'text');

SELECT f_add_col('usermanagement.users', 'instantdownloadvolume', 'integer');

SELECT f_add_col('usermanagement.users', 'weeklydownloadvolume', 'text');

SELECT f_add_col('usermanagement.rights', 'productidentifier', 'text');

SELECT f_add_col('usermanagement.sharedlinks', 'email', 'text');

CREATE TABLE IF NOT EXISTS usermanagement.groups
(
  gid serial NOT NULL,
  groupname text NOT NULL,
  description text,
  CONSTRAINT groups_pkey PRIMARY KEY (gid),
  CONSTRAINT groups_groupname_key UNIQUE (groupname)
);
CREATE INDEX idx_groupname_groups ON usermanagement.groups (groupname);

CREATE INDEX idx_identifier_features ON resto.features (identifier);

ALTER TABLE usermanagement.groups OWNER TO postgres;
GRANT ALL ON TABLE usermanagement.groups TO postgres;
GRANT SELECT, UPDATE, INSERT, DELETE ON TABLE usermanagement.groups TO resto;

GRANT ALL ON SEQUENCE usermanagement.groups_gid_seq TO postgres;
GRANT SELECT, UPDATE ON SEQUENCE usermanagement.groups_gid_seq TO resto;

ALTER TABLE resto.features ALTER COLUMN resource_size TYPE bigint;

UPDATE usermanagement.users  SET instantdownloadvolume=1000, weeklydownloadvolume=7000 WHERE instantdownloadvolume IS NULL OR weeklydownloadvolume IS NULL;