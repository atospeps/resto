/* Script de migration de la base de données Resto de la version 2.2.4 vers 2.2.5 */
UPDATE resto.collections SET mapping='{"parentIdentifier": "urn:ogc:def:EOP:ESA::SENTINEL-1:", "quicklook" : "https://peps.cnes.fr/quicklook/{:quicklook:}_quicklook.jpg", "resource" : "/hpss/peps/data/{:quicklook:}.zip", "resourceMimeType": "application/zip", "wms" : "https://peps.cnes.fr/cgi-bin/mapserver?map=WMS_S1&data={:quicklook:}"}' WHERE collection='S1';

UPDATE resto.collections SET mapping='{"parentIdentifier": "urn:ogc:def:EOP:ESA::SENTINEL-2:", "quicklook" : "https://peps.cnes.fr/quicklook/{:quicklook:}_quicklook.jpg", "resource" : "/hpss/peps/data/{:quicklook:}.zip", "resourceMimeType": "application/zip"}' WHERE collection='S2';

CREATE TABLE usermanagement.alerts
(
  aid serial NOT NULL,
  email text,
  title text,
  creation_time timestamp without time zone,
  expiration timestamp without time zone,
  last_dispatch timestamp without time zone,
  period integer,
  criterias text,
  CONSTRAINT alerts_pkey PRIMARY KEY (aid)
)

ALTER TABLE usermanagement.alerts
  OWNER TO postgres;
GRANT ALL ON TABLE usermanagement.alerts TO postgres;
GRANT SELECT, UPDATE, INSERT, DELETE ON TABLE usermanagement.alerts TO resto;

GRANT ALL ON SEQUENCE usermanagement.alerts_aid_seq TO postgres;
GRANT SELECT, UPDATE ON SEQUENCE usermanagement.alerts_aid_seq TO resto;