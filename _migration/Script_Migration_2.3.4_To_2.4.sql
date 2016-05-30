-- VERSION 1.3.2 de PEPS
-- Modification de l'adresse mail et developpeur pour les collections S1 et S2

update resto.osdescriptions set developper='Atos', contact='exppeps@cnes.fr' where collection IN ('S1', 'S2');

ALTER TABLE resto.features
   ADD COLUMN orbitnumberrelative integer,
   ADD COLUMN cyclenumber integer;

ALTER TABLE resto.features
   RENAME orbitnumber  TO orbitnumberabsolute;

