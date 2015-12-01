/* Script de migration de la base de données Resto de la version 2.2.4 vers 2.2.5 */
UPDATE resto.collections SET mapping='{"parentIdentifier": "urn:ogc:def:EOP:ESA::SENTINEL-1:", "quicklook" : "https://peps.cnes.fr/quicklook/{:quicklook:}_quicklook.jpg", "resource" : "/hpss/peps/data/{:quicklook:}.zip", "resourceMimeType": "application/zip", "wms" : "https://peps.cnes.fr/cgi-bin/mapserver?map=WMS_S1&data={:quicklook:}"}' WHERE collection='S1';

UPDATE resto.collections SET mapping='{"parentIdentifier": "urn:ogc:def:EOP:ESA::SENTINEL-2:", "quicklook" : "https://peps.cnes.fr/quicklook/{:quicklook:}_quicklook.jpg", "resource" : "/hpss/peps/data/{:quicklook:}.zip", "resourceMimeType": "application/zip"}' WHERE collection='S2';
