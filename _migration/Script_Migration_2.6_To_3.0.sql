--
-- VERSION 3.0 de PEPS
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

--
-- renommage d'une colonne
--
CREATE OR REPLACE function f_rename_col(_tbl regclass, _col1 text, _col2 text)
  RETURNS bool AS
$func$
BEGIN
      EXECUTE 'ALTER TABLE ' || _tbl || ' RENAME COLUMN ' || _col1 || ' TO ' || _col2;
      RETURN TRUE;
END
$func$  LANGUAGE plpgsql;

--
-- changement du type d'une colonne
--
CREATE OR REPLACE function f_change_type_col(_tbl regclass, _col text, _type text)
  RETURNS bool AS
$func$
BEGIN
      EXECUTE 'ALTER TABLE ' || _tbl || ' ALTER COLUMN ' || _col || ' TYPE ' || _type || ' USING CAST(' || _col || ' AS ' || _type || ')';
      RETURN TRUE;
END
$func$  LANGUAGE plpgsql;


-- ---------------------------------------------------------------------------------
--
-- PEPS-FT-511
--
-- ---------------------------------------------------------------------------------

-- renommage des anciennes colonnes d'options
SELECT f_rename_col('usermanagement.users', 'instantdownloadvolume', 'instantdownload');
SELECT f_rename_col('usermanagement.users', 'weeklydownloadvolume', 'weeklydownload');

-- on change toutes les anciennes valeurs à NULL pour prendre, par défaut, les limites définies dans config.php
UPDATE usermanagement.users SET instantdownload = NULL WHERE TRUE;
UPDATE usermanagement.users SET weeklydownload = NULL WHERE TRUE;

-- modifie le type de la colonne weeklydownload de text -> integer
SELECT f_change_type_col('usermanagement.users', 'weeklydownload', 'integer');

-- ajout d'une colonne pour obtenir directement le nb de produits dans une commande
SELECT f_add_col('usermanagement.orders', 'nbitems', 'integer');

-- ajout d'une colonne userid à la table orders
SELECT f_add_col('usermanagement.orders', 'userid', 'integer');

-- ----------------------------------------------------------------------------------------
--
-- Creates TABLE usermanagement.wps_status
--
------------------------------------------------------------------------------------------- 
CREATE TABLE usermanagement.wps_status
(
  last_dispatch timestamp without time zone,
  status text NOT NULL
)

ALTER TABLE usermanagement.wps_status OWNER TO resto;
GRANT ALL ON TABLE usermanagement.wps_status TO postgres;
GRANT ALL ON TABLE usermanagement.wps_status TO resto;

-- Default values
INSERT INTO usermanagement.wps_status (last_dispatch, status) VALUES (NOW(), 'SUCCESS');

DROP INDEX IF EXISTS _s1.features_polarisation_idx;
CREATE INDEX features_polarisation_idx ON _s1.features (polarisation DESC);



delete from resto.facets where type='isNrt';
delete from resto.facets where type='realtime';
insert into resto.facets (uid, type, value, collection, counter) values ('099d081936829ce', 'realtime', 'Fast-24h', 'S1', (select count(identifier) from _s1.features where realtime='Fast-24h'));
insert into resto.facets (uid, type, value, collection, counter) values ('b282942dabfad10', 'realtime', 'NRT-10m', 'S1', (select count(identifier) from _s1.features where realtime='NRT-10m'));
insert into resto.facets (uid, type, value, collection, counter) values ('bb0adaff1b95bdc', 'realtime', 'NRT-1h', 'S1', (select count(identifier) from _s1.features where realtime='NRT-1h'));
insert into resto.facets (uid, type, value, collection, counter) values ('4692f29501e4511', 'realtime', 'Off-line', 'S1', (select count(identifier) from _s1.features where realtime='Off-line'));
insert into resto.facets (uid, type, value, collection, counter) values ('c214faaea338ef4', 'realtime', 'Reprocessing', 'S1', (select count(identifier) from _s1.features where realtime='Reprocessing'));

update _s2.features set realtime='Nominal';
insert into resto.facets (uid, type, value, collection, counter) values ('a56dfb1210d361d', 'realtime', 'Nominal', 'S2', (select count(identifier) from _s2.features where realtime='Nominal'));

insert into resto.facets (uid, type, value, collection, counter) values ('a56dfb1210d361d', 'realtime', 'Nominal', 'S2ST', (select count(identifier) from _s2st.features where realtime='Nominal'));
insert into resto.facets (uid, type, value, collection, counter) values ('671b9c886d67577', 'realtime', 'NRT', 'S2ST', (select count(identifier) from _s2st.features where realtime='NRT'));
insert into resto.facets (uid, type, value, collection, counter) values ('653e45dc987fa87', 'realtime', 'RT', 'S2ST', (select count(identifier) from _s2st.features where realtime='RT'));

insert into resto.facets (uid, type, value, collection, counter) values ('671b9c886d67577', 'realtime', 'NRT', 'S3', (select count(identifier) from _s3.features where realtime='NRT'));
insert into resto.facets (uid, type, value, collection, counter) values ('d205833dfd4c97b', 'realtime', 'NTC', 'S3', (select count(identifier) from _s3.features where realtime='NTC'));
insert into resto.facets (uid, type, value, collection, counter) values ('dfaf229ee26146d', 'realtime', 'STC', 'S3', (select count(identifier) from _s3.features where realtime='STC'));
