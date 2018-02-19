--
-- VERSION 3.0 de PEPS
--

-- ----------------------------------------------------------------------------------------
--
-- Creates TABLE usermanagement.wps_status
--
------------------------------------------------------------------------------------------- 
CREATE TABLE usermanagement.wps_status
(
  last_dispatch timestamp without time zone,
  status text NOT NULL
)

ALTER TABLE usermanagement.wps_status OWNER TO resto;
GRANT ALL ON TABLE usermanagement.wps_status TO postgres;
GRANT ALL ON TABLE usermanagement.wps_status TO resto;

-- Default values
INSERT INTO usermanagement.wps_status (last_dispatch, status) VALUES (NOW(), 'SUCCESS');

