--
-- VERSION 2.0 de PEPS
--
-- NRT
--


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




