--
-- VERSION 2.2 de PEPS
--

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
-- suppression d'une colonne
--
CREATE OR REPLACE function f_drop_col(_tbl regclass, _col  text)
  RETURNS bool AS
$func$
BEGIN
      EXECUTE 'ALTER TABLE ' || _tbl || ' DROP COLUMN IF EXISTS ' || _col;
      RETURN TRUE;
END
$func$  LANGUAGE plpgsql;



-- ----------------------------------------------------------------------------------------
---
--- Table 'jobs'
---                       
---
-------------------------------------------------------------------------------------------- 
SELECT f_add_col('usermanagement.jobs', 'logs', 'text');
SELECT f_drop_col('usermanagement.jobs', 'email');

--DROP INDEX IF EXISTS usermanagement.idx_jobs_querytime;
--CREATE INDEX idx_jobs_querytime ON usermanagement.jobs (querytime DESC);

-- ----------------------------------------------------------------------------------------
--
-- Table 'rights'
--                      
--
------------------------------------------------------------------------------------------- 
SELECT f_drop_col('usermanagement.rights', 'wps');


-- ----------------------------------------------------------------------------------------
--
-- Table 'resto.keywords'
--                       
--
------------------------------------------------------------------------------------------- 
DELETE FROM resto.keywords WHERE name='L1C' and value='level1c' and lang='**' and type='processingLevel';
INSERT INTO resto.keywords (name, value, lang, type) VALUES ('L1C', 'level1c', '**', 'processingLevel');

DELETE FROM resto.keywords WHERE name='L2A' and value='level2a' and lang='**' and type='processingLevel';
INSERT INTO resto.keywords (name, value, lang, type) VALUES ('L2A', 'level2a', '**', 'processingLevel');

-- ----------------------------------------------------------------------------------------
--
-- Table 'resto.osdescriptions'
--                                  FT-489
--
-------------------------------------------------------------------------------------------
UPDATE resto.osdescriptions SET developper = 'CNES';


-- ----------------------------------------------------------------------------------------
-- 
-- PEPS-FT-638 
-- Les datatakeID des produits S2 sont manquants
--
-- ----------------------------------------------------------------------------------------
UPDATE _s2st.features SET s2takeid='G' || substr(title, 1, 4) || substr(title, 12, 15) || to_char(orbitnumber, '000000') || '_N' || substr(title, 29, 2) || '.' || substr(title, 31, 2) where s2takeid IS NULL OR s2takeid='';

-- ----------------------------------------------------------------------------------------
-- 
--  Les index (dont les contraintes d'unicité) et les contraintes de clés étrangères ne s'appliquent qu'aux tables mères, pas à leurs héritiers.
-- (http://docs.postgresqlfr.org/9.4/ddl-inherit.html)
-- Les collections (S1, S2, S2ST et S3) héritent de la table resto.features.
-- Les contraintes d’unicité sont définies seulement dans la table resto.features 
-- (« CONSTRAINT features_identifier_key UNIQUE (identifier) ») et pas dans les collections héritières (_s1.features, _s2.features, …).
--
-- ----------------------------------------------------------------------------------------

-- Suppression des doublons (même identifier) les plus anciens (en fonction de la date de publication)
DELETE FROM _s1.features a USING _s1.features b WHERE a.published < b.published AND a.identifier = b.identifier;
DELETE FROM _s2.features a USING _s2.features b WHERE a.published < b.published AND a.identifier = b.identifier;
DELETE FROM _s2st.features a USING _s2st.features b WHERE a.published < b.published AND a.identifier = b.identifier;
DELETE FROM _s3.features a USING _s3.features b WHERE a.published < b.published AND a.identifier = b.identifier;

ALTER TABLE _s1.features ADD CONSTRAINT _s1_features_identifier_key UNIQUE(identifier);
ALTER TABLE _s2.features ADD CONSTRAINT _s2_features_identifier_key UNIQUE(identifier);
ALTER TABLE _s2st.features ADD CONSTRAINT _s2st_features_identifier_key UNIQUE(identifier);
ALTER TABLE _s3.features ADD CONSTRAINT _s3_features_identifier_key UNIQUE(identifier);

DROP INDEX IF EXISTS _s1._s1_features_identifier_idx;
DROP INDEX IF EXISTS _s2._s2_features_identifier_idx;
DROP INDEX IF EXISTS _s2st._s2st_features_identifier_idx;
DROP INDEX IF EXISTS _s3._s3_features_identifier_idx;

--
-- Performance - Index fonctionnel
--
DROP INDEX IF EXISTS resto.resto_features_startdate_visible_idx;
DROP INDEX IF EXISTS _s1._s1_features_startdate_visible_idx;
DROP INDEX IF EXISTS _s2._s2_startdate_visible_idx;
DROP INDEX IF EXISTS _s2st._s2st_features_startdate_visible_idx;
DROP INDEX IF EXISTS _s3._s3_features_startdate_visible_idx;

CREATE INDEX resto_features_startdate_visible_idx ON resto.features USING btree (startdate DESC, identifier) where visible = 1;
CREATE INDEX _s1_features_startdate_visible_idx ON _s1.features USING btree (startdate DESC, identifier) where visible = 1;
CREATE INDEX _s2_features_startdate_visible_idx ON _s2.features USING btree (startdate DESC, identifier) where visible = 1;
CREATE INDEX _s2st_features_startdate_visible_idx ON _s2st.features USING btree (startdate DESC, identifier) where visible = 1;
CREATE INDEX _s3_features_startdate_visible_idx ON _s3.features USING btree (startdate DESC, identifier) where visible = 1;

DROP INDEX IF EXISTS _s1.features_title_idx;
CREATE INDEX resto_features_title_idx ON resto.features USING btree (title text_pattern_ops DESC);
CREATE INDEX _s1_features_title_idx ON _s1.features USING btree (title text_pattern_ops DESC);
CREATE INDEX _s2_features_title_idx ON _s2.features USING btree (title text_pattern_ops DESC);
CREATE INDEX _s2st_features_title_idx ON _s2st.features USING btree (title text_pattern_ops DESC);
CREATE INDEX _s3_features_title_idx ON _s3.features USING btree (title text_pattern_ops DESC);
