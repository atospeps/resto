--
-- METHODES A UTILISER POUR LES SCRIPTS DE MIGRATION
--

--
-- ajout d'une colonne
--
CREATE OR REPLACE function f_add_col(_tbl regclass, _col  text, _type regtype)
  RETURNS bool AS
$func$
BEGIN
   IF EXISTS (SELECT 1 FROM pg_attribute
              WHERE  attrelid = _tbl
              AND    attname = _col
              AND    NOT attisdropped) THEN
      RETURN FALSE;
   ELSE
      EXECUTE 'ALTER TABLE ' || _tbl || ' ADD COLUMN ' || _col || ' ' || _type;
      RETURN TRUE;
   END IF;
END
$func$  LANGUAGE plpgsql;

--
-- suppression d'une colonne
--
CREATE OR REPLACE function f_drop_col(_tbl regclass, _col  text)
  RETURNS bool AS
$func$
BEGIN
      EXECUTE 'ALTER TABLE ' || _tbl || ' DROP COLUMN IF EXISTS ' || _col;
      RETURN TRUE;
END
$func$  LANGUAGE plpgsql;

--
-- renommage d'une colonne
--
CREATE OR REPLACE function f_rename_col(_tbl regclass, _col1 text, _col2 text)
  RETURNS bool AS
$func$
BEGIN
      EXECUTE 'ALTER TABLE ' || _tbl || ' RENAME COLUMN ' || _col1 || ' TO ' || _col2;
      RETURN TRUE;
END
$func$  LANGUAGE plpgsql;

--
-- changement du type d'une colonne
--
CREATE OR REPLACE function f_change_type_col(_tbl regclass, _col text, _type text)
  RETURNS bool AS
$func$
BEGIN
      EXECUTE 'ALTER TABLE ' || _tbl || ' ALTER COLUMN ' || _col || ' TYPE ' || _type || ' USING CAST(' || _col || ' AS ' || _type || ')';
      RETURN TRUE;
END
$func$  LANGUAGE plpgsql;


