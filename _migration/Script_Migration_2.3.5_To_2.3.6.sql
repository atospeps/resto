-- VERSION 1.3.1.5 de PEPS
-- Met à jour les informations des produits S1 et initialise isnrt

UPDATE _s1.features SET instrument='SAR-C SAR';

UPDATE _s1.features SET processinglevel='LEVEL1' where (producttype='SLC' OR producttype='GRD') AND processinglevel='1';
UPDATE _s1.features SET processinglevel='LEVEL2' where producttype='OCN';

GRANT SELECT, UPDATE, INSERT, DELETE ON TABLE resto.facets TO resto;

ALTER TABLE resto.features ALTER COLUMN isnrt SET DEFAULT 0;
UPDATE resto.features SET isnrt=0 where isnrt is null;