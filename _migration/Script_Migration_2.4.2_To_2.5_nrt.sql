--
-- VERSION 2.0 de PEPS
--
-- NRT
--
CREATE OR REPLACE FUNCTION product_version(title text, collection text) RETURNS TEXT AS
$func$
BEGIN
	CASE collection
    WHEN 'S1' THEN
        RETURN SUBSTRING(title, 0, 63);
    WHEN 'S2' THEN
        RETURN SUBSTRING(title, 0, 25) || SUBSTRING(title, 41);
    WHEN 'S2ST' THEN
        RETURN SUBSTRING(title, 0, 29) || SUBSTRING(title, 33);
    WHEN 'S3' THEN
        RETURN SUBSTRING(title, 0, 48) ||SUBSTRING(title, 64, 24) || SUBSTRING(title, 91);
    ELSE
    	RAISE NOTICE 'product_version: invalid collection';
    END CASE;
END
$func$ LANGUAGE plpgsql IMMUTABLE;

DROP INDEX IF EXISTS _s1._s1_features_version_idx;
CREATE INDEX _s1_features_version_idx ON _s1.features (product_version(title, 'S1'));

DROP INDEX IF EXISTS _s2._s2_features_version_idx;
CREATE INDEX _s2_features_version_idx ON _s2.features (product_version(title, 'S2'));

DROP INDEX IF EXISTS _s2st._s2st_features_version_idx;
CREATE INDEX _s2st_features_version_idx ON _s2st.features (product_version(title, 'S2ST'));

DROP INDEX IF EXISTS _s3._s3_features_version_idx;
CREATE INDEX _s3_features_version_idx ON _s3.features (product_version(title, 'S3'));







-- ----------------------------------------------------------------------------------------
--
-- UPDATE realtime
--
-- ----------------------------------------------------------------------------------------

-- S1
UPDATE _s1.features   SET realtime = 'Reprocessing' WHERE isnrt = 0;     -- [DEV] 11m pour 417 000 lignes
UPDATE _s1.features   SET realtime = 'NRT-3h'       WHERE isnrt = 1;
-- S2ST
UPDATE _s2st.features SET realtime = 'Nominal'      WHERE isnrt = 0;
UPDATE _s2st.features SET realtime = 'NRT'          WHERE isnrt = 1;
-- S3
UPDATE _s3.features
SET realtime = CASE
                   WHEN SUBSTR(productidentifier, 89, 2) = 'NR' THEN 'NRT'
                   WHEN SUBSTR(productidentifier, 89, 2) = 'ST' THEN 'STC'
                   ELSE 'NTC'
               END;

-- ----------------------------------------------------------------------------------------
--
-- UPDATE S3 isnrt
--
-- ----------------------------------------------------------------------------------------

UPDATE _s3.features SET isnrt = CASE WHEN realtime = 'NRT' THEN 1 ELSE 0 END;

-- ----------------------------------------------------------------------------------------
--
-- UPDATE visible + new version
--
-- ----------------------------------------------------------------------------------------




