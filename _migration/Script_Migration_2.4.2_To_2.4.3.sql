-- VERSION 1.3.2.2 de PEPS

-- ajout de colonnes pour la gestion des produits S2ST L2A

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

SELECT f_add_col('_s2st.features', 'bareSoil', 'NUMERIC');
SELECT f_add_col('_s2st.features', 'highProbaClouds', 'NUMERIC');
SELECT f_add_col('_s2st.features', 'mediumProbaClouds', 'NUMERIC');
SELECT f_add_col('_s2st.features', 'lowProbaClouds', 'NUMERIC');
SELECT f_add_col('_s2st.features', 'snowIce', 'NUMERIC');
SELECT f_add_col('_s2st.features', 'vegetation', 'NUMERIC');
SELECT f_add_col('_s2st.features', 'water', 'NUMERIC');
