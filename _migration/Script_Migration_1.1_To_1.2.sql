/* Script de migration de la base de données Resto de la version 1.1 vers 1.2 */

ALTER TABLE usermanagement.users ADD COLUMN organization text;

ALTER TABLE usermanagement.users ADD COLUMN nationality text;

ALTER TABLE usermanagement.users ADD COLUMN domain text;

ALTER TABLE usermanagement.users ADD COLUMN use text;

ALTER TABLE usermanagement.users ADD COLUMN country text;

ALTER TABLE usermanagement.users ADD COLUMN adress text;

ALTER TABLE usermanagement.users ADD COLUMN numtel text;

ALTER TABLE usermanagement.users ADD COLUMN numfax text;

ALTER TABLE usermanagement.users ADD COLUMN instantdownloadvolume integer;

ALTER TABLE usermanagement.users ADD COLUMN weeklydownloadvolume integer;