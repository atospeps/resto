/* Script de migration de la base de données Resto de la version 1.2.1.2 vers 1.2.2 */
UPDATE resto.osdescriptions SET description='La mission SENTINEL-1 comprend une constellation de deux satellites SAR (Synthetic Aperture Radar) en bande C en orbite polaire opérant jour et nuit quelles que soient les conditions météorologiques' WHERE collection='S1' AND lang='fr';
