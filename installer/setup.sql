CREATE TYPE public.userlevel AS ENUM ('Admin', 'User', 'Devel');
CREATE TYPE public.store_type AS ENUM ('pg', 'qgs');

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
	type public.store_type NOT NULL,
	store_id integer NOT NULL	REFERENCES public.store(id),
	owner_id integer NOT NULL	REFERENCES public.user(id),
	UNIQUE(name)
);

CREATE TABLE public.layer_access (	id SERIAL PRIMARY KEY,
    layer_id integer NOT NULL					REFERENCES public.layer(id),
    access_group_id integer NOT NULL	REFERENCES public.access_group(id),
		UNIQUE(layer_id, access_group_id)
);

CREATE TABLE public.pg_layer (
	id integer PRIMARY KEY REFERENCES public.layer(id),
	public BOOLEAN DEFAULT False,
	tbl character varying(50) NOT NULL,
	geom character varying(50) NOT NULL
);

CREATE TABLE public.qgs_layer (
	id integer PRIMARY KEY REFERENCES public.layer(id),
	public BOOLEAN DEFAULT False,
	cached BOOLEAN DEFAULT False,
	proxyfied BOOLEAN DEFAULT False,
	customized BOOLEAN DEFAULT False,
	exposed BOOLEAN DEFAULT False,
	layers character varying(250) NOT NULL
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
