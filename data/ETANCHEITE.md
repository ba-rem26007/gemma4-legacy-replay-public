# Étanchéité TRAIN / TEST

- **Split temporel** : coupure au 2025-06-01. TEST = bugs mergés à cette date ou après (187), TRAIN = avant (820).
- **Non-recouvrement** : tout bug TRAIN qui touche une même fonction (fichier + Classe::méthode) qu'un bug TEST est exclu,
  ainsi que tout bug partageant la PR ou l'issue. Exclus : **58**.
- Le glossaire (`glossary_mine.py --gitlog`) doit être extrait avec `git log --before=2025-06-01`.
- Méthode déterministe (`trajectories/reconstruct.py`), relancée à chaque changement de coupure ou de catalogue.

Exemples d'exclusions : [(38257, ['themes/_core/js/address.js:handleCountryChange']), (38168, ['classes/Category.php:CategoryCore::getParentsCategories']), (37818, ['src/PrestaShopBundle/Form/Admin/Improve/Shipping/Carrier/GeneralSettings.php:GeneralSettings::buildForm']), (36876, ['src/PrestaShopBundle/Form/Admin/Improve/Shipping/Carrier/GeneralSettings.php:GeneralSettings::buildForm']), (36664, ['classes/Product.php:ProductCore::getAttributesParams']), (36374, ['src/Adapter/Order/OrderDetailUpdater.php:OrderDetailUpdater::updateOrderDetailsTaxes']), (35812, ['classes/Cart.php:CartCore::getProducts']), (35372, ['classes/Cart.php:CartCore::getProducts']), (35321, ['controllers/front/listing/CategoryController.php:CategoryControllerCore::init']), (35166, ['classes/controller/FrontController.php:FrontControllerCore::init'])]
