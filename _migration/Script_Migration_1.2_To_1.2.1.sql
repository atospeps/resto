/* Script de migration de la base de données Resto de la version 1.2 vers 1.2.1 */
/* Enlever la table signatures. On valide la license que quand on crée le compte utilisateur  */
/* Rajouter la colonne orbitDirection dans la table features de la collection s1 et de Resto  */
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

DROP TABLE usermanagement.signatures;

SELECT f_add_col('_s1.features', 'orbitDirection', 'text');

update resto.collections set mapping='{"parentIdentifier":"urn:ogc:def:EOP:ESA::SENTINEL-1:","quicklook":"https:\/\/peps.cnes.fr\/hpss\/peps\/data\/{:quicklook:}_quicklook.gif","metadata":"https:\/\/peps.cnes.fr\/hpss\/peps\/data\/{:quicklook:}_Metadata.xml","resource":"\/hpss\/peps\/data\/{:quicklook:}.zip","resourceMimeType":"application\/zip","wms":"https:\/\/peps.cnes.fr\/cgi-bin\/mapserver?map=WMS_S1&data={:quicklook:}"}' where collection='S1';
