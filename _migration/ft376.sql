--
-- PEPS-FT-376
--

--
-- Search index on property published
--
DROP INDEX IF EXISTS resto.resto_features_published_idx;
CREATE INDEX resto_features_published_idx ON resto.features (published DESC);

DROP INDEX IF EXISTS _s1._s1_features_published_idx;
CREATE INDEX _s1_features_published_idx ON _s1.features (published DESC);

DROP INDEX IF EXISTS _s2._s2_features_published_idx;
CREATE INDEX _s2_features_published_idx ON _s2.features (published DESC);

DROP INDEX IF EXISTS _s2st._s2st_features_published_idx;
CREATE INDEX _s2st_features_published_idx ON _s2st.features (published DESC);

DROP INDEX IF EXISTS _s3._s3_features_published_idx;
CREATE INDEX _s3_features_published_idx ON _s3.features (published DESC);

--
-- Search index on property sensormode
--
DROP INDEX IF EXISTS resto.resto_features_sensormode_idx;
CREATE INDEX resto_features_sensormode_idx ON resto.features (sensormode DESC);

DROP INDEX IF EXISTS _s1._s1_features_sensormode_idx;
CREATE INDEX _s1_features_sensormode_idx ON _s1.features (sensormode DESC);

DROP INDEX IF EXISTS _s2._s2_features_sensormode_idx;
CREATE INDEX _s2_features_sensormode_idx ON _s2.features (sensormode DESC);

DROP INDEX IF EXISTS _s2st._s2st_features_sensormode_idx;
CREATE INDEX _s2st_features_sensormode_idx ON _s2st.features (sensormode DESC);

DROP INDEX IF EXISTS _s3._s3_features_sensormode_idx;
CREATE INDEX _s3_features_sensormode_idx ON _s3.features (sensormode DESC);


--
-- Indexes for requests which manages obsolescence
--

--
-- dhusingestdate
--
DROP INDEX IF EXISTS resto.resto_features_dhusingestdate_idx;
CREATE INDEX resto_features_dhusingestdate_idx ON resto.features (dhusingestdate DESC);

DROP INDEX IF EXISTS _s1._s1_features_dhusingestdate_idx;
CREATE INDEX _s1_features_dhusingestdate_idx ON _s1.features (dhusingestdate DESC);

DROP INDEX IF EXISTS _s2._s2_features_dhusingestdate_idx;
CREATE INDEX _s2_features_dhusingestdate_idx ON _s2.features (dhusingestdate DESC);

DROP INDEX IF EXISTS _s2st._s2st_features_dhusingestdate_idx;
CREATE INDEX _s2st_features_dhusingestdate_idx ON _s2st.features (dhusingestdate DESC);

DROP INDEX IF EXISTS _s3._s3_features_dhusingestdate_idx;
CREATE INDEX _s3_features_dhusingestdate_idx ON _s3.features (dhusingestdate DESC);


--
-- TODO : usermanagement.cart indexes ?!!
--




-- productidentifier
-- 
DROP INDEX IF EXISTS resto.resto_features_productidentifier_idx;
CREATE INDEX resto_features_productidentifier_idx ON resto.features (productidentifier DESC);

DROP INDEX IF EXISTS _s1._s1_features_productidentifier_idx;
CREATE INDEX _s1_features_productidentifier_idx ON _s1.features (productidentifier DESC);

DROP INDEX IF EXISTS _s2._s2_features_productidentifier_idx;
CREATE INDEX _s2_features_productidentifier_idx ON _s2.features (productidentifier DESC);

DROP INDEX IF EXISTS _s2st._s2st_features_productidentifier_idx;
CREATE INDEX _s2st_features_productidentifier_idx ON _s2st.features (productidentifier DESC);

DROP INDEX IF EXISTS _s3._s3_features_productidentifier_idx;
CREATE INDEX _s3_features_productidentifier_idx ON _s3.features (productidentifier DESC);


--
-- Admin module
--

--
-- querytime
--
DROP INDEX IF EXISTS usermanagement.history_querytime_idx;
CREATE INDEX history_querytime_idx ON usermanagement.history (querytime DESC);


--
