--
-- VERSION 6.0 de PEPS
--

--
-- PEPS-FT-765 - Requête OpenSearch très longue
--

DROP INDEX IF EXISTS _s2st._s2st_features_mgrs_idx;
CREATE INDEX _s2st_features_mgrs_idx ON _s2st.features (mgrs DESC);
