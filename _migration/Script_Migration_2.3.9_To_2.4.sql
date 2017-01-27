--
-- VERSION 1.3.2 de PEPS
--

--
-- PEPS-FT-391 (FA-ROCKET_PEPS) Recherche sur Sentinel 3 non fonctionnelle
--
INSERT INTO resto.keywords (name, value, lang, type) VALUES ('s3', 'S3%','**', 'platform');
INSERT INTO resto.keywords (name, value, lang, type) VALUES ('s3A', 'S3A','**', 'platform');
INSERT INTO resto.keywords (name, value, lang, type) VALUES ('s3B', 'S3B','**', 'platform');
INSERT INTO resto.keywords (name, value, lang, type) VALUES ('sentinel3', 'S3%','**', 'platform');
INSERT INTO resto.keywords (name, value, lang, type) VALUES ('sentinel-3', 'S3%','**', 'platform');

--
-- PEPS-FT-365 (FA-PEPS) Recherche sémantique incomprise
--
UPDATE resto.keywords SET value=normalize(value) WHERE TYPE IN ('continent', 'country', 'region', 'state', 'landuse');

INSERT INTO resto.keywords (name, value, lang, type) VALUES ('Zone côtière', 'coastal', 'fr', 'location');
INSERT INTO resto.keywords (name, value, lang, type) VALUES ('Zones côtières', 'coastal', 'fr', 'location');
INSERT INTO resto.keywords (name, value, lang, type) VALUES ('Zone littorale', 'coastal', 'fr', 'location');
INSERT INTO resto.keywords (name, value, lang, type) VALUES ('Zones littorales', 'coastal', 'fr', 'location');
INSERT INTO resto.keywords (name, value, lang, type) VALUES ('coastal area', 'coastal', 'en', 'location');

INSERT INTO resto.keywords (name, value, lang, type) VALUES ('equatoriale', 'equatorial', 'fr', 'location');
INSERT INTO resto.keywords (name, value, lang, type) VALUES ('zones equatoriales', 'equatorial', 'fr', 'location');
INSERT INTO resto.keywords (name, value, lang, type) VALUES ('equatorial', 'equatorial', '**', 'location');
INSERT INTO resto.keywords (name, value, lang, type) VALUES ('equatorial area', 'equatorial', 'en', 'location');


INSERT INTO resto.keywords (name, value, lang, type) VALUES ('tropique', 'tropical', 'fr', 'location');
INSERT INTO resto.keywords (name, value, lang, type) VALUES ('tropiques', 'tropical', 'fr', 'location');
INSERT INTO resto.keywords (name, value, lang, type) VALUES ('tropicale', 'tropical', 'fr', 'location');
INSERT INTO resto.keywords (name, value, lang, type) VALUES ('tropicales', 'tropical', 'fr', 'location');
INSERT INTO resto.keywords (name, value, lang, type) VALUES ('zones tropicales', 'tropical', 'fr', 'location');
INSERT INTO resto.keywords (name, value, lang, type) VALUES ('tropical', 'tropical', 'en', 'location');
INSERT INTO resto.keywords (name, value, lang, type) VALUES ('tropical area', 'tropical', 'en', 'location');

--
-- PEPS-FT-280 (DM-ROCKET_PEPS) Possibilité de rechercher les produits Sentinel par le numéro du cycle, 
-- le numéro d'orbite absolue et le numéro d'orbite relative
--

-- ajout de colonnes pour la gestion des produits NRT

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

SELECT f_add_col('resto.features', 'relativeorbitnumber', 'INTEGER');
SELECT f_add_col('_s1.features', 'cyclenumber', 'INTEGER');
SELECT f_add_col('_s3.features', 'cyclenumber', 'INTEGER');

-- UPDATE _s1.features.relativeorbitnumber with Acquisition UPDATE function

UPDATE _s2.features set relativeorbitnumber=SUBSTR(title, 43, 3);
UPDATE _s2st.features set relativeorbitnumber=SUBSTR(title, 35, 3);
UPDATE _s3.features set relativeorbitnumber=SUBSTR(title, 74, 3);

UPDATE _s1.features set cyclenumber=floor((orbitNumber + 2552) / 175);
UPDATE _s3.features set cyclenumber=SUBSTR(title, 70, 3);




