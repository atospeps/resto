/* Script de migration de la base de données Resto de la version 1.1 vers 1.2 */
/* Change user postgres with your database admin user, and change user resto with your database resto user */

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

CREATE TABLE usermanagement.groups
(
  gid serial NOT NULL,
  groupname text NOT NULL,
  description text,
  CONSTRAINT groups_pkey PRIMARY KEY (gid),
  CONSTRAINT groups_groupname_key UNIQUE (groupname)
);
CREATE INDEX idx_groupname_groups ON usermanagement.groups (groupname);

ALTER TABLE usermanagement.groups OWNER TO postgres;
GRANT ALL ON TABLE usermanagement.groups TO postgres;
GRANT SELECT, UPDATE, INSERT, DELETE ON TABLE usermanagement.groups TO resto;

GRANT ALL ON SEQUENCE usermanagement.groups_gid_seq TO postgres;
GRANT SELECT, UPDATE ON SEQUENCE usermanagement.groups_gid_seq TO resto;

