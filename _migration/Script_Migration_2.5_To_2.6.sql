--
--
-- VERSION 2.1 de PEPS
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


-- ---------------------------------------------------------------------------------
--
-- PEPS-FT-511
--
-- ---------------------------------------------------------------------------------

-- renommage des anciennes colonnes d'options
SELECT f_rename_col('usermanagement.users', 'instantdownloadvolume', 'instantdownload');
SELECT f_rename_col('usermanagement.users', 'weeklydownloadvolume', 'weeklydownload');

-- on change toutes les anciennes valeurs à NULL pour prendre, par défaut, les limites définies dans config.php
UPDATE usermanagement.users SET instantdownload = NULL WHERE TRUE;
UPDATE usermanagement.users SET weeklydownload = NULL WHERE TRUE;

-- modifie le type de la colonne weeklydownload de text -> integer
SELECT f_change_type_col('usermanagement.users', 'weeklydownload', 'integer');

-- ajout d'une colonne pour obtenir directement le nb de produits dans une commande
SELECT f_add_col('usermanagement.orders', 'nbitems', 'integer');

-- ajout d'une colonne userid à la table orders
SELECT f_add_col('usermanagement.orders', 'userid', 'integer');

