INSERT INTO public.user
	(name, email, password, accesslevel, ftp_user, pg_password, secret_key, owner_id)
VALUES
	('QCarta Admin', 'admin@admin.com', 'ADMIN_APP_PASS', 'Admin', 'admin1', 'ADMIN_PG_PASS', '3b0f29cf-6c76-49c8-981c-c67cd1bbdf13', 1),
	('Jane Doe', 'jane@doe.com', 'ADMIN_APP_PASS', 'User', 'jane1', 'ADMIN_PG_PASS', 'e33e51b5-db92-4ad6-a271-75abf831b7d6', 1);
	
INSERT INTO public.access_group
	(name, owner_id)
VALUES
	('Default', 1),
	('ClientGroup1', 1);

INSERT INTO public.user_access
	(user_id, access_group_id)
VALUES
	(1, 1),
	(2, 2);

INSERT INTO public.basemaps
    (name, description, url, type, attribution, min_zoom, max_zoom, public, owner_id, created_at, updated_at, thumbnail)
VALUES
('OpenStreetMap', 'OpenStreetMap standard tiles', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png', 'xyz', '© OpenStreetMap contributors', 0, 19, true, 1, '2025-08-14 14:09:35.54709', '2025-08-14 14:09:35.54709', 'openstreetmap.png'),
('Carto Light', 'Carto light tiles', 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', 'xyz', '© CartoDB', 0, 18, true, 1, '2025-08-14 14:13:00.68385', '2025-08-14 14:13:00.68385', 'carto.png');

INSERT INTO public.basemaps_access
    (basemaps_id, access_group_id)
VALUES
    (1,1),(2,1);
