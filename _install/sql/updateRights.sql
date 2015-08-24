-- CHANGE OWNER
ALTER SCHEMA public OWNER TO :user;
ALTER SCHEMA resto OWNER TO :user;
ALTER SCHEMA usermanagement OWNER TO :user;
ALTER TABLE public.geometry_columns OWNER TO :user;
ALTER TABLE public.geography_columns OWNER TO :user;
ALTER TABLE public.spatial_ref_sys OWNER TO :user;
ALTER TABLE resto.features OWNER TO :user;
ALTER DATABASE :db OWNER TO :user;

-- REVOKE rights on public schema
REVOKE CREATE ON SCHEMA public FROM PUBLIC;

-- SET user RIGHTS
GRANT ALL ON geometry_columns TO :user;
GRANT ALL ON geography_columns TO :user;
GRANT SELECT ON spatial_ref_sys TO :user;
GRANT CREATE ON DATABASE :db TO :user;

GRANT ALL ON SCHEMA resto TO :user;
GRANT SELECT,INSERT,UPDATE,DELETE ON resto.collections TO :user;
GRANT SELECT,INSERT,UPDATE,DELETE ON resto.osdescriptions TO :user;
GRANT SELECT,INSERT,UPDATE,DELETE ON resto.keywords TO :user;
GRANT SELECT,INSERT,UPDATE ON resto.features TO :user;
GRANT SELECT,INSERT,UPDATE ON resto.facets TO :user;
GRANT ALL ON resto.keywords_gid_seq TO :user;
GRANT ALL ON resto.facets_gid_seq TO :user;

GRANT ALL ON SCHEMA usermanagement TO :user;
GRANT SELECT,INSERT,UPDATE,DELETE ON usermanagement.users TO :user;
GRANT SELECT,INSERT,UPDATE,DELETE ON usermanagement.revokedtokens TO :user;
GRANT SELECT,INSERT,UPDATE,DELETE ON usermanagement.rights TO :user;
GRANT SELECT,INSERT,UPDATE,DELETE ON usermanagement.signatures TO :user;
GRANT SELECT,INSERT,UPDATE,DELETE ON usermanagement.cart TO :user;
GRANT SELECT,INSERT,UPDATE,DELETE ON usermanagement.orders TO :user;
GRANT SELECT,INSERT,UPDATE,DELETE ON usermanagement.sharedlinks TO :user;
GRANT SELECT,INSERT,UPDATE,DELETE ON usermanagement.groups TO :user;
GRANT SELECT,INSERT,UPDATE ON usermanagement.history TO :user;
GRANT ALL ON usermanagement.rights_gid_seq TO :user;

GRANT SELECT,UPDATE ON usermanagement.users_userid_seq TO :user;
GRANT SELECT,UPDATE ON usermanagement.revokedtokens_gid_seq TO :user;
GRANT SELECT,UPDATE ON usermanagement.history_gid_seq TO :user;
GRANT SELECT,UPDATE ON usermanagement.sharedlinks_gid_seq TO :user;
GRANT SELECT,UPDATE ON usermanagement.cart_gid_seq TO :user;
GRANT SELECT,UPDATE ON usermanagement.orders_gid_seq TO :user;
