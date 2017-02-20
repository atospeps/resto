--
-- VERSION 1.3.2.1 de PEPS
--

--
-- PEPS-FT-446
--
DROP INDEX IF EXISTS resto.resto_features_startdate_idx;
CREATE INDEX resto_features_startdate_idx ON resto.features  (startdate DESC, identifier);

DROP INDEX IF EXISTS _s1._s1_features_startdate_idx;
CREATE INDEX _s1_features_startdate_idx ON _s1.features  (startdate DESC, identifier);

DROP INDEX IF EXISTS _s2._s2_features_startdate_idx;
CREATE INDEX _s2_features_startdate_idx ON _s2.features  (startdate DESC, identifier);

DROP INDEX IF EXISTS _s2st._s2st_features_startdate_idx;
CREATE INDEX _s2st_features_startdate_idx ON _s2st.features  (startdate DESC, identifier);

DROP INDEX IF EXISTS _s3._s3_features_startdate_idx;
CREATE INDEX _s3_features_startdate_idx ON _s3.features  (startdate DESC, identifier);
