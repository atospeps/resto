-- VERSION 1.3.1.3 de PEPS
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


SELECT f_add_col('resto.features', 'new_version', 'text');
SELECT f_add_col('resto.features', 'isnrt', 'integer');
SELECT f_add_col('resto.features', 'realtime', 'text');

update resto.collections set mapping = '{"parentIdentifier": "urn:ogc:def:EOP:ESA::SENTINEL-1:", "quicklook": "https://peps.cnes.fr/quicklook/{:quicklook:}_quicklook.jpg <https://peps.cnes.fr/quicklook/%7b:quicklook:%7d_quicklook.jpg> ", "resource" : "/hpss/peps/data/{:quicklook:}.zip", "resourceMimeType": "application/zip", "wms" : "https://peps.cnes.fr/cgi-bin/mapserver?map=WMS_S1&data={:quicklook:} <https://peps.cnes.fr/cgi-bin/mapserver?map=WMS_S1&data=%7b:quicklook:%7d> ", "nrtResource" : "/data/NRT/{:quicklook:}.zip"}' where collection = 'S1';
update resto.collections set mapping = ' {"parentIdentifier": "urn:ogc:def:EOP:ESA::SENTINEL-2:", "quicklook": "htts://peps.cnes.fr/quicklook/{:quicklook:}_quicklook.jpg", "resource" : "/hpss/peps/data/{:quicklook:}.zip", "resourceMimeType": "application/zip", "nrtResource" : "/data/NRT/{:quicklook:}.zip"}' where collection = 'S2';
