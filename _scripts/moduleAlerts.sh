/* Activation du module Alerts */
CREATE TABLE usermanagement.alerts
(
  aid serial NOT NULL,
  email text,
  title text,
  creation_time timestamp without time zone,
  expiration timestamp without time zone,
  last_dispatch timestamp without time zone,
  period integer,
  criterias text,
  CONSTRAINT alerts_pkey PRIMARY KEY (aid)
)

ALTER TABLE usermanagement.alerts
  OWNER TO postgres;
GRANT ALL ON TABLE usermanagement.alerts TO postgres;
GRANT SELECT, UPDATE, INSERT, DELETE ON TABLE usermanagement.alerts TO resto;

GRANT ALL ON SEQUENCE usermanagement.alerts_aid_seq TO postgres;
GRANT SELECT, UPDATE ON SEQUENCE usermanagement.alerts_aid_seq TO resto;