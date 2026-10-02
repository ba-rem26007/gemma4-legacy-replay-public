-- #40853 : recherche floue activée et mot indexé contenant une apostrophe (s'u), rattaché au produit 1.
UPDATE ps_configuration SET value = '1' WHERE name = 'PS_SEARCH_FUZZY';
DELETE FROM ps_search_index WHERE id_word = 99001;
DELETE FROM ps_search_word WHERE id_word = 99001 OR (id_lang = 1 AND id_shop = 1 AND word = 's''u');
INSERT INTO ps_search_word (id_word, id_shop, id_lang, word) VALUES (99001, 1, 1, 's''u');
INSERT INTO ps_search_index (id_product, id_word, weight) VALUES (1, 99001, 1000);
