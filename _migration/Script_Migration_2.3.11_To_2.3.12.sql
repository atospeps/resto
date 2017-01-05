-- VERSION 1.3.1.7.3 de PEPS

DELETE FROM _s2.features where title like 'S2A_MSIL1C%';

DO
$$
DECLARE
	rec record;
BEGIN
	raise notice 'UPDATING COLLECTION TYPE FACETS (CAN TAKEN SEVERAL MINUTES)...';
	
	IF NOT EXISTS(SELECT 1 FROM resto.facets WHERE type='collection' AND collection='S2ST') THEN
		INSERT INTO resto.facets(uid, value, type, collection, counter) VALUES ('45038f3c2322ea3', 'S2ST', 'collection', 'S2ST', 0);
	END IF;

	FOR rec IN 
		SELECT collection FROM resto.collections
	LOOP
		IF EXISTS(SELECT 1 FROM resto.facets WHERE type='collection' AND collection=rec.collection)
		THEN
			raise notice '[%] Updating collection type facets...', rec.collection;
			UPDATE resto.facets 
			SET counter=(SELECT count(identifier) FROM resto.features WHERE collection=rec.collection) 
			WHERE type='collection' AND collection=rec.collection;
			raise notice '[%] Update finished', rec.collection;
		ELSE
			raise WARNING '[%] Facets does not exist for this collection', rec.collection;
		END IF;
	END LOOP;
	raise notice 'UPDATE FINISHED';
END;
$$