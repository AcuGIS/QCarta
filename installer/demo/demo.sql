INSERT INTO  public.store
	(name, type, owner_id)
VALUES
	('usdata',				'pg',  1),
	('USA-PostGIS', 	'qgs', 1),
	('Chicago-ESRI-Shapefiles', 	'qgs', 1),
	('BGS-GeoPackage','qgs', 1),
	('MapWarper-GeoTIFF', 	'qgs', 1),
	('WeatherService-WMS',			 'qgs', 1),
	('Monarch-ESRI-Geodatabase', 'qgs', 1),
	('Qfield-SimpleBeeFarm', 'qgs', 1);
INSERT INTO  public.store_access (store_id, access_group_id) VALUES (1, 1), (2, 1), (3, 1), (4, 1), (5, 1), (6, 1), (7, 1), (8, 1);
INSERT INTO  public.pg_store (id, host, port, username, password, schema, dbname, svc_name) VALUES (1,	'db',	5432,	'admin1',	'ADMIN_PG_PASS',	'public',	'states',	'usdata');
INSERT INTO  public.qgs_store (id, public) VALUES (2, 't'), (3, 'f'), (4, 't'), (5, 'f'), (6, 'f'), (7, 't'), (8, 'f');

INSERT INTO public.layer
	(name, type, store_id, owner_id)
VALUES
 	('usdata',				'pg',	 1,	1),
	('usa',						'qgs', 2,	1),
	('neighborhoods',	'qgs', 3, 1),
	('bgsgrid', 			'qgs', 4, 1),
	('paris1550',			'qgs', 5, 1),
	('nws', 					'qgs', 6, 1),
	('monarchs', 			'qgs', 7, 1),
	('CustomDemo', 		'qgs', 2, 1),
	('qfieldSimpleBee', 'qgs', 8, 1);
INSERT INTO public.layer_access
	(layer_id, access_group_id)
VALUES
	(1, 1), (2, 1), (3, 1), (4, 1), (5, 1), (6, 1), (7, 1), (8, 1), (9, 1),
	(1, 2), (8, 2);
INSERT INTO  public.pg_layer (id, public, tbl, geom) VALUES (1,	'f',	'states',	'geom');
INSERT INTO  public.qgs_layer
	(id, public, cached, proxyfied, customized, exposed, layers)
VALUES
 	(2,	false, true, true, false, false, 'states'),
	(3, false, true, true, false, true,  'neighborhoods,parks,waterways'),
	(4, true,  true, true, false, true,  'GB_Hex_5km_GS_RunningSand_v8,GB_Hex_5km_GS_SolubleRocks_v8'),
	(5, false, true, true, false, false, 'paris'),
	(6, false, true, true, true,  false, 'NDFD Forecast hawaii.apparentt,NDFD Forecast hawaii.apparentt.points'),
	(7, true,  true, true, true, false, 'S_USA.Activity_MBHR_PL — Activity_MBHR_PL'),
	(8, false, true, true, true,  false, 'states'),
	(9, true,  false,true, false, true,  'Fields,Apiary,Tracks');
