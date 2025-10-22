CREATE TYPE public.userlevel AS ENUM ('Admin', 'User', 'Devel');
CREATE TYPE public.store_type AS ENUM ('pg', 'qgs');
CREATE TYPE public.layer_query_type AS ENUM ('gpkg', 'postgres', 'shp', 'gdb');

CREATE TYPE public.inspire_conformity_type AS ENUM ('conformant', 'nonconformant', 'unknown');
CREATE TYPE public.cit_role_type AS ENUM ('pointOfContact', 'originator', 'publisher', 'author', 'custodian');

CREATE TABLE public.user (	id SERIAL PRIMARY KEY,
  name character varying(250),
  email character varying(250),
  password character varying(250),
	ftp_user character varying(250),
	pg_password character varying(250),
  accesslevel public.userlevel,
	secret_key uuid DEFAULT gen_random_uuid(),
	owner_id integer NOT NULL	REFERENCES public.user(id),
	UNIQUE(email)
);

CREATE TABLE public.access_group (	id SERIAL PRIMARY KEY,
	name character varying(255) NOT NULL,
	owner_id integer NOT NULL	REFERENCES public.user(id),
	UNIQUE(name)
);

CREATE TABLE public.user_access (	id SERIAL PRIMARY KEY,
    user_id integer NOT NULL					REFERENCES public.user(id),
    access_group_id integer NOT NULL	REFERENCES public.access_group(id),
		UNIQUE(user_id, access_group_id)
);

