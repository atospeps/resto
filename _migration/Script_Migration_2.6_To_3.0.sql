--
-- VERSION 3.0 de PEPS
--

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
