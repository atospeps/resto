
--
-- Use unaccent function from postgresql >= 9
-- Set it as IMMUTABLE to use it in index
--
CREATE EXTENSION IF NOT EXISTS unaccent;
ALTER FUNCTION unaccent(text) IMMUTABLE;

--
-- Create function normalize
-- This function will return input text
-- in lower case, without accents and with spaces replaced as '-'
--
CREATE OR REPLACE FUNCTION normalize(text) 
RETURNS text AS \$\$ 
SELECT replace(replace(lower(unaccent(\$1)),' ','-'), '''', '-')
\$\$ LANGUAGE sql;

-- 
-- resto schema contains collections descriptions tables
--
CREATE SCHEMA resto;

--
-- collections table list all RESTo collections
--
CREATE TABLE resto.collections (
    collection          TEXT PRIMARY KEY,
    creationdate        TIMESTAMP,
    model               TEXT DEFAULT 'RestoModel_default',
    status              TEXT DEFAULT 'public',
    license             TEXT,
    mapping             TEXT
);
CREATE INDEX idx_status_collections ON resto.collections (status);
CREATE INDEX idx_creationdate_collections ON resto.collections (creationdate);

--
-- osdescriptions table describe all RESTo collections
--
CREATE TABLE resto.osdescriptions (
    collection          TEXT,
    lang                TEXT,
    shortname           TEXT,
    longname            TEXT,
    description         TEXT,
    tags                TEXT,
    developper          TEXT,
    contact             TEXT,
    query               TEXT,
    attribution         TEXT
);
ALTER TABLE ONLY resto.osdescriptions ADD CONSTRAINT fk_collection FOREIGN KEY (collection) REFERENCES resto.collections(collection);
ALTER TABLE ONLY resto.osdescriptions ADD CONSTRAINT cl_collection UNIQUE(collection, lang);
CREATE INDEX idx_collection_osdescriptions ON resto.osdescriptions (collection);
CREATE INDEX idx_lang_osdescriptions ON resto.osdescriptions (lang);


--
-- Keywords table
--
CREATE TABLE resto.keywords (
    gid                 SERIAL PRIMARY KEY, -- unique id
    name                TEXT, -- keyword name in given language code
    type                TEXT, -- type of keyword (i.e. region, state, location, etc.)
    lang                TEXT, -- ISO A2 language code in lowercase
    value               TEXT, -- keyword as stored in features keywords columns
    location            TEXT DEFAULT NULL -- 'country code:bounding box'
);
CREATE INDEX idx_name_keywords ON resto.keywords (normalize(name));
CREATE INDEX idx_type_keywords ON resto.keywords (type);
CREATE INDEX idx_lang_keywords ON resto.keywords (lang);

--
-- Facets table - store statistics for keywords appearance
--
CREATE TABLE resto.facets (
    gid                 SERIAL PRIMARY KEY, -- unique id
    uid                 TEXT,
    value               TEXT, -- keyword value (without type)
    type                TEXT, -- type of keyword (i.e. region, state, location, etc.)
    pid                 TEXT, -- parent hash (i.e. 'europe' for keyword 'france')
    collection          TEXT, -- collection name
    counter             INTEGER -- number of appearance of this keyword within the collection
);
CREATE INDEX idx_type_facets ON resto.facets (type);
CREATE INDEX idx_uid_facets ON resto.facets (uid);
CREATE INDEX idx_pid_facets ON resto.facets (pid);
CREATE INDEX idx_collection_facets ON resto.facets (collection);


--
-- tags table list all tags attached to data within collection
--
CREATE TABLE resto.tags (
    tag                 TEXT PRIMARY KEY,
    creationdate        TIMESTAMP,
    updateddate         TIMESTAMP,
    occurence           INTEGER
);
CREATE INDEX idx_updated_tags ON resto.tags (updateddate);

--
-- features TABLE MUST BE EMPTY (inheritance)
--

CREATE TABLE resto.features (
    identifier          TEXT UNIQUE,
    parentidentifier    TEXT,
    collection          TEXT,
    visible             INTEGER DEFAULT 1,
    productidentifier   TEXT,
    title               TEXT,
    description         TEXT,
    authority           TEXT,
    startdate           TIMESTAMP,
    completiondate      TIMESTAMP,
    producttype         TEXT,
    processinglevel     TEXT,
    platform            TEXT,
    instrument          TEXT,
    resolution          NUMERIC(8,2),
    sensormode          TEXT,
    orbitnumber         INTEGER,
    quicklook           TEXT,
    thumbnail           TEXT,
    metadata            TEXT,
    metadata_mimetype   TEXT,
    resource            TEXT,
    resource_mimetype   TEXT,
    resource_size       BIGINT,
    resource_checksum   TEXT, -- Checksum should be on the form checksumtype=checksum (e.g. SHA1=.....)
    wms                 TEXT,
    updated             TIMESTAMP,
    published           TIMESTAMP,
    keywords            TEXT,
    lu_cultivated       NUMERIC DEFAULT 0,
    lu_desert           NUMERIC DEFAULT 0,
    lu_flooded          NUMERIC DEFAULT 0,
    lu_forest           NUMERIC DEFAULT 0,
    lu_herbaceous       NUMERIC DEFAULT 0,
    lu_ice              NUMERIC DEFAULT 0,
    lu_urban            NUMERIC DEFAULT 0,
    lu_water            NUMERIC DEFAULT 0,
    hashes              TEXT[],
    snowcover           NUMERIC,
    cloudcover          NUMERIC,
    new_version         TEXT,
    isnrt               INTEGER DEFAULT 0,
    realtime            TEXT,
    dhusingestdate		TIMESTAMP
);
CREATE INDEX idx_identifier_features ON resto.features (identifier);

SELECT AddGeometryColumn('resto', 'features', 'geometry', '4326', 'GEOMETRY', 2);

-- 
-- users schema contains users descriptions tables
--
CREATE SCHEMA usermanagement;

--
-- users table list user informations
--
CREATE TABLE usermanagement.users (
    userid              SERIAL PRIMARY KEY,
    email               TEXT UNIQUE,  -- should be an email adress
    groupname           TEXT, -- group name
    username            TEXT,
    givenname           TEXT,
    lastname            TEXT,
    password            TEXT NOT NULL, -- stored as sha1
    registrationdate    TIMESTAMP NOT NULL,
    activationcode      TEXT NOT NULL UNIQUE, -- activation code store as sha1
    activated           INTEGER DEFAULT 0,
    organization        TEXT,
    nationality         TEXT,
    domain              TEXT,
    use                 TEXT,
    country             TEXT,
    adress              TEXT,
    numtel              TEXT,
    numfax              TEXT,
    instantdownloadvolume	INTEGER,
    weeklydownloadvolume	INTEGER
);
CREATE INDEX idx_email_users ON usermanagement.users (email);
CREATE INDEX idx_groupname_users ON usermanagement.users (groupname);

--
-- rights table list user rights on collection
--
CREATE TABLE usermanagement.rights (
    gid                 SERIAL PRIMARY KEY, -- unique id
    collection          TEXT, -- same as collection in resto.collections
    featureid           TEXT, -- same as identifier in resto.features
    productidentifier   TEXT, 
    emailorgroup        TEXT NOT NULL,  -- email or group name (from usermanagement.users)
    search              INTEGER DEFAULT 0,
    visualize           INTEGER DEFAULT 0,
    download            INTEGER DEFAULT 0,
    canpost             INTEGER DEFAULT 0,
    canput              INTEGER DEFAULT 0,
    candelete           INTEGER DEFAULT 0,
    filters             TEXT -- serialized json representation of services rights
);
CREATE INDEX idx_emailorgroup_rights ON usermanagement.rights (emailorgroup);

--
-- history table stores all user requests
--
CREATE TABLE usermanagement.history (
    gid                 SERIAL PRIMARY KEY,
    userid              INTEGER DEFAULT -1,
    method              TEXT,
    service             TEXT,
    collection          TEXT,
    resourceid          TEXT,
    query               TEXT DEFAULT NULL,
    querytime           TIMESTAMP,
    url                 TEXT DEFAULT NULL,
    ip                  TEXT
);
CREATE INDEX idx_service_history ON usermanagement.history (service);
CREATE INDEX idx_userid_history ON usermanagement.history (userid);
CREATE INDEX idx_querytime_history ON usermanagement.history (querytime);

--
-- cart table stores user download request
--
CREATE TABLE usermanagement.cart (
    gid                 SERIAL PRIMARY KEY,
    email               TEXT,
    itemid              TEXT NOT NULL,
    querytime           TIMESTAMP,
    item                TEXT NOT NULL -- item as JSON
);
CREATE INDEX idx_email_cart ON usermanagement.cart (email);
CREATE INDEX idx_itemid_cart ON usermanagement.cart (itemid);

--
-- orders table stores user orders
--
CREATE TABLE usermanagement.orders (
    gid                 SERIAL PRIMARY KEY,
    email               TEXT,
    orderid             TEXT NOT NULL,
    querytime           TIMESTAMP,
    items               TEXT NOT NULL -- items as an array of JSON cart item
);
CREATE INDEX idx_email_orders ON usermanagement.orders (email);
CREATE INDEX idx_orderid_orders ON usermanagement.orders (orderid);
CREATE INDEX idx_querytime_orders ON usermanagement.orders (querytime);

--
-- temporary download table
--
CREATE TABLE usermanagement.sharedlinks (
    gid                 SERIAL PRIMARY KEY,
    token               TEXT UNIQUE NOT NULL,
    url                 TEXT NOT NULL,
    validity            TIMESTAMP,
    email	            TEXT
);
CREATE INDEX idx_token_sharedlinks ON usermanagement.sharedlinks (token);

--
-- Revoked tokens table
-- On insert trigger delete entries older than 48 hours
--
CREATE TABLE usermanagement.revokedtokens (
    gid                 SERIAL PRIMARY KEY,
    token               TEXT UNIQUE NOT NULL,
    creationdate        TIMESTAMP NOT NULL DEFAULT now()
);
CREATE INDEX idx_token_revokedtokens ON usermanagement.revokedtokens (token);
CREATE OR REPLACE FUNCTION delete_old_tokens() RETURNS trigger
    LANGUAGE plpgsql
    AS \$\$
BEGIN
  DELETE FROM usermanagement.revokedtokens WHERE creationdate < now() - INTERVAL '2 days';
  RETURN NEW;
END;
\$\$;
CREATE TRIGGER old_tokens_gc AFTER INSERT ON usermanagement.revokedtokens EXECUTE PROCEDURE delete_old_tokens();


CREATE TABLE usermanagement.groups
(
  gid serial NOT NULL,
  groupname text NOT NULL,
  description text,
  CONSTRAINT groups_pkey PRIMARY KEY (gid),
  CONSTRAINT groups_groupname_key UNIQUE (groupname)
);
CREATE INDEX idx_groupname_groups ON usermanagement.groups (groupname);
