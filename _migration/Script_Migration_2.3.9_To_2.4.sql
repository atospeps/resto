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


