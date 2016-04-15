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