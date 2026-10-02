INSERT INTO ps_feature (id_feature, position) VALUES (9999, 0) ON DUPLICATE KEY UPDATE position=0;
INSERT INTO ps_feature_lang (id_feature, id_lang, name) VALUES (9999, 1, 'Test Feature') ON DUPLICATE KEY UPDATE name='Test Feature';
INSERT INTO ps_feature_shop (id_feature, id_shop) VALUES (9999, 1) ON DUPLICATE KEY UPDATE id_shop=1;

-- Default value (custom = 0)
INSERT INTO ps_feature_value (id_feature_value, id_feature, position, custom) VALUES (99999, 9999, 0, 0) ON DUPLICATE KEY UPDATE custom=0;
INSERT INTO ps_feature_value_lang (id_feature_value, id_lang, value) VALUES (99999, 1, 'Default Value') ON DUPLICATE KEY UPDATE value='Default Value';

-- Custom value (custom = 1)
INSERT INTO ps_feature_value (id_feature_value, id_feature, position, custom) VALUES (100000, 9999, 0, 1) ON DUPLICATE KEY UPDATE custom=1;
INSERT INTO ps_feature_value_lang (id_feature_value, id_lang, value) VALUES (100000, 1, 'Custom Value 123') ON DUPLICATE KEY UPDATE value='Custom Value 123';
