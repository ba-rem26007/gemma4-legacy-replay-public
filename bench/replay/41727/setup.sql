-- #41727 : module « oracle41727 » déclaré installé et actif (ses fichiers sont créés par le test)
DELETE ms FROM ps_module_shop ms JOIN ps_module m ON m.id_module = ms.id_module WHERE m.name = 'oracle41727';
DELETE FROM ps_module WHERE name = 'oracle41727';
INSERT INTO ps_module (name, active, version) VALUES ('oracle41727', 1, '1.0.0');
INSERT INTO ps_module_shop (id_module, id_shop, enable_device) SELECT id_module, 1, 7 FROM ps_module WHERE name = 'oracle41727';
