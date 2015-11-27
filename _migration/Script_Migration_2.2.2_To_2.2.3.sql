/* Script de migration de la base de données Resto de la version 2.2.2 vers 2.2.3 */

UPDATE _s1.features SET processinglevel='LEVEL2' where producttype='OCN';

UPDATE resto.osdescriptions SET description='La mission SENTINEL-1 comprend une constellation de deux satellites SAR en bande C en orbite polaire opérant jour et nuit quelles que soient les conditions météorologiques. Ces satellites sont exploités en quatre modes d''imagerie de différentes résolutions (jusqu''à une précision de 10 m) et couvertures (pouvant atteindre 400 km). La capacité d''observation des deux satellites permettra une surveillance fiable et répétée de zones très étendues du globe tous les 6 jours.' WHERE collection='S1' AND lang='fr';

UPDATE resto.osdescriptions SET description='The SENTINEL-1 mission comprises a constellation of two polar-orbiting satellites, operating day and night performing C-band Synthetic Aperture Radar (SAR) imaging, enabling them to acquire imagery regardless of the weather. Sentinel-1 is operated in four imaging modes with different resolutions (down to 10 m) and coverage (up to 400 km swath), offering reliable wide area monitoring every 12 days with one satellite and 6 days with two satellites.' WHERE collection='S1' AND lang='en';