CREATE TABLE public.basemaps (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    thumbnail TEXT,
    url TEXT NOT NULL,
    type VARCHAR(50) NOT NULL DEFAULT 'xyz',
    attribution TEXT,
    min_zoom INTEGER DEFAULT 0,
    max_zoom INTEGER DEFAULT 18,
    public BOOLEAN DEFAULT FALSE,
    owner_id INTEGER NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE public.basemaps_access ( id SERIAL PRIMARY KEY,
    basemaps_id INTEGER NOT NULL        REFERENCES public.basemaps(id),
    access_group_id INTEGER NOT NULL    REFERENCES public.access_group(id)
);

CREATE TABLE public.store (	id SERIAL PRIMARY KEY,
	name character varying(250) NOT NULL,
	type public.store_type NOT NULL,
	owner_id integer NOT NULL	REFERENCES public.user(id),
	UNIQUE(name)
);

CREATE TABLE public.store_access (	id SERIAL PRIMARY KEY,
    store_id integer NOT NULL				REFERENCES public.store(id),
    access_group_id integer NOT NULL	REFERENCES public.access_group(id),
		UNIQUE(store_id, access_group_id)
);

CREATE TABLE public.pg_store (
	id integer PRIMARY KEY REFERENCES public.store(id),
	host character varying(250) NOT NULL,
	port integer NOT NULL default 5432,
	username character varying(250) NOT NULL,
  password character varying(250) NOT NULL,
	schema character varying(80) DEFAULT 'public',
	dbname character varying(80) NOT NULL,
	svc_name character varying(50) NOT NULL
);

CREATE TABLE public.qgs_store (
	id integer PRIMARY KEY REFERENCES public.store(id),
	public BOOLEAN DEFAULT False
);

CREATE TABLE public.layer (	id SERIAL PRIMARY KEY,
	name character varying(250) NOT NULL,
	description character varying(250) NOT NULL,
	type public.store_type NOT NULL,
	public BOOLEAN DEFAULT False,
	store_id integer NOT NULL	REFERENCES public.store(id),
	owner_id integer NOT NULL	REFERENCES public.user(id),
	last_updated TIMESTAMP DEFAULT NOW(),
	UNIQUE(name)
);

CREATE TABLE public.layer_access (	id SERIAL PRIMARY KEY,
    layer_id integer NOT NULL					REFERENCES public.layer(id),
    access_group_id integer NOT NULL	REFERENCES public.access_group(id),
		UNIQUE(layer_id, access_group_id)
);

CREATE TABLE public.pg_layer (
	id integer PRIMARY KEY REFERENCES public.layer(id),
	tbl character varying(50) NOT NULL,
	geom character varying(50) NOT NULL
);

CREATE TABLE public.qgs_layer (
	id integer PRIMARY KEY REFERENCES public.layer(id),
	basemap_id integer NOT NULL	REFERENCES public.basemaps(id),
	cached BOOLEAN DEFAULT False,
	proxyfied BOOLEAN DEFAULT False,
	customized BOOLEAN DEFAULT False,
	exposed BOOLEAN DEFAULT False,
	show_charts BOOLEAN DEFAULT False,
	show_dt BOOLEAN DEFAULT False,
	show_query BOOLEAN DEFAULT False,
	show_fi_edit BOOLEAN DEFAULT False, /* show feature info edit */
	print_layout character varying(250) DEFAULT NULL,
	layers TEXT NOT NULL
);

CREATE TABLE public.access_key ( id SERIAL PRIMARY KEY,
	access_key uuid DEFAULT gen_random_uuid(),
	valid_until TIMESTAMP NOT NULL,
	ip_restricted BOOLEAN DEFAULT False,
	owner_id integer NOT NULL	REFERENCES public.user(id),
	UNIQUE(access_key)
);

CREATE TABLE public.access_key_ips (	id SERIAL PRIMARY KEY,
	access_key_id integer NOT NULL	REFERENCES public.access_key(id) ON DELETE CASCADE,
  addr inet NOT NULL,
	UNIQUE(access_key_id, addr)
);

CREATE TABLE public.layer_query (	id SERIAL PRIMARY KEY,
    name character varying(50) NOT NULL,
    description character varying(250) NOT NULL,
    badge character varying(50) NOT NULL,
    database_type public.layer_query_type NOT NULL,
    sql_query TEXT NOT NULL,
    layer_id integer NOT NULL REFERENCES public.layer(id),
    owner_id integer NOT NULL REFERENCES public.user(id),
    UNIQUE(name)
);

CREATE TABLE public.property_filter (	id SERIAL PRIMARY KEY,
    name character varying(50) NOT NULL,
    feature character varying(50) NOT NULL,
    property character varying(50) NOT NULL,
    layer_id integer NOT NULL REFERENCES public.layer(id),
    owner_id integer NOT NULL REFERENCES public.user(id),
    UNIQUE(name)
);

CREATE TABLE public.layer_report (	id SERIAL PRIMARY KEY,
    name character varying(50) NOT NULL,
    description character varying(250) NOT NULL,
    badge character varying(50) NOT NULL,
    database_type public.layer_query_type NOT NULL,
    sql_query TEXT NOT NULL,
    layer_id integer NOT NULL REFERENCES public.layer(id),
    owner_id integer NOT NULL REFERENCES public.user(id),
    UNIQUE(name)
);

CREATE TABLE public.layer_metadata (id SERIAL PRIMARY KEY,
    layer_id integer NOT NULL REFERENCES public.layer(id),
    
    /* Basic Information */
    title character varying(250) NOT NULL,
    resource_identifier bigint GENERATED ALWAYS AS IDENTITY,
    abstract character varying(250) NOT NULL,
    purpose character varying(250) NOT NULL,
    keywords character varying(250) NOT NULL,
    language character varying(20) NOT NULL,
    character_set character varying(20) NOT NULL,
    maintenance_frequency character varying(3) DEFAULT '012',
    
    /* Citation */
    cit_date DATE NOT NULL,
    cit_responsible_org character varying(250) NOT NULL,
    cit_responsible_person character varying(250) NOT NULL,
    cit_role public.cit_role_type NOT NULL,
    
    /* Spatial Information */
    west NUMERIC NOT NULL,
    east NUMERIC NOT NULL,
    south NUMERIC NOT NULL,
    north NUMERIC NOT NULL,
    coordinate_system character varying(100) NOT NULL,
    spatial_resolution INTEGER NOT NULL,
    
    /* Temporal Information */
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    
    /* Data Quality */
    lineage character varying(100) NOT NULL,
    scope character varying(100) NOT NULL,
    conformity_result character varying(100) NOT NULL,
    
    
    /* Responsible Parties */
    metadata_organization character varying(250) NOT NULL,
    metadata_email character varying(100) NOT NULL,
    metadata_role character varying(100) NOT NULL,
    
    /* Access and Use Constraints */
    use_constraints character varying(250),
    use_limitation character varying(250),
    access_constraints character varying(250),
    
    /* INSPIRE Metadata */
    inspire_point_of_contact character varying(250),
    inspire_conformity inspire_conformity_type DEFAULT 'unknown',
    spatial_data_service_url character varying(250) NOT NULL,
    
    /* Distribution */
    distribution_url character varying(250) NOT NULL,
    data_format character varying(50) NOT NULL,
    coupled_resource character varying(250) NOT NULL,
    
    UNIQUE(layer_id)
);

CREATE TABLE public.geostory (	id SERIAL PRIMARY KEY,
    name character varying(50) NOT NULL,
    description character varying(250) NOT NULL,
    public BOOLEAN DEFAULT False,
    export_type character varying(50) NOT NULL,
    owner_id integer NOT NULL REFERENCES public.user(id),
    last_updated TIMESTAMP DEFAULT NOW()
);

CREATE TABLE public.geostory_access (	id SERIAL PRIMARY KEY,
    geostory_id integer NOT NULL		REFERENCES public.geostory(id),
    access_group_id integer NOT NULL	REFERENCES public.access_group(id),
	UNIQUE(geostory_id, access_group_id)
);

CREATE TABLE public.geostory_wms ( id SERIAL PRIMARY KEY,
	story_id integer REFERENCES public.geostory(id),
	layer_id integer REFERENCES public.layer(id),
	basemap_id integer REFERENCES public.basemaps(id),
	section_order integer NOT NULL,
	title character varying(250) NOT NULL,
	layers TEXT NOT NULL,
	content TEXT NOT NULL,
	map_center character varying(50) NOT NULL,
	map_zoom integer DEFAULT 4
);

CREATE TABLE public.geostory_html ( id SERIAL PRIMARY KEY,
	story_id integer REFERENCES public.geostory(id),
	section_order integer NOT NULL,
	title character varying(250) NOT NULL,
	content TEXT NOT NULL
);

CREATE TABLE public.geostory_upload ( id SERIAL PRIMARY KEY,
	story_id integer REFERENCES public.geostory(id),
	section_order integer NOT NULL,
	title character varying(250) NOT NULL,
	fillColor character varying(10) NOT NULL,
	strokeColor character varying(10) NOT NULL,
	strokeWidth real DEFAULT 1.0,
	fillOpacity real DEFAULT 0.4,
	pointRadius real DEFAULT 5,
	content TEXT NOT NULL
);

CREATE TABLE public.geostory_pg ( id SERIAL PRIMARY KEY,
	story_id integer REFERENCES public.geostory(id),
	pg_layer_id integer REFERENCES public.pg_layer(id),
	section_order integer NOT NULL,
	title character varying(250) NOT NULL,
	fillColor character varying(10) NOT NULL,
	strokeColor character varying(10) NOT NULL,
	strokeWidth real DEFAULT 1.0,
	fillOpacity real DEFAULT 0.4,
	pointRadius real DEFAULT 5,
	content TEXT NOT NULL
);

CREATE TABLE public.web_link (	id SERIAL PRIMARY KEY,
	name character varying(200),
    description character varying(255),
    url character varying(250),
    public BOOLEAN DEFAULT False,
    last_updated TIMESTAMP DEFAULT NOW()
);

CREATE TABLE public.web_link_access (	id SERIAL PRIMARY KEY,
    web_link_id integer NOT NULL		REFERENCES public.web_link(id),
    access_group_id integer NOT NULL	REFERENCES public.access_group(id),
	UNIQUE(web_link_id, access_group_id)
);

CREATE TABLE public.doc (	id SERIAL PRIMARY KEY,
	name character varying(200),
    description character varying(255),
    filename character varying(250),
    public BOOLEAN DEFAULT False,
    last_updated TIMESTAMP DEFAULT NOW()
);

CREATE TABLE public.doc_access (	id SERIAL PRIMARY KEY,
    doc_id integer NOT NULL		REFERENCES public.doc(id),
    access_group_id integer NOT NULL	REFERENCES public.access_group(id),
	UNIQUE(doc_id, access_group_id)
);

CREATE TABLE public.dashboard (	id SERIAL PRIMARY KEY,
	name character varying(200),
    description character varying(255),
    filename character varying(250),
    public BOOLEAN DEFAULT False,
    layer_id integer NOT NULL	REFERENCES public.layer(id),
    owner_id integer NOT NULL REFERENCES public.user(id),
    last_updated TIMESTAMP DEFAULT NOW()
);

CREATE TABLE public.dashboard_access (	id SERIAL PRIMARY KEY,
    dashboard_id integer NOT NULL		REFERENCES public.dashboard(id),
    access_group_id integer NOT NULL	REFERENCES public.access_group(id),
	UNIQUE(dashboard_id, access_group_id)
);

CREATE TABLE public.topic (	id SERIAL PRIMARY KEY,
	name character varying(200),
    description character varying(255)
);

CREATE TABLE public.topic_layer (	id SERIAL PRIMARY KEY,
    topic_id integer NOT NULL	REFERENCES public.topic(id),
    layer_id integer NOT NULL	REFERENCES public.layer(id),
	UNIQUE(topic_id, layer_id)
);

CREATE TABLE public.topic_geostory (	id SERIAL PRIMARY KEY,
    topic_id integer NOT NULL	REFERENCES public.topic(id),
    geostory_id integer NOT NULL	REFERENCES public.geostory(id),
	UNIQUE(topic_id, geostory_id)
);

CREATE TABLE public.topic_web_link (	id SERIAL PRIMARY KEY,
    topic_id integer NOT NULL	REFERENCES public.topic(id),
    web_link_id integer NOT NULL	REFERENCES public.web_link(id),
	UNIQUE(topic_id, web_link_id)
);

CREATE TABLE public.topic_doc (	id SERIAL PRIMARY KEY,
    topic_id integer NOT NULL	REFERENCES public.topic(id),
    doc_id integer NOT NULL	REFERENCES public.doc(id),
	UNIQUE(topic_id, doc_id)
);

CREATE TABLE public.gemet (	id SERIAL PRIMARY KEY,
	name character varying(200),
    description character varying(255)
);

CREATE TABLE public.gemet_layer (	id SERIAL PRIMARY KEY,
    gemet_id integer NOT NULL	REFERENCES public.gemet(id),
    layer_id integer NOT NULL	REFERENCES public.layer(id),
	UNIQUE(gemet_id, layer_id)
);

CREATE TABLE public.gemet_geostory (	id SERIAL PRIMARY KEY,
    gemet_id integer NOT NULL	    REFERENCES public.gemet(id),
    geostory_id integer NOT NULL	REFERENCES public.geostory(id),
	UNIQUE(gemet_id, geostory_id)
);

CREATE TABLE public.gemet_web_link (	id SERIAL PRIMARY KEY,
    gemet_id integer NOT NULL	    REFERENCES public.gemet(id),
    web_link_id integer NOT NULL	REFERENCES public.web_link(id),
	UNIQUE(gemet_id, web_link_id)
);

CREATE TABLE public.gemet_doc (	id SERIAL PRIMARY KEY,
    gemet_id integer NOT NULL	REFERENCES public.gemet(id),
    doc_id integer NOT NULL	    REFERENCES public.doc(id),
	UNIQUE(gemet_id, doc_id)
);

CREATE TABLE public.store_relation (	id SERIAL PRIMARY KEY,
    store_id integer NOT NULL	REFERENCES public.qgs_store(id),
	name            character varying(255) NOT NULL,
	parent_layer    character varying(255) NOT NULL,
	parent_field    character varying(255) NOT NULL,
	child_layer    character varying(255) NOT NULL,
	child_field    character varying(255) NOT NULL,
	child_list_fields    character varying(255) NOT NULL,
	owner_id integer NOT NULL	REFERENCES public.user(id),
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	UNIQUE(name)
);

CREATE FUNCTION check_doc_key(acc_k uuid, ip_addr inet, doc_id integer) RETURNS INTEGER AS $$
declare
    v_cnt numeric;
begin
    v_cnt := 0;
UPDATE public.access_key SET valid_until = valid_until + interval '15 minutes'
WHERE id IN (
	SELECT k1.id
	    FROM public.access_key k1
	            INNER JOIN public.access_key_ips k2 ON (k1.ip_restricted = 'f') OR (k1.ip_restricted = 't' AND k1.id = k2.access_key_id AND k2.addr=ip_addr)
	            INNER JOIN public.user_access g ON g.user_id = k1.owner_id
	            INNER JOIN public.doc_access d ON d.access_group_id = g.access_group_id AND d.doc_id=doc_id
	    WHERE access_key=acc_k AND valid_until >= now()
			LIMIT 1
	);
GET DIAGNOSTICS v_cnt = ROW_COUNT;
RETURN v_cnt;
end;
$$ LANGUAGE plpgsql;

CREATE FUNCTION check_geostory_key(acc_k uuid, ip_addr inet, gs_id integer) RETURNS INTEGER AS $$
declare
    v_cnt numeric;
begin
    v_cnt := 0;
UPDATE public.access_key SET valid_until = valid_until + interval '15 minutes'
WHERE id IN (
	SELECT k1.id
	    FROM public.access_key k1
	            INNER JOIN public.access_key_ips k2 ON (k1.ip_restricted = 'f') OR (k1.ip_restricted = 't' AND k1.id = k2.access_key_id AND k2.addr=ip_addr)
	            INNER JOIN public.user_access g ON g.user_id = k1.owner_id
	            INNER JOIN public.geostory_access gs ON gs.access_group_id = gs.access_group_id AND gs.geostory_id=gs_id
	    WHERE access_key=acc_k AND valid_until >= now()
			LIMIT 1
	);
GET DIAGNOSTICS v_cnt = ROW_COUNT;
RETURN v_cnt;
end;
$$ LANGUAGE plpgsql;

CREATE FUNCTION check_layer_key(acc_k uuid, ip_addr inet, lay_id integer) RETURNS INTEGER AS $$
declare
    v_cnt numeric;
begin
    v_cnt := 0;
UPDATE public.access_key SET valid_until = valid_until + interval '15 minutes'
WHERE id IN (
	SELECT k1.id
	    FROM public.access_key k1
	            INNER JOIN public.access_key_ips k2 ON (k1.ip_restricted = 'f') OR (k1.ip_restricted = 't' AND k1.id = k2.access_key_id AND k2.addr=ip_addr)
	            INNER JOIN public.user_access g ON g.user_id = k1.owner_id
	            INNER JOIN public.layer_access l ON l.access_group_id = g.access_group_id AND l.layer_id=lay_id
	    WHERE access_key=acc_k AND valid_until >= now()
			LIMIT 1
	);
GET DIAGNOSTICS v_cnt = ROW_COUNT;
RETURN v_cnt;
end;
$$ LANGUAGE plpgsql;

CREATE FUNCTION check_store_key(acc_k uuid, ip_addr inet, sto_id integer) RETURNS INTEGER AS $$
declare
    v_cnt numeric;
begin
    v_cnt := 0;
UPDATE public.access_key SET valid_until = valid_until + interval '15 minutes'
WHERE id IN (
	SELECT k1.id
	    FROM public.access_key k1
	            INNER JOIN public.access_key_ips k2 ON (k1.ip_restricted = 'f') OR (k1.ip_restricted = 't' AND k1.id = k2.access_key_id AND k2.addr=ip_addr)
	            INNER JOIN public.user_access g ON g.user_id = k1.owner_id
	            INNER JOIN public.store_access l ON l.access_group_id = g.access_group_id AND l.store_id=sto_id
	    WHERE access_key=acc_k AND valid_until >= now()
			LIMIT 1
	);
GET DIAGNOSTICS v_cnt = ROW_COUNT;
RETURN v_cnt;
end;
$$ LANGUAGE plpgsql;
