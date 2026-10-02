"""Règles d'Architecture & Conventions Métier PrestaShop.

Injectables dans le prompt de l'agent pour guider la localisation (mots-clés pertinents)
et l'édition chirurgicale selon les conventions exactes de PrestaShop 8.x / 9.x.
"""

PRESTASHOP_RULES = """RÈGLES D'ARCHITECTURE & CONVENTIONS PRESTASHOP :
1. DUALITÉ LEGACY / SYMFONY (Où chercher ?) :
   - Legacy : classes/Nom.php (déclarée 'class NomCore extends ObjectModel'). Attention : le fichier est classes/Nom.php, JAMAIS NomCore.php.
   - Contrôleurs Legacy : controllers/admin/AdminNomController.php ou controllers/front/NomController.php.
   - Moderne Symfony : src/Core/ (Domain, CQRS, Grid, QueryBuilder) et src/PrestaShopBundle/ (Contrôleurs, Form Types).
   - Ponts Symfony ↔ Legacy : src/Adapter/ (CommandHandlers, Repositories).
2. TEMPLATES & VUES :
   - Back-Office moderne : src/PrestaShopBundle/Resources/views/Admin/**/*.html.twig
   - Legacy BO / FO / PDF : admin-dev/themes/**/*.tpl, themes/classic/**/*.tpl, pdf/*.tpl
3. MULTIBOUTIQUE (MULTISHOP) :
   - Les tables de données ont souvent une table déclinée ps_*_shop (ex: ps_product_shop, ps_country_shop, ps_category_shop).
   - Toujours filtrer ou joindre avec contextShopIds, Shop::isFeatureActive(), ou Context::getContext()->shop->id.
4. HOOKS & DISPATCHERS :
   - Legacy : Hook::exec('actionNomHook', $params).
   - Symfony Grids : action{GridId}GridQueryBuilderModifier via HookDispatcherInterface.
5. DÉNORMALISATION & STOCKS :
   - Stocks réels et virtuels : table ps_stock_available (géré via StockAvailable::setQuantity).
   - Recherche rapide : tables ps_search_word et ps_search_index (moteur Levenshtein dans classes/Search.php).
6. ÉDITION SÉCURISÉE :
   - Toujours copier les lignes exactes dans SEARCH. Utiliser pSQL() sur les variables dans les requêtes SQL brutes.
"""


def get_rules_prompt():
    """Renvoie le bloc formaté des règles pour injection dans le prompt."""
    return f"\n{PRESTASHOP_RULES}\n"
