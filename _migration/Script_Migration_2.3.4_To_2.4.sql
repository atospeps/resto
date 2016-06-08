-- VERSION 1.3.2 de PEPS
-- Modification de l'adresse mail et developpeur pour les collections S1 et S2
-- Ajout de la table files (gestion des fichiers)

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

update resto.osdescriptions set developper='Atos', contact='exppeps@cnes.fr' where collection IN ('S1', 'S2');

SELECT f_add_col('resto.features', 'orbitnumberrelative', 'integer');
SELECT f_add_col('resto.features', 'cyclenumber', 'integer');

ALTER TABLE resto.features
   RENAME orbitnumber  TO orbitnumberabsolute;

CREATE TABLE usermanagement.files
(
  gid serial NOT NULL,
  email text,
  jobid integer,
  name text,
  type text,
  path text,
  date timestamp without time zone,
  size bigint,
  format text,
  CONSTRAINT files_pkey PRIMARY KEY (gid)
)
WITH (
  OIDS=FALSE
);
GRANT ALL ON TABLE usermanagement.files TO postgres;
GRANT SELECT, UPDATE, INSERT, DELETE ON TABLE usermanagement.files TO resto;
GRANT ALL ON usermanagement.files_gid_seq TO resto;

SELECT f_add_col('usermanagement.users', 'storagevolume', 'integer');

update usermanagement.users set storagevolume=10000;