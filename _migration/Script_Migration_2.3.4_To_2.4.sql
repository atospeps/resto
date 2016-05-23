-- VERSION 1.3.2 de PEPS
-- Modification de l'adresse mail et developpeur pour les collections S1 et S2

update resto.osdescriptions set developper='Atos', contact='exppeps@cnes.fr' where collection IN ('S1', 'S2');
